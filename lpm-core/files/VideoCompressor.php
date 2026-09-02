<?php

/**
 * Асинхронное сжатие загруженных видео через ffmpeg.
 *
 * После загрузки видео помечается статусом "в обработке" и попадает в очередь
 * {@see VideoCompressQueue}: если свободен слот, сразу запускается отдельный
 * фоновый процесс ({@see video-compress-worker.php}), который перекодирует
 * файл через ffmpeg, иначе файл ждёт освобождения слота. При успешном сжатии
 * оригинал заменяется на сжатую версию (для экономии места на сервере).
 *
 * Число одновременных перекодирований ограничено на весь сервер
 * (VIDEO_COMPRESS_MAX_PARALLEL): каждый процесс ffmpeg держит свою память,
 * и без предела одновременная загрузка нескольких роликов её исчерпывает.
 *
 * Очередь разгребается без крона: закончив работу, воркер освобождает слот
 * и запускает следующий ожидающий файл.
 *
 * Работа с БД вынесена в {@see LPMFile} и {@see VideoCompressQueue},
 * здесь только оркестрация процессов и файловые операции.
 */
class VideoCompressor
{
    /** Видео в очереди/в процессе сжатия. */
    const STATUS_PROCESSING = 1;
    /** Обработка завершена (файл сжат либо оставлен оригинал). */
    const STATUS_DONE = 2;
    /** Во время сжатия произошла ошибка, оставлен оригинал. */
    const STATUS_FAILED = 3;

    /** Имя скрипта фонового воркера. */
    const WORKER_SCRIPT = 'video-compress-worker.php';

    /**
     * Как часто очередь разбирается из опроса статуса сжатия, секунд.
     * Опрос идёт каждые несколько секунд у каждой открытой страницы, поэтому
     * разбор ограничен по частоте: он нужен лишь как страховка на случай
     * смерти всех воркеров, а задержка в пределах минуты для сжатия,
     * которое идёт минутами, незаметна.
     */
    const POLL_DISPATCH_INTERVAL = 30;

    /**
     * Включено ли асинхронное сжатие видео в конфигурации.
     * @return bool
     */
    public static function isEnabled()
    {
        return defined('VIDEO_COMPRESS_ENABLED') && VIDEO_COMPRESS_ENABLED;
    }

    /**
     * Предел одновременно работающих процессов сжатия на сервере.
     * @return int
     */
    public static function getMaxParallel()
    {
        return max(1, self::intConst('VIDEO_COMPRESS_MAX_PARALLEL', 3));
    }

    /**
     * Предельное время сжатия одного файла в секундах: по его истечении
     * ffmpeg прерывается, а слот в очереди освобождается.
     * @return int
     */
    public static function getTimeout()
    {
        return max(60, self::intConst('VIDEO_COMPRESS_TIMEOUT', 3600));
    }

    /**
     * Ставит видео в очередь на фоновое сжатие, если фича включена.
     * Помечает файл статусом "в обработке"; воркер запускается сразу либо,
     * если все слоты заняты, когда освободится место.
     * @param LPMFile $file
     */
    public static function maybeCompress(LPMFile $file)
    {
        if (!self::isEnabled() || !$file->isVideo()) {
            return;
        }

        LPMFile::setCompressStatus($file->fileId, self::STATUS_PROCESSING);
        // Обновляем статус и в самом объекте, чтобы свежесозданный комментарий
        // сразу отрисовался с заглушкой сжатия, а не с плеером.
        $file->compressStatus = self::STATUS_PROCESSING;

        self::dispatch();

        // Разбор очереди мог сразу закончить файл ошибкой (воркер не
        // запустился): объект уже уедет в ответ на загрузку, поэтому берём
        // из базы итоговый статус, а не тот, что был выставлен выше.
        $actual = LPMFile::load($file->fileId);
        if ($actual) {
            $file->compressStatus = $actual->compressStatus;
        }
    }

