<?php
/**
 * Вспомогательные методы для работы с видео вложениями.
 */
class AttachmentVideoHelper
{
    const URL_PATTERNS = [
        // YouTube
        "(?:youtube.)\w{2,4}\/(?:watch\?v=)",
        //Youtu.be
        ":?youtu.be",
        // Droplr
        "d.pr\/v\/",
        // Innim owncloud
        "cloud.innim.ru\/index.php\/s\/"
    ];

    /**
     * Возвращает данные о видео по URL.
     *
     * Если URL не является поддреживаемым адресом
     * видео, то вернется null.
     */
    public static function getInfoByUrl($url)
    {
        $pattern = self::getPattern();
        $list = self::getVideoWith($url, $pattern);
        return empty($list) ? null : $list[0];
    }

    public static function getPattern($prefix = null, $suffix = null)
    {
        $pattern = "/";

        if (!empty($prefix)) {
            $pattern .= $prefix;
        }

        $pattern .= "(" . implode("|", self::URL_PATTERNS) . ")(\S*)";

        if (!empty($suffix)) {
            $pattern .= $suffix;
        }
        
        $pattern .= "/";
        return $pattern;
    }

    public static function getVideoWith($text, $pattern)
    {
        preg_match_all($pattern, $text, $match);

        $list = [];
        foreach ($match[0] as $key => $value) {
            $urlPrefix = $match[1][$key];
            $videoUid = $match[2][$key];

            $data = self::getInfoBy($urlPrefix, $videoUid);
            if ($data !== null) {
                $list[] = $data;
            }
        }

        return $list;
    }

    private static function getInfoBy($urlPrefix, $videoUid)
    {
        $type = 'video';
        $url = null;

        if (strpos($urlPrefix, 'youtube') === 0) {
            $uidParts = explode('&', $videoUid);

            // Это YouTube
            $type = 'youtube';
            $url = "http://www.youtube.com/embed/" .  (!empty($uidParts) ? $uidParts[0] : '');
        } elseif (strpos($urlPrefix, 'youtu.be') === 0) {
            // Это YouTu.be
            $type = 'youtube';
            $url = "http://www.youtube.com/embed/" . $videoUid;
        } elseif (strpos($urlPrefix, 'd.pr') === 0) {
            // Это Droplr
            // $url = "http://d.pr/v/" . $videoUid . "+";
            $url = "http://" . $urlPrefix . $videoUid . "+";
        } else {
            // Для owncloud по формату ссылки не понятно, поэтому грузим заголовок
            $url = "https://" . $urlPrefix . $videoUid . "/download";
            $prev = stream_context_get_options(stream_context_get_default());
            // Устаналиваем таймаут поменьше
            // TODO: вообще желательно бы перенести на клиент
            stream_context_set_default([
                    'http' => [
                        'timeout' => 2, // seconds
                    ]
                ]);
            $header = @get_headers($url, 1);
            stream_context_set_default($prev);

            if (empty($header) || !isset($header['Content-Type']) ||
                    strpos($header['Content-Type'], 'video/') !== 0) {
                $url = null;
            }
        }

        return empty($url) ? null : (object) compact('type', 'url');
    }
}
