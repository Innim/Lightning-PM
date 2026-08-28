<?php

/**
 * Очередь фонового сжатия видео: ограничивает число одновременно работающих
 * процессов ffmpeg на сервере.
 *
 * Слоты хранятся в таблице {@see LPMTables::VIDEO_COMPRESS_SLOTS} — состояние
 * общее для всех процессов и всех пользователей, а не для одной загрузки.
 * Слот занимается одной атомарной операцией, поэтому два параллельных
 * процесса не могут занять один слот или запустить сжатие одного файла дважды.
 *
 * Ожидающие файлы отдельно нигде не хранятся: очередь — это видео со статусом
 * {@see VideoCompressor::STATUS_PROCESSING}, у которых нет занятого слота.
 * Поэтому файл не теряется ни при падении воркера, ни при рестарте сервера.
 */
class VideoCompressQueue extends LPMBaseObject
{
    /**
     * Сколько секунд слоту разрешено оставаться без PID процесса.
     * PID проставляет сам воркер при старте, так что это запас на его запуск:
     * до истечения этого времени слот считается живым.
     */
    const SPAWN_TIMEOUT = 60;

    /**
     * Занимает свободный слот для файла.
     * @param  int $fileId
     * @param  int $maxParallel Предел одновременных сжатий
     * @return int Номер занятого слота или 0, если свободных слотов нет
     *   либо файл уже сжимается.
     */
    public static function acquireSlot($fileId, $maxParallel)
    {
        $fileId = (int)$fileId;
        $maxParallel = max(1, (int)$maxParallel);
        if ($fileId <= 0) {
            return 0;
        }

        $busySlots = [];
        foreach (self::loadSlots() as $slot) {
            if ((int)$slot['fileId'] === $fileId) {
                return 0;
            }

            $busySlots[] = (int)$slot['slotNo'];
        }

        for ($slotNo = 1; $slotNo <= $maxParallel; $slotNo++) {
            if (in_array($slotNo, $busySlots, true)) {
                continue;
            }

            if (self::insertSlot($slotNo, $fileId)) {
                return $slotNo;
            }
            // Слот перехватили между чтением и вставкой — пробуем следующий.
        }

        return 0;
    }

