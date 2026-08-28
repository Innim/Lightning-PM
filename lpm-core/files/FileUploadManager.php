<?php

use GMFramework\FileSystemUtils;

class FileUploadManager
{
    public static function hasUploads(array $filesData)
    {
        if (!isset($filesData['tmp_name']) || !is_array($filesData['tmp_name'])) {
            return false;
        }

        foreach ($filesData['tmp_name'] as $index => $tmp) {
            $errorCode = isset($filesData['error'][$index]) ? $filesData['error'][$index] : UPLOAD_ERR_OK;
            if (!empty($tmp) || $errorCode !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет выбранные в форме файлы, не сохраняя их.
     * Позволяет отсечь некорректные файлы до записи каких-либо данных.
     * @param  array $filesData      Данные поля из $_FILES.
     * @param  int   $availableSlots Количество файлов, которые ещё можно прикрепить.
     * @param  int   $totalLimit     Максимальное количество файлов.
     * @return array Массив сообщений об ошибках. Пустой, если всё в порядке.
     */
    public static function validateUploads(array $filesData, $availableSlots, $totalLimit)
    {
        $errors = [];

        if (!self::hasUploads($filesData)) {
            return $errors;
        }

        $newCount = 0;
        $count = count($filesData['name']);
        for ($index = 0; $index < $count; $index++) {
            $originalName = $filesData['name'][$index];
            $tmpName = $filesData['tmp_name'][$index];
            $errorCode = isset($filesData['error'][$index]) ? $filesData['error'][$index] : UPLOAD_ERR_OK;
            $size = isset($filesData['size'][$index]) ? (int)$filesData['size'][$index] : 0;

            if (self::isEmptyUpload($tmpName, $errorCode)) {
                continue;
            }

            $newCount++;

            $error = self::checkUploadedFile($originalName, $errorCode, $size);
            if (null !== $error) {
                $errors[] = $error;
            }
        }

        if ($newCount > max(0, (int)$availableSlots)) {
            $errors[] = sprintf('Вы не можете прикрепить больше %d файлов', self::getFilesLimit($availableSlots, $totalLimit));
        }

        $totalError = self::checkTotalSize($filesData);
        if (null !== $totalError) {
            $errors[] = $totalError;
        }

        return $errors;
    }

    /**
     * Определяет количество файлов для сообщения о превышении лимита.
     * @param  int $availableSlots Количество файлов, которые ещё можно прикрепить.
     * @param  int $totalLimit     Максимальное количество файлов.
     * @return int
     */
    private static function getFilesLimit($availableSlots, $totalLimit)
    {
        $limit = $totalLimit > 0 ? (int)$totalLimit : (int)$availableSlots;

        return $limit > 0 ? $limit : 1;
    }

    /**
     * Определяет, что в поле не выбран файл.
     * @param  string $tmpName   Путь до временного файла.
     * @param  int    $errorCode Код ошибки загрузки (одна из констант UPLOAD_ERR_*).
     * @return bool
     */
    private static function isEmptyUpload($tmpName, $errorCode)
    {
        return $errorCode === UPLOAD_ERR_NO_FILE || (empty($tmpName) && $errorCode === UPLOAD_ERR_OK);
    }

    /**
     * Проверяет, что выбранный файл может быть загружен.
     * @param  string $originalName Оригинальное имя файла.
     * @param  int    $errorCode    Код ошибки загрузки (одна из констант UPLOAD_ERR_*).
     * @param  int    $size         Размер файла в байтах.
     * @return string|null Текст ошибки или null, если файл может быть загружен.
     */
    private static function checkUploadedFile($originalName, $errorCode, $size)
    {
        if ($errorCode !== UPLOAD_ERR_OK) {
            return self::translateUploadError($errorCode, $originalName);
        }

        if ($size <= 0) {
            return sprintf('Файл "%s" пустой или поврежден', $originalName);
        }

        return null;
    }

    /**
     * Проверяет суммарный размер выбранных файлов.
     * @param  array $filesData Данные поля из $_FILES.
     * @return string|null Текст ошибки или null, если размер в пределах лимита.
     */
    private static function checkTotalSize(array $filesData)
    {
        $total = 0;
        $count = count($filesData['name']);
        for ($index = 0; $index < $count; $index++) {
            $tmpName = $filesData['tmp_name'][$index];
            $errorCode = isset($filesData['error'][$index]) ? $filesData['error'][$index] : UPLOAD_ERR_OK;

            if (self::isEmptyUpload($tmpName, $errorCode)) {
                continue;
            }

            $total += isset($filesData['size'][$index]) ? (int)$filesData['size'][$index] : 0;
        }

        if ($total > MAX_ATTACHMENTS_TOTAL_SIZE_BYTES) {
            return sprintf('Суммарный размер файлов не должен превышать %d Мб', MAX_ATTACHMENTS_TOTAL_SIZE_MB);
        }

        return null;
    }

    public static function upload($itemType, $itemId, $userId, array $filesData, $availableSlots, $totalLimit)
    {
        $result = [
            'uploaded' => [],
            'errors'   => [],
        ];

        if (!self::hasUploads($filesData)) {
            return $result;
        }

        $totalError = self::checkTotalSize($filesData);
        if (null !== $totalError) {
            $result['errors'][] = $totalError;
            return $result;
        }

        if (!self::ensureStorageDirectory($itemType, $itemId)) {
            $result['errors'][] = 'Не удалось создать директорию для загрузки файлов';
            return $result;
        }

        $availableSlots = max(0, (int)$availableSlots);
        $totalLimit = (int)$totalLimit;

        $count = count($filesData['name']);
        for ($index = 0; $index < $count; $index++) {
            $originalName = $filesData['name'][$index];
            $tmpName = $filesData['tmp_name'][$index];
            $errorCode = isset($filesData['error'][$index]) ? $filesData['error'][$index] : UPLOAD_ERR_OK;
            $size = isset($filesData['size'][$index]) ? (int)$filesData['size'][$index] : 0;

            if (self::isEmptyUpload($tmpName, $errorCode)) {
                continue;
            }

            if ($availableSlots <= 0) {
                $result['errors'][] = sprintf(
                    'Вы не можете прикрепить больше %d файлов',
                    self::getFilesLimit($availableSlots, $totalLimit)
                );
                break;
            }

            $checkError = self::checkUploadedFile($originalName, $errorCode, $size);
            if (null !== $checkError) {
                $result['errors'][] = $checkError;
                continue;
            }

            if (!is_uploaded_file($tmpName)) {
                $result['errors'][] = sprintf('Не удалось загрузить файл "%s"', $originalName);
                continue;
            }

            $sanitizedName = self::sanitizeOriginalName($originalName);
            $extension = self::buildStoredExtension($sanitizedName);

            do {
                $storedBase = SecureRandomHelper::str(16);
                $storedName = $extension ? $storedBase . '.' . $extension : $storedBase;
                $relativePath = self::buildRelativePath($itemType, $itemId, $storedName);
                $absolutePath = self::getAbsolutePath($relativePath);
            } while (file_exists($absolutePath));

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                $result['errors'][] = sprintf('Не удалось сохранить файл "%s"', $originalName);
                break;
            }

            $mimeType = self::detectMimeType($absolutePath);
            $realSize = filesize($absolutePath);

            try {
                $file = LPMFile::create(
                    $itemType,
                    $itemId,
                    $userId,
                    $sanitizedName,
                    $mimeType,
                    $realSize,
                    $relativePath
                );
                $result['uploaded'][] = $file;
                $availableSlots--;
            } catch (Exception $e) {
                FileSystemUtils::remove($absolutePath, false);
                $result['errors'][] = 'Ошибка при сохранении данных файла';
                break;
            }
        }

        if (!empty($result['errors']) && !empty($result['uploaded'])) {
            $ids = array_map(function (LPMFile $file) {
                return $file->fileId;
            }, $result['uploaded']);
            LPMFile::delete($itemType, $itemId, $ids);
            $result['uploaded'] = [];
        }

        // Ставим загруженные видео в очередь на фоновое сжатие.
        // Делаем это после отката, чтобы не обрабатывать удалённые файлы.
        foreach ($result['uploaded'] as $file) {
            VideoCompressor::maybeCompress($file);
        }

        return $result;
    }

    public static function ensureStorageDirectory($itemType, $itemId)
    {
        $dir = self::getAbsoluteDirectory($itemType, $itemId);
        return FileSystemUtils::createPath($dir);
    }

    /**
     * Удаляет директорию хранилища сущности, если в ней не осталось файлов.
     * @param int $itemType Одна из констант {@see LPMInstanceTypes}.
     * @param int $itemId
     */
    public static function removeStorageDirectory($itemType, $itemId)
    {
        FileSystemUtils::remove(self::getAbsoluteDirectory($itemType, $itemId), false);
    }

    public static function buildRelativePath($itemType, $itemId, $fileName)
    {
        $base = self::getRelativeBase();
        $segments = [];
        if ($base !== '') {
            $segments[] = $base;
        }
        $segments[] = (int)$itemType;
        $segments[] = (int)$itemId;
        $segments[] = $fileName;

        return implode('/', $segments);
    }

    public static function getAbsolutePath($relativePath)
    {
        return ROOT . FILES_DIR . ltrim($relativePath, '/');
    }

    private static function getAbsoluteDirectory($itemType, $itemId)
    {
        return ROOT . rtrim(UPLOAD_FILES_DIR, '/') . '/' . (int)$itemType . '/' . (int)$itemId . '/';
    }

    private static function getRelativeBase()
    {
        $base = trim(UPLOAD_FILES_DIR, '/');
        $filesDir = trim(FILES_DIR, '/');
        if (strpos($base, $filesDir) === 0) {
            $base = ltrim(substr($base, strlen($filesDir)), '/');
        }

        return $base;
    }

    private static function detectMimeType($path)
    {
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $path);
                if (!empty($detected)) {
                    $mime = $detected;
                }
                finfo_close($finfo);
            }
        }