    /**
     * Запускает ожидающие файлы на свободных слотах и освобождает слоты
     * умерших воркеров. Вызывается при загрузке видео и при завершении
     * каждого воркера — так очередь разгружается сама.
     */
    public static function dispatch()
    {
        if (!self::isEnabled()) {
            return;
        }

        self::reclaimStaleSlots();

        $maxParallel = self::getMaxParallel();
        foreach (VideoCompressQueue::loadPendingFileIds($maxParallel) as $fileId) {
            $slotNo = VideoCompressQueue::acquireSlot($fileId, $maxParallel);
            if (!$slotNo) {
                // Свободных слотов нет — оставшиеся файлы ждут; их запустит
                // воркер, который освободит слот первым.
                break;
            }

            if (!self::spawnWorker($fileId, $slotNo)) {
                VideoCompressQueue::releaseSlot($slotNo, $fileId);
                // Фоновый воркер запустить не удалось — не оставляем файл
                // навсегда в статусе "в обработке".
                LPMFile::setCompressStatus($fileId, self::STATUS_FAILED);
            }
        }
    }

    /**
     * Разбор очереди для частых фоновых обращений — опроса статуса сжатия.
     *
     * Отличий от {@see dispatch()} два: выполняется не чаще раза в
     * {@see POLL_DISPATCH_INTERVAL} секунд на весь сервер и никогда не
     * выбрасывает исключений — опрос статуса не должен ни дорожать от числа
     * открытых страниц, ни отвечать ошибкой из-за проблем с очередью.
     */
    public static function dispatchOnPoll()
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            if (!self::isPollDispatchDue()) {
                return;
            }

