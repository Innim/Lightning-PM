<?php
/**
 * Вспомогательные методы для работы с вложенными изображениями.
 */
class AttachmentImageHelper
{
    // Droplr
    const PATTERN_DROPLR = "https?:\/\/d.pr\/i\/[a-z0-9]+";
    // Innim owncloud
    const PATTERN_OWNCLOUD = "https?:\/\/cloud.innim.ru\/(index.php\/)?s\/[a-z0-9]+";
    // imgur gif
    const PATTERN_IMGUR_GIF = "https?:\/\/i.imgur.com\/[a-z0-9]+\.gifv";

    const URL_PATTERNS = [
        self::PATTERN_DROPLR,
        self::PATTERN_OWNCLOUD,
        self::PATTERN_IMGUR_GIF,
    ];

    /**
     * Обрабатывает $url и преобразует его к прямому адресу до картинки.
     *
     * Если URL не подходит по формату под обработку
     * (т.е. не описан в этом методе заранее),
     * то он вернётся без модификации.
     *
     * Метод не проверяет, ведет ли URL действительно на изображение или нет.
     */
    public static function getDirectUrl($url)
    {
        if (preg_match("/^" . self::PATTERN_DROPLR . "$/i", $url)) {
            // Если картинка из сервиса droplr (http://droplr.com/)
            $url .= '+';
        } elseif (preg_match("/^" . self::PATTERN_OWNCLOUD . "$/i", $url)) {
            // Если картинка из сервиса ownCloud (http://cloud.innim.ru/)
            $url .= '/download';
        } elseif (preg_match("/^" . self::PATTERN_IMGUR_GIF . "$/i", $url)) {
            // Если картинка с imgur (https://imgur.com/) и это gif,
            // то надо обрезать v в конце, чтобы ссылка вела на само изображение
            $url = mb_substr($url, 0, -1);
        }

        return $url;
    }

    /**
     * Возвращает данные об изображении по URL.
     *
     * Если URL не является поддерживаемым адресом
     * изображения, то вернется null.
     *
     * @param string $url
     * @param CacheController $cache
     */
    public static function getInfoByUrl($url, $cache = null)
    {
        $url = trim($url);
        foreach (self::URL_PATTERNS as $pattern) {
            if (preg_match("/^" . $pattern . "$/i", $url)) {
                if ($pattern == self::PATTERN_OWNCLOUD) {
                    $type = OwncloudHelper::getSharedFileType($url, $cache);
                    if (empty($type) || strpos($type, 'image/') !== 0) {
                        return null;
                    }
                }

                $url = self::getDirectUrl($url);
                $previewUrl = $cache != null ? $cache->getImageCachedPreview($url) : null;

                return (object) compact('url', 'previewUrl');
            }
        }
        
        return null;
    }
}
