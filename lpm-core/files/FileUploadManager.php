<?php

use GMFramework\BaseString;
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

    public static function upload($itemType, $itemId, $userId, array $filesData, $availableSlots, $totalLimit)
    {
        $result = [
            'uploaded' => [],
            'errors'   => [],
        ];

        if (!self::hasUploads($filesData)) {
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

            if ($errorCode === UPLOAD_ERR_NO_FILE || (empty($tmpName) && $errorCode === UPLOAD_ERR_OK)) {
                continue;
            }

            if ($availableSlots <= 0) {
                $limitValue = $totalLimit > 0 ? $totalLimit : $availableSlots;
                if ($limitValue <= 0) {
                    $limitValue = 1;
                }
                $result['errors'][] = sprintf('Вы не можете прикрепить больше %d файлов', $limitValue);
                break;
            }

            if ($errorCode !== UPLOAD_ERR_OK) {
                $result['errors'][] = self::translateUploadError($errorCode, $originalName);
                continue;
            }

            if ($size <= 0) {
                $result['errors'][] = sprintf('Файл "%s" пустой или поврежден', $originalName);
                continue;
            }

            if ($size > LPMFile::MAX_SIZE_MB * 1024 * 1024) {
                $result['errors'][] = sprintf('Размер файла "%s" не должен превышать %d Мб', $originalName, LPMFile::MAX_SIZE_MB);
                continue;
            }

            if (!is_uploaded_file($tmpName)) {
                $result['errors'][] = sprintf('Не удалось загрузить файл "%s"', $originalName);
                continue;
            }

            $sanitizedName = self::sanitizeOriginalName($originalName);
            $extension = mb_strtolower((string)pathinfo($sanitizedName, PATHINFO_EXTENSION));
            $extension = preg_replace('/[^a-z0-9]+/i', '', $extension);

            do {
                $storedBase = BaseString::randomStr(16);
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

        return $result;
    }

    public static function ensureStorageDirectory($itemType, $itemId)
    {
        $dir = self::getAbsoluteDirectory($itemType, $itemId);
        return FileSystemUtils::createPath($dir);
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

    private static function translateUploadError($errorCode, $fileName)
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