        return $mime;
    }

    /**
     * Определяет расширение, с которым файл будет сохранён в хранилище.
     *
     * Расширения, которые веб-сервер может отдать на исполнение, отбрасываются:
     * файл сохраняется вовсе без расширения. Оригинальное имя при этом
     * не меняется - оно хранится отдельно и подставляется при скачивании.
     *
     * Белым списком тут не обойтись: вложением может быть файл любого типа.
     * Поэтому это второй рубеж - основной запрет на исполнение задаётся
     * конфигурацией веб-сервера (см. lpm-files/.htaccess).
     * @param  string $name Оригинальное имя файла (уже нормализованное).
     * @return string Расширение без точки или пустая строка.
     */
    private static function buildStoredExtension($name)
    {
        $executableExtensions = [
            'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
            'phps', 'pht', 'phtm', 'phtml', 'phar', 'inc',
            'htaccess', 'htpasswd', 'shtml', 'shtm',
            'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
            'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'asmx',
        ];

        $extension = mb_strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension);

        return in_array($extension, $executableExtensions, true) ? '' : $extension;
    }

    private static function sanitizeOriginalName($name)
    {
        $name = trim((string)$name);
        $name = preg_replace('/[\\\\\/\:\*\?"<>\|]+/', '_', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        if ($name === '') {
            $name = 'file';
        }

        return mb_substr($name, 0, 255);
    }

    /**
     * Возвращает текст ошибки загрузки файла.
     * @param  int    $errorCode Код ошибки (одна из констант UPLOAD_ERR_*).
     * @param  string $fileName  Имя файла.
     * @return string
     */
    public static function translateUploadError($errorCode, $fileName)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf('Файл "%s" превышает допустимый размер', $fileName);
            case UPLOAD_ERR_PARTIAL:
                return sprintf('Файл "%s" был загружен частично', $fileName);
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Не найдена временная директория для загрузки файлов';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Не удалось сохранить файл на диск';
            case UPLOAD_ERR_EXTENSION:
                return sprintf('Загрузка файла "%s" была остановлена расширением PHP', $fileName);
            default:
                return sprintf('Не удалось загрузить файл "%s"', $fileName);
        }
    }
}
