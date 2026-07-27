<?php

/**
 * Асинхронное сжатие загруженных видео через ffmpeg.
 *
 * После загрузки видео помечается статусом "в обработке" и запускается
 * отдельный фоновый процесс ({@see video-compress-worker.php}), который
 * перекодирует файл через ffmpeg. При успешном сжатии оригинал заменяется
 * на сжатую версию (для экономии места на сервере).
 *
 * Работа с БД вынесена в {@see LPMFile}, здесь только оркестрация процесса
 * и файловые операции.
 */
class VideoCompressor
{
    /** Видео в очереди/в процессе сжатия. */
    const STATUS_PROCESSING = 1;
    /** Обработка завершена (файл сжат либо оставлен оригинал). */
    const STATUS_DONE = 2;
    /** Во время сжатия произошла ошибка, оставлен оригинал. */
    const STATUS_FAILED = 3;

    /**
     * Включено ли асинхронное сжатие видео в конфигурации.
     * @return bool
     */
    public static function isEnabled()
    {
        return defined('VIDEO_COMPRESS_ENABLED') && VIDEO_COMPRESS_ENABLED;
    }

    /**
     * Ставит видео в очередь на фоновое сжатие, если фича включена.
     * Помечает файл статусом "в обработке" и запускает фоновый воркер.
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

        if (!self::spawnWorker($file->fileId)) {
            // Фоновый воркер запустить не удалось — не оставляем файл
            // навсегда в статусе "в обработке".
            LPMFile::setCompressStatus($file->fileId, self::STATUS_FAILED);
            $file->compressStatus = self::STATUS_FAILED;
        }
    }

    /**
     * Запускает фоновый процесс сжатия, не блокируя текущий запрос.
     * @param int $fileId
     * @return bool true если процесс удалось запустить
     */
    private static function spawnWorker($fileId)
    {
        $fileId = (int)$fileId;
        if ($fileId <= 0) {
            return false;
        }

        if (!function_exists('exec')) {
            LPMLog::error('exec() недоступна, фоновое сжатие невозможно', LPMLog::CH_VIDEO, ['fileId' => $fileId]);
            return false;
        }

        $php = defined('VIDEO_COMPRESS_PHP_BIN') ? VIDEO_COMPRESS_PHP_BIN : 'php';
        $worker = __DIR__ . '/video-compress-worker.php';

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
            'nohup %s %s %d >> %s 2>&1 &',
            escapeshellcmd($php),
            escapeshellarg($worker),
            $fileId,
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
            return;
        }

        $sourcePath = $file->getAbsolutePath();
        if (!is_file($sourcePath)) {
            LPMLog::warning('Исходный файл отсутствует: ' . $sourcePath, LPMLog::CH_VIDEO, ['fileId' => $file->fileId]);
            LPMFile::setCompressStatus($file->fileId, self::STATUS_FAILED);
            return;
        }

        $targetPath = $sourcePath . '.compressed.mp4';

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
     * Запускает ffmpeg для перекодирования видео в H.264/AAC mp4.
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
            '%s -y -i %s -c:v libx264 -preset medium -crf %d -vf %s '
                . '-c:a aac -b:a 128k -movflags +faststart %s 2>&1',
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
     * Меняет расширение в пути/имени файла.
     */
    private static function changeExtension($path, $ext)
    {
        $dir = pathinfo($path, PATHINFO_DIRNAME);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $newName = $name . '.' . $ext;

        if ($dir === '' || $dir === '.') {
            return $newName;
        }

        return $dir . '/' . $newName;
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
