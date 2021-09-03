<?php
/**
 * Вспомогательный класс для взаимодействия с owncloud.
 */
class OwncloudHelper
{
    /**
     * Возвращает тип расшаренного файла.
     *
     * Если не удалось получить тип или $url не доступен,
     * то вернется null.
     * @param string $url
     * @param CacheController $cache
     * @return string Content-Type
     */
    public static function getSharedFileType($url, $cache = null)
    {
        if ($cache != null) {
            $cachedVal = $cache->getOwncloudSharedFileType($url);
            if ($cachedVal !== false) {
                return $cachedVal;
            }
        }
        
        $downloadUrl = $url . "/download";
        $prev = stream_context_get_options(stream_context_get_default());
        // Устанавливаем таймаут поменьше
        stream_context_set_default([
                'http' => [
                    'timeout' => 2, // seconds
                ]
            ]);
        $header = @get_headers($downloadUrl, 1);
        stream_context_set_default($prev);

        if (empty($header) || !isset($header['Content-Type'])) {
            return null;
        } else {
            $type = $header['Content-Type'];
            if ($cache != null) {
                $cachedVal = $cache->setOwncloudSharedFileType($url, $type);
            }
            return $type;
        }
    }
}