    /**
     * Освобождает слот. Номер и файл проверяются вместе, чтобы не снять
     * чужой слот, если этот уже был переиспользован после реклейма.
     * @param  int $slotNo
     * @param  int $fileId
     * @return bool true, если слот был занят этим файлом и освобождён
     */
    public static function releaseSlot($slotNo, $fileId)
    {
        $res = self::buildAndExecute([
            'DELETE' => LPMTables::VIDEO_COMPRESS_SLOTS,
            'WHERE'  => [
                'slotNo' => (int)$slotNo,
                'fileId' => (int)$fileId,
            ],
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderSaveException();
        }

        return self::getDB()->affected_rows === 1;
    }

    /**
     * Записывает PID процесса воркера, занявшего слот. Вызывается самим
     * воркером при старте: до этого слот удерживается по {@see SPAWN_TIMEOUT}.
     * @param int $slotNo
     * @param int $fileId
     * @param int $pid
     */
    public static function setWorkerPid($slotNo, $fileId, $pid)
    {
        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::VIDEO_COMPRESS_SLOTS,
            'SET'    => [
                'pid'       => (int)$pid,
                'startedAt' => time(),
            ],
            'WHERE'  => [
                'slotNo' => (int)$slotNo,
                'fileId' => (int)$fileId,
            ],
        ]);
    }

    /**
     * Брошенные слоты: их процессы умерли (убиты OOM, упали) или работают
     * дольше отведённого времени. Без освобождения таких слотов убитый
     * воркер навсегда занимал бы место в очереди.
     * @param  int $maxDuration Предельная длительность сжатия, секунд
     * @return array[] Строки таблицы слотов
     */
    public static function loadStaleSlots($maxDuration)
    {
        $now = time();
        $maxDuration = max(1, (int)$maxDuration);
        $stale = [];

        foreach (self::loadSlots() as $slot) {
            if (self::isSlotStale($slot, $now, $maxDuration)) {
                $stale[] = $slot;
            }
        }

        return $stale;
    }

    /**
     * Идентификаторы видео, ожидающих сжатия: помечены как обрабатываемые,
     * но слот не занят. Порядок — от самых старых загрузок.
     * @param  int $limit
     * @return int[]
     */
    public static function loadPendingFileIds($limit)
    {
        $limit = max(1, (int)$limit);

        $res = self::loadFromDV2([
            'SELECT' => '`f`.`fileId`',
            'FROM'   => LPMTables::FILES,
            'AS'     => 'f',
            'JOINS'  => [
                [
                    'LEFT JOIN' => LPMTables::VIDEO_COMPRESS_SLOTS,
                    'AS'        => 's',
                    'ON'        => ['`s`.`fileId`' => self::col('f.fileId')],
                ],
            ],
            'WHERE'  => [
                '`f`.`deleted`' => 0,
                '`f`.`compressStatus`' => VideoCompressor::STATUS_PROCESSING,
                '`s`.`fileId` IS NULL',
            ],
            'ORDER BY' => '`f`.`fileId` ASC',
            'LIMIT'    => $limit,
        ]);

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['fileId'];
        }

        return $ids;
    }

    /**
     * Все занятые слоты.
     * @return array[] Строки таблицы слотов
     */
    private static function loadSlots()
    {
        $res = self::loadFromDV2([
            'SELECT' => ['slotNo', 'fileId', 'pid', 'startedAt'],
            'FROM'   => LPMTables::VIDEO_COMPRESS_SLOTS,
            'ORDER BY' => '`slotNo` ASC',
        ]);

        $slots = [];
        while ($row = $res->fetch_assoc()) {
            $slots[] = $row;
        }

        return $slots;
    }

    /**
     * @return bool true, если слот удалось занять
     */
    private static function insertSlot($slotNo, $fileId)
    {
        $res = self::buildAndExecute([
            'INSERT' => [
                'slotNo'    => (int)$slotNo,
                'fileId'    => (int)$fileId,
                'pid'       => 0,
                'startedAt' => time(),
            ],
            'INTO'   => LPMTables::VIDEO_COMPRESS_SLOTS,
            'IGNORE' => true,
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderSaveException();
        }

        return self::getDB()->affected_rows === 1;
    }

    /**
     * Слот брошен: процесс не запустился, умер или работает слишком долго.
     * @return bool
     */
    private static function isSlotStale(array $slot, $now, $maxDuration)
    {
        $startedAt = (int)$slot['startedAt'];
        $pid = (int)$slot['pid'];

        if ($pid <= 0) {
            return $now - $startedAt > self::SPAWN_TIMEOUT;
        }

        return $now - $startedAt > $maxDuration || !self::isWorkerAlive($pid);
    }

    /**
     * Жив ли процесс воркера с указанным PID.
     * Если проверить нельзя, считаем процесс живым: слот в этом случае
     * освободится по предельной длительности сжатия.
     * @return bool
     */
    private static function isWorkerAlive($pid)
    {
        $pid = (int)$pid;
        if ($pid <= 0) {
            return false;
        }

        // Сверяем командную строку процесса: PID переиспользуются, и без
        // проверки чужой процесс с тем же номером удерживал бы слот.
        $cmdline = '/proc/' . $pid . '/cmdline';
        if (@is_readable($cmdline)) {
            return strpos((string)@file_get_contents($cmdline), VideoCompressor::WORKER_SCRIPT) !== false;
        }

        if (is_dir('/proc')) {
            return is_dir('/proc/' . $pid);
        }

        if (function_exists('posix_kill')) {
            // Нет прав на сигнал (EPERM) — процесс всё-таки существует.
            return @posix_kill($pid, 0)
                || (function_exists('posix_get_last_error') && posix_get_last_error() === 1);
        }

        return true;
    }
}