            self::dispatch();
        } catch (\Throwable $e) {
            LPMLog::exception($e, LPMLog::CH_VIDEO);
        }
    }

    /**
     * Пора ли снова разбирать очередь по опросу статуса. Отметка о времени
     * разбора общая для всех процессов, поэтому предел частоты — на сервер,
     * а не на посетителя. Если отметку хранить негде, разбор не запрещается.
     * @return bool
     */
    private static function isPollDispatchDue()
    {
        if (!self::ensureLogDir()) {
            return true;
        }

        $marker = LOGS_PATH . 'video-compress-dispatch.state';
        clearstatcache(true, $marker);

        if (is_file($marker) && time() - (int)@filemtime($marker) < self::POLL_DISPATCH_INTERVAL) {
            return false;
        }

        @touch($marker);

        return true;
    }

    /**
     * Точка входа фонового воркера: выполняет сжатие и в любом случае
     * освобождает слот, после чего запускает следующий файл из очереди.
     * @param int $fileId
     * @param int $slotNo Номер занятого под этот файл слота
     */
    public static function runJob($fileId, $slotNo)
    {
        $fileId = (int)$fileId;
        $slotNo = (int)$slotNo;

        // Слот занимается до запуска процесса и не знает его PID: без PID
        // реклейм не отличит убитый воркер от живого. Регистрируемся сразу
        // на старте, ещё до долгого кодирования.
        VideoCompressQueue::setWorkerPid($slotNo, $fileId, getmypid());

        // Слот должен освободиться, а очередь — поехать дальше, даже при
        // фатальной ошибке PHP: shutdown-функция отрабатывает и после неё.
        $finished = false;
        $finish = function () use ($slotNo, $fileId, &$finished) {
            if ($finished) {
                return;
            }

            $finished = true;
            // Статус проставляем, пока слот ещё занят: иначе файл успел бы
            // уйти в очередь и получить ошибку уже во время нового сжатия.
            self::failIfUnfinished($fileId);
            VideoCompressQueue::releaseSlot($slotNo, $fileId);
            self::dispatch();
        };
        register_shutdown_function($finish);

        try {
            self::compress($fileId);
        } finally {
            $finish();
        }
    }

    /**
     * Освобождает слоты умерших или зависших воркеров.
     */
    private static function reclaimStaleSlots()
    {
        foreach (VideoCompressQueue::loadStaleSlots(self::getTimeout()) as $slot) {
            $fileId = (int)$slot['fileId'];

            // Воркера уже нет, но запущенный им ffmpeg мог осиротеть: слот
            // сейчас освободится, и без этого на сервере оказалось бы больше
            // одновременных перекодирований, чем разрешено.
            self::killOrphanProcesses($fileId);

            // Статус проставляем, пока слот ещё занят: иначе файл успел бы
            // уйти в очередь и получить ошибку уже во время нового сжатия.
            $failed = self::failIfUnfinished($fileId);

            // Слот мог освободить параллельный процесс — тогда и запись о нём
            // не наша.
            if (VideoCompressQueue::releaseSlot($slot['slotNo'], $fileId) && $failed) {
                LPMLog::warning('Сжатие не завершилось, слот освобождён', LPMLog::CH_VIDEO, [
                    'fileId' => $fileId,
                ]);
            }
        }
    }

    /**
     * Помечает файл ошибкой, если обработка закончилась, так и не проставив
     * терминальный статус (воркер упал, убит или завис). Иначе файл навсегда
     * остался бы "в обработке" и бесконечно возвращался в очередь.
     * @param  int $fileId
     * @return bool true, если статус был изменён
     */
    private static function failIfUnfinished($fileId)
    {
        $file = LPMFile::load($fileId);
        if (!$file || (int)$file->compressStatus !== self::STATUS_PROCESSING) {
            return false;
        }

        LPMFile::setCompressStatus($fileId, self::STATUS_FAILED);

        return true;
    }

    /**
     * Снимает процессы, оставшиеся от умершего воркера этого файла.
     * Опознаёт их по пути результата сжатия в командной строке: имя файла
     * случайное, поэтому под совпадение не попадёт посторонний процесс.
     * @param int $fileId
     */
    private static function killOrphanProcesses($fileId)
    {
        if (!function_exists('posix_kill') || !is_dir('/proc')) {
            return;
        }

        $file = LPMFile::load($fileId);
        if (!$file) {
            return;
        }

        $targetPath = self::getTargetPath($file->getAbsolutePath());
        $killed = false;

        foreach (glob('/proc/[0-9]*/cmdline') as $procFile) {
            $cmdline = @file_get_contents($procFile);
            if ($cmdline === false || strpos($cmdline, $targetPath) === false) {
                continue;
            }

            $pid = (int)basename(dirname($procFile));
            // 9 — SIGKILL; константа объявлена в ext-pcntl, которого может не быть.
            if ($pid > 0 && @posix_kill($pid, 9)) {
                $killed = true;
                LPMLog::warning('Снят осиротевший процесс сжатия', LPMLog::CH_VIDEO, [
                    'fileId' => (int)$fileId,
                    'pid'    => $pid,
                ]);
            }
        }

        if ($killed && is_file($targetPath)) {
            @unlink($targetPath);
        }
    }

    /**
     * Запускает фоновый процесс сжатия, не блокируя текущий запрос.
     * @param int $fileId
     * @param int $slotNo Номер занятого под этот файл слота
     * @return bool true если процесс удалось запустить
     */
    private static function spawnWorker($fileId, $slotNo)
    {
        $fileId = (int)$fileId;
        $slotNo = (int)$slotNo;
        if ($fileId <= 0 || $slotNo <= 0) {
            return false;
        }

        if (!function_exists('exec')) {
            LPMLog::error('exec() недоступна, фоновое сжатие невозможно', LPMLog::CH_VIDEO, ['fileId' => $fileId]);
            return false;
        }

        $php = defined('VIDEO_COMPRESS_PHP_BIN') ? VIDEO_COMPRESS_PHP_BIN : 'php';
        $worker = __DIR__ . '/' . self::WORKER_SCRIPT;

        if (!is_file($worker)) {
            LPMLog::error('Скрипт воркера не найден: ' . $worker, LPMLog::CH_VIDEO);
            return false;
        }

        // Каталог логов должен существовать до запуска: команда ниже
        // перенаправляет вывод в `>> video-compress.log`, и если каталога
        // нет (или он недоступен для записи), редирект упадёт в shell до
        // старта PHP, а файл навсегда останется в статусе "в обработке".
        if (!self::ensureLogDir()) {
            LPMLog::error('Каталог логов недоступен для записи: ' . LOGS_PATH, LPMLog::CH_VIDEO);
            return false;
        }

        // Фоновый `nohup ... &` вернёт управление независимо от того, удалось
        // ли реально запустить PHP. Поэтому предварительно синхронно проверяем,
        // что бинарь PHP CLI доступен и работает, иначе файл навсегда останется
        // в статусе "в обработке" (напр. php не в PATH или неверно настроен
        // VIDEO_COMPRESS_PHP_BIN).
        $probeOutput = [];
        $probeCode = 1;
        exec(escapeshellcmd($php) . ' -v 2>/dev/null', $probeOutput, $probeCode);
        if ($probeCode !== 0) {
            LPMLog::error('PHP CLI недоступен (' . $php . '), фоновое сжатие невозможно', LPMLog::CH_VIDEO, ['fileId' => $fileId]);
            return false;
        }

        // nohup ... & — процесс переживает завершение HTTP-запроса
        $cmd = sprintf(
            'nohup %s %s %d %d >> %s 2>&1 &',
            escapeshellcmd($php),
            escapeshellarg($worker),
            $fileId,
            $slotNo,
            escapeshellarg(self::getLogPath())
        );

        exec($cmd);

        return true;
    }

    /**
     * Выполняет сжатие видео. Вызывается фоновым воркером.
     * Всегда завершает файл терминальным статусом (готово/ошибка).
     * @param int $fileId
     */
    public static function compress($fileId)
    {
        $file = LPMFile::load($fileId);
        if (!$file) {
            LPMLog::warning('Файл не найден', LPMLog::CH_VIDEO, ['fileId' => (int)$fileId]);
            return;
        }

        if (!$file->isVideo()) {
            LPMFile::setCompressStatus($file->fileId, self::STATUS_DONE);
            return;
        }

        $sourcePath = $file->getAbsolutePath();
        if (!is_file($sourcePath)) {
            LPMLog::warning('Исходный файл отсутствует: ' . $sourcePath, LPMLog::CH_VIDEO, ['fileId' => $file->fileId]);
            LPMFile::setCompressStatus($file->fileId, self::STATUS_FAILED);
            return;
        }

        $targetPath = self::getTargetPath($sourcePath);

        try {
            if (!self::runFfmpeg($sourcePath, $targetPath) || !is_file($targetPath)) {
                throw new \RuntimeException('ffmpeg не создал выходной файл');
            }

            // Пока шло кодирование, файл могли удалить (комментарий/задача).
            // Перечитываем запись и не восстанавливаем хранилище для удалённого
            // или отвязанного файла, иначе на диске останется «сирота».
            $file = LPMFile::load($file->fileId);
            if (!$file || $file->deleted) {
                @unlink($targetPath);
                LPMLog::info('Файл удалён во время сжатия — результат отброшен', LPMLog::CH_VIDEO, ['fileId' => (int)$fileId]);
                return;
            }

            $origSize = (int)filesize($sourcePath);
            $newSize = (int)filesize($targetPath);

            // Если сжатие не дало выигрыша — оставляем оригинал
            if ($newSize <= 0 || $newSize >= $origSize) {
                @unlink($targetPath);
                LPMFile::setCompressStatus($file->fileId, self::STATUS_DONE);
                LPMLog::info('Сжатие не уменьшило размер — оставлен оригинал', LPMLog::CH_VIDEO, [
                    'fileId' => $file->fileId,
                    'origSize' => $origSize,
                    'newSize' => $newSize,
                ]);
                return;
            }

            self::swapFile($file, $sourcePath, $targetPath, $origSize, $newSize);
            LPMLog::info('Видео сжато', LPMLog::CH_VIDEO, [
                'fileId' => $file->fileId,
                'origSize' => $origSize,
                'newSize' => $newSize,
            ]);
        } catch (\Throwable $e) {
            if (is_file($targetPath)) {
                @unlink($targetPath);
            }
            LPMFile::setCompressStatus($file->fileId, self::STATUS_FAILED);
            LPMLog::exception($e, LPMLog::CH_VIDEO, ['fileId' => $file->fileId]);
        }
    }

    /**
     * Путь, по которому пишется результат сжатия исходного файла.
     * @param  string $sourcePath
     * @return string
     */
    private static function getTargetPath($sourcePath)
    {
        return $sourcePath . '.compressed.mp4';
    }

    /**
     * Запускает ffmpeg для перекодирования видео в H.264/AAC mp4.
     * Время работы ограничено {@see getTimeout()}, по его истечении процесс
     * прерывается и сжатие считается неудачным.
     * @return bool true если ffmpeg завершился успешно
     */
    private static function runFfmpeg($source, $target)
    {
        $ffmpeg = defined('VIDEO_COMPRESS_FFMPEG_BIN') ? VIDEO_COMPRESS_FFMPEG_BIN : 'ffmpeg';
        $long = self::intConst('VIDEO_COMPRESS_MAX_LONG_SIDE', 1920);
        $short = self::intConst('VIDEO_COMPRESS_MAX_SHORT_SIDE', 1080);
        $crf = self::intConst('VIDEO_COMPRESS_CRF', 28);

        // Ограничиваем разрешение: длинная сторона не более $long, короткая —
        // не более $short, независимо от ориентации (портрет/альбом). Пропорции
        // сохраняются, апскейла нет. Затем приводим стороны к чётным значениям
        // (требование кодека libx264 / формата yuv420p).
        // $long/$short — целые числа, безопасно подставляются в команду.
        $filter = "scale=w='if(gte(iw,ih),min($long,iw),min($short,iw))'"
            . ":h='if(gte(iw,ih),min($short,ih),min($long,ih))':force_original_aspect_ratio=decrease"
            . ',scale=trunc(iw/2)*2:trunc(ih/2)*2';

        $cmd = sprintf(
            '%s%s -y -i %s -c:v libx264 -preset medium -crf %d -vf %s '
                . '-c:a aac -b:a 128k -movflags +faststart %s 2>&1',
            self::getTimeoutPrefix(),
            escapeshellcmd($ffmpeg),
            escapeshellarg($source),
            $crf,
            escapeshellarg($filter),
            escapeshellarg($target)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            LPMLog::error('ffmpeg завершился с кодом ' . $exitCode, LPMLog::CH_VIDEO, [
                'output' => array_slice($output, -5),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Префикс команды, ограничивающий время работы ffmpeg.
     * Возвращает пустую строку, если утилита timeout недоступна.
     * @return string
     */
    private static function getTimeoutPrefix()
    {
        $output = [];
        $exitCode = 1;
        exec('command -v timeout 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0) {
            LPMLog::warning(
                'Утилита timeout недоступна — сжатие не ограничено по времени',
                LPMLog::CH_VIDEO
            );
            return '';
        }

        // -k: добить процесс, если он не завершился по SIGTERM
        return sprintf('timeout -k 10 %d ', self::getTimeout());
    }

    /**
     * Заменяет оригинальный файл сжатой версией на диске и в БД.
     */
    private static function swapFile(LPMFile $file, $sourcePath, $targetPath, $origSize, $newSize)
    {
        $newRelativePath = self::changeExtension($file->path, 'mp4');
        $newAbsolutePath = FileUploadManager::getAbsolutePath($newRelativePath);

        // Переносим сжатый файл на его финальное место
        // (если путь совпадает с оригиналом — оригинал перезаписывается).
        if (!@rename($targetPath, $newAbsolutePath)) {
            throw new \RuntimeException('Не удалось переместить сжатый файл');
        }

        // Удаляем оригинал, если сменилось расширение (это другой файл)
        if ($newAbsolutePath !== $sourcePath && is_file($sourcePath)) {
            @unlink($sourcePath);
        }

        LPMFile::applyCompressionResult(
            $file->fileId,
            $newRelativePath,
            self::changeExtension($file->origName, 'mp4'),
            'video/mp4',
            $newSize,
            $origSize
        );
    }

    /**
     * Меняет расширение в пути/имени файла. Имя без расширения получает его.
     * Остальная часть имени сохраняется как есть, включая точки внутри него.
     * @param  string $path Путь или имя файла.
     * @param  string $ext  Новое расширение без точки.
     * @return string
     */
    private static function changeExtension($path, $ext)
    {
        // Имя режется по разделителям вручную: pathinfo() и basename() зависят
        // от локали и в локали C выбрасывают всё до первого ASCII-символа.
        $slashPos = strrpos($path, '/');
        $dir = false === $slashPos ? '' : substr($path, 0, $slashPos + 1);
        $name = false === $slashPos ? $path : substr($path, $slashPos + 1);

        // Точка в начале — часть имени скрытого файла, а не отделяет расширение.
        $dotPos = strrpos($name, '.');
        if (false !== $dotPos && $dotPos > 0) {
            $name = substr($name, 0, $dotPos);
        }

        return $dir . $name . '.' . $ext;
    }

    /**
     * Целочисленное значение константы конфига с запасным значением.
     * @param string $name
     * @param int    $default
     * @return int
     */
    private static function intConst($name, $default)
    {
        return defined($name) ? (int)constant($name) : $default;
    }

    /**
     * Файл для сырого вывода фонового воркера (stdout/stderr процесса ffmpeg
     * и возможных фатальных ошибок PHP). Пишется shell-редиректом в
     * {@see spawnWorker()}, отдельно от структурного лога канала LPMLog::CH_VIDEO.
     */
    private static function getLogPath()
    {
        return LOGS_PATH . 'video-compress.log';
    }

    /**
     * Гарантирует наличие каталога логов, доступного для записи.
     * @return bool
     */
    private static function ensureLogDir()
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }

        return is_dir(LOGS_PATH) && is_writable(LOGS_PATH);
    }
}
