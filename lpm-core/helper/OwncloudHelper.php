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
     * @return string Content-Type
     */
    public static function getSharedFileType($url)
    {
        $url = $url . "/download";
        $prev = stream_context_get_options(stream_context_get_default());
        // Устаналиваем таймаут поменьше
        stream_context_set_default([
                'http' => [
                    'timeout' => 2, // seconds
                ]
            ]);
        $header = @get_headers($url, 1);
        stream_context_set_default($prev);

        if (empty($header) || !isset($header['Content-Type'])) {
            return null;
        } else {
            return $header['Content-Type'];
        }
    }
}
