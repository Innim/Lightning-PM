<?php

/**
 * Единый механизм логирования приложения.
 *
 * Пишет структурированные записи в файлы каталога логов (см. LOGS_PATH).
 * Для каждого канала ведётся два файла:
 *  - `<channel>.log`       — записи всех уровней (в пределах текущего порога);
 *  - `<channel>.error.log` — только ошибки уровня ERROR, чтобы их наличие
 *                            было видно сразу, отдельно от прочих записей.
 *
 * Уровни по возрастанию важности: DEBUG, INFO, WARNING, ERROR. В обычном
 * режиме пишутся записи уровня INFO и выше; в режиме отладки
 * (LPMGlobals::isDebugMode()) — начиная с DEBUG. Записи уровня ERROR
 * пишутся всегда, независимо от режима. Порог можно переопределить
 * константой LOG_MIN_LEVEL.
 *
 * Формат строки:
 *   [Y-m-d H:i:s] <channel>.<LEVEL>: <message> {<context-json>}
 *
 * Логирование не должно ронять запрос: методы не бросают исключений,
 * ошибки записи файлов подавляются. Данные пишутся только в файлы (не в БД),
 * чтобы логирование работало даже при недоступной базе.
 */
class LPMLog
{
    /** Уровень отладочных сообщений */
    const DEBUG = 100;
    /** Уровень информационных сообщений */
    const INFO = 200;
    /** Уровень предупреждений */
    const WARNING = 300;
    /** Уровень ошибок */
    const ERROR = 400;

    /** Общий канал приложения */
    const CH_APP = 'app';
    /** Канал фонового сжатия видео */
    const CH_VIDEO = 'video';
    /** Канал интеграции с GitLab */
    const CH_GITLAB = 'gitlab';
    /** Канал интеграции со Slack */
    const CH_SLACK = 'slack';
    /** Канал отправки почты */
    const CH_MAIL = 'mail';
    /** Канал email-оповещений */
    const CH_EMAIL = 'email';
    /** Канал кэша изображений */
    const CH_CACHE = 'cache';
    /** Канал интеграции с ИИ-моделями */
    const CH_AI = 'ai';
    /** Канал миграций схемы БД */
    const CH_DB = 'db';

    /**
     * Записать отладочное сообщение (уровень DEBUG).
     * @param string $message текст сообщения
     * @param string $channel канал (одна из констант CH_*)
     * @param array $context ассоциативный массив дополнительных данных
     */
    public static function debug($message, $channel = self::CH_APP, array $context = [])
    {
        self::write(self::DEBUG, $message, $channel, $context);
    }

    /**
     * Записать информационное сообщение (уровень INFO).
     * @param string $message текст сообщения
     * @param string $channel канал (одна из констант CH_*)
     * @param array $context ассоциативный массив дополнительных данных
     */
    public static function info($message, $channel = self::CH_APP, array $context = [])
    {
        self::write(self::INFO, $message, $channel, $context);
    }

    /**
     * Записать предупреждение (уровень WARNING).
     * @param string $message текст сообщения
     * @param string $channel канал (одна из констант CH_*)
     * @param array $context ассоциативный массив дополнительных данных
     */
    public static function warning($message, $channel = self::CH_APP, array $context = [])
    {
        self::write(self::WARNING, $message, $channel, $context);
    }

    /**
     * Записать ошибку (уровень ERROR). Дублируется в `<channel>.error.log`.
     * @param string $message текст сообщения
     * @param string $channel канал (одна из констант CH_*)
     * @param array $context ассоциативный массив дополнительных данных
     */
    public static function error($message, $channel = self::CH_APP, array $context = [])
    {
        self::write(self::ERROR, $message, $channel, $context);
    }

    /**
     * Записать исключение уровнем ERROR. В режиме отладки к контексту
     * добавляется стек вызовов.
     * @param \Throwable $e исключение
     * @param string $channel канал (одна из констант CH_*)
     * @param array $context ассоциативный массив дополнительных данных
     */
    public static function exception(\Throwable $e, $channel = self::CH_APP, array $context = [])
    {
        $message = get_class($e) . ' #' . $e->getCode() . ': ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine();

        if (LPMGlobals::isDebugMode()) {
            $context['trace'] = $e->getTraceAsString();
        }

        self::write(self::ERROR, $message, $channel, $context);
    }

