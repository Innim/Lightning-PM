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
     * Если URL не является поддерживаемым адресом
     * видео, то вернется null.
     * @param string $url
     * @param CacheController $cache
     */
    public static function getInfoByUrl($url, $cache = null)
    {
        $pattern = self::getPattern();
        $list = self::getVideoWith($url, $pattern, $cache);
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

    /**
     * @param string $text
     * @param string $pattern
     * @param CacheController $cache
     */
    public static function getVideoWith($text, $pattern, $cache = null)
    {
        preg_match_all($pattern, $text, $match);

        $list = [];
        foreach ($match[0] as $key => $value) {
            $urlPrefix = $match[1][$key];
            $videoUid = $match[2][$key];

            $data = self::getInfoBy($urlPrefix, $videoUid, $cache);
            if ($data !== null) {
                $list[] = $data;
            }
        }

        return $list;
    }

    /**
     * @param string $urlPrefix
     * @param string $videoUid
     * @param CacheController $cache
     */
    private static function getInfoBy($urlPrefix, $videoUid, $cache = null)
    {
        $type = 'video';
        $url = null;

        if (strpos($urlPrefix, 'youtube') === 0) {
            $uidParts = explode('&', $videoUid);

            // Это YouTube
            $type = 'youtube';
            $url = "https://www.youtube.com/embed/" .  (!empty($uidParts) ? $uidParts[0] : '');
        } elseif (strpos($urlPrefix, 'youtu.be') === 0) {
            // Это YouTu.be
            $type = 'youtube';
            $url = "https://www.youtube.com/embed/" . $videoUid;
        } elseif (strpos($urlPrefix, 'd.pr') === 0) {
            // Это Droplr
            // $url = "http://d.pr/v/" . $videoUid . "+";
            $url = "https://" . $urlPrefix . $videoUid . "+";
        } else {
            // Для owncloud по формату ссылки не понятно, поэтому грузим заголовок
            $original = "https://" . $urlPrefix . $videoUid;
            $type = OwncloudHelper::getSharedFileType($original, $cache);

            if (empty($type) || strpos($type, 'video/') !== 0) {
                $url = null;
            } else {
                $url = $original . "/download";
            }
        }

        return empty($url) ? null : (object) compact('type', 'url');
    }
}
