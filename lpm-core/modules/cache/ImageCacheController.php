<?php
/**
 * Контроллер для работы с кэшем изображений.
 * 
 * Используется для кэширования внешних изображений, 
 * например изображений, которые добавляются в комментарий
 * по ссылке.
 */
class ImageCacheController 
{
    const MAX_SIZE_MB = 10;
    const CACHE_SUBDIR = 'cache';

    const MAX_SIZE_PIXELS = 800;

    public function __construct()
    {
    }

    /**
     * Создает кэш изображения по указанному URL.
     * 
     * Изображение будет скачано на сервер и пережато,
     * если подходит под условия.
     * 
     * @param string $url URL изображения для кэширования.
     * @return string|null URL закэшированного изображения или null,
     *                     если не удалось выполнить кэширование.
     */
    public function createCache($url) 
    {
        $basename = md5($url);

        try {
            $filepathWithoutExt = $this->getFilePath($basename);
            
            $downloadedFile = $this->findDownloadedImage($filepathWithoutExt);
            if (empty($downloadedFile)) {
                // скачиваем файл      
                $filepath = DownloadHelper::downloadImage($url, $filepathWithoutExt, self::MAX_SIZE_MB, true);
            } else {
                // Используем скачанный 
                // TODO: неплохо было бы предусмотреть механизм перезагрузки файла в случае чего
                $filepath = $downloadedFile;
            }
            
            $img = new LPMImg();
            $img->setSrcImg($filepath);

            return $this->getCompressedImage($img);
        } catch (Exception $e) {
            // TODO: запись ошибки в лог
            return null;
        }
    }

    private function findDownloadedImage($filepathWithoutExt) {
        $mask = $filepathWithoutExt . '.*';
        $files = glob($mask);
        if (empty($files) || !is_array($files)) {
            return false;
        } else {
            return $files[0];
        }
    }

    private function getCompressedImage(LPMImg $img) {
        // генерируем уменьшенное превью для типов:
        // jpeg, png, webp.
        $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_JPEG2000, IMG_WEBP];
        
        $filepath = $img->getSrcImg();
        list($width, $height, $type, $attr) = getimagesize($filepath);
        if (in_array($type, $allowedTypes) && !empty($width) && !empty($height) && 
                ($width > self::MAX_SIZE_PIXELS || $height > self::MAX_SIZE_PIXELS)) {            
            $ratio = $width / $height;
            if ($width > $height) {
                $previewWidth = min($width, self::MAX_SIZE_PIXELS);
                $previewHeight = $previewWidth / $ratio;
            } else {
                $previewHeight = min($height, self::MAX_SIZE_PIXELS);
                $previewWidth = $previewHeight * $ratio;
            }

            $previewUrl = $img->getCacheImg($previewWidth, $previewHeight);
            if ($previewUrl != null) return $previewUrl;
        }

        return $img->getSource();
    }

    private function getFilePath($name) {
        $cacheDirPath = LPMImg::getSrcImgPath(self::CACHE_SUBDIR);
        if (!is_dir($cacheDirPath) && !mkdir($cacheDirPath)) {
            throw new Exception('Ошибка при создании директории кэша изображений');
        }

        return $cacheDirPath . DIRECTORY_SEPARATOR. $name;
    }
}