    /**
     * Общая точка записи: проверяет порог уровня, формирует строку и
     * пишет её в файл(ы) канала.
     */
    private static function write($level, $message, $channel, array $context)
    {
        if ($level < self::getMinLevel()) {
            return;
        }

        $channel = self::sanitizeChannel($channel);
        $line = self::format($level, $channel, $message, $context);

        self::appendTo(self::filePath($channel, false), $line);
        if ($level >= self::ERROR) {
            self::appendTo(self::filePath($channel, true), $line);
        }
    }

    /**
     * Минимальный уровень, начиная с которого записи попадают в лог.
     */
    private static function getMinLevel()
    {
        if (defined('LOG_MIN_LEVEL')) {
            return (int)LOG_MIN_LEVEL;
        }

        return LPMGlobals::isDebugMode() ? self::DEBUG : self::INFO;
    }

    /**
     * Собрать строку лога с завершающим переводом строки.
     */
    private static function format($level, $channel, $message, array $context)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] '
            . $channel . '.' . self::levelName($level) . ': '
            . trim((string)$message);

        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $line .= ' ' . ($json === false ? '{"_context":"json_encode failed"}' : $json);
        }

        return $line . "\n";
    }

    /**
     * Человекочитаемое имя уровня.
     */
    private static function levelName($level)
    {
        switch ($level) {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::WARNING:
                return 'WARNING';
            case self::ERROR:
                return 'ERROR';
            default:
                return 'LOG';
        }
    }

    /**
     * Привести имя канала к безопасному имени файла.
     */
    private static function sanitizeChannel($channel)
    {
        $channel = preg_replace('/[^a-z0-9_-]/i', '_', (string)$channel);
        return $channel === '' ? self::CH_APP : $channel;
    }

    /**
     * Путь до файла лога канала.
     * @param bool $errorsOnly вернуть путь до файла с ошибками (`.error.log`)
     */
    private static function filePath($channel, $errorsOnly)
    {
        return LOGS_PATH . $channel . ($errorsOnly ? '.error' : '') . '.log';
    }

    /**
     * Дописать строку в файл, при необходимости выполнив ротацию по размеру.
     */
    private static function appendTo($path, $line)
    {
        if (!self::ensureDir()) {
            return;
        }

        self::rotateIfNeeded($path);
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Гарантирует наличие каталога логов, доступного для записи.
     * @return bool
     */
    private static function ensureDir()
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }

        return is_dir(LOGS_PATH) && is_writable(LOGS_PATH);
    }

    /**
     * Ротация файла лога по достижении предельного размера: текущий файл
     * становится `<path>.1`, старые архивы сдвигаются, самый старый удаляется.
     */
    private static function rotateIfNeeded($path)
    {
        $maxBytes = self::maxFileSizeBytes();
        $archiveCount = self::archiveCount();
        if ($maxBytes <= 0 || $archiveCount < 1 || !is_file($path)) {
            return;
        }

        clearstatcache(true, $path);
        if (filesize($path) < $maxBytes) {
            return;
        }

        $oldest = $path . '.' . $archiveCount;
        if (is_file($oldest)) {
            @unlink($oldest);
        }
        for ($i = $archiveCount - 1; $i >= 1; $i--) {
            if (is_file($path . '.' . $i)) {
                @rename($path . '.' . $i, $path . '.' . ($i + 1));
            }
        }
        @rename($path, $path . '.1');
    }

    /**
     * Предельный размер одного файла лога в байтах.
     */
    private static function maxFileSizeBytes()
    {
        $mb = defined('LOG_FILE_MAX_SIZE_MB') ? (int)LOG_FILE_MAX_SIZE_MB : 5;
        return $mb * 1024 * 1024;
    }

    /**
     * Сколько архивных файлов хранить при ротации.
     */
    private static function archiveCount()
    {
        return defined('LOG_ARCHIVE_COUNT') ? (int)LOG_ARCHIVE_COUNT : 3;
    }
}
