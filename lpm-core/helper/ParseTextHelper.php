<?php
/**
 * Класс, содержащий вспомогательные методы парсинга для текста.
 */
class ParseTextHelper {
	/**
	 * Получает ссылки на видео из текста.
	 * @param  string $html Текст (с ссылками на видео в формате html).
	 * @return array<{
	 *  type:string = youtube|video,
	 *  url:string
	 * ]>
	 */
	public static function parseVideoLinks($html) {
		$preg = [
            // YouTube
            "(?:youtube.)\w{2,4}\/(?:watch\?v=)",
            //Youtu.be
            ":?youtu.be",
            // Droplr
            "d.pr\/v\/",
            // Innim owncloud
            "cloud.innim.ru\/index.php\/s\/"
        ];

        $pattern = "/(" . implode("|", $preg) . ")(\S*)\"/";
        preg_match_all($pattern, $html, $video);
        #preg_match_all("/(?:youtube.)\w{2,4}\/(?:watch\?v=)(\S*)\"|(?:d.pr\/v\/)(\S*)\"/", $this->getDesc() , $video);
        $list = array();

        foreach ($video[0] as $key => $value) {
            $urlPrefix = $video[1][$key];
            $videoUid = $video[2][$key];
            $type = 'video';
            $url = null;
            $youtubeUid = reset(explode('&', $videoUid));

            if (strpos($urlPrefix, 'youtube') === 0) {
                // Это YouTube
                $type = 'youtube';
                $url = "http://www.youtube.com/embed/" .  $youtubeUid;
            } else if (strpos($urlPrefix, 'youtu.be') === 0) {
                // Это YouTu.be
                $type = 'youtube';
                $url = "http://www.youtube.com/embed/" . $videoUid;
            } else if (strpos($urlPrefix, 'd.pr') === 0) {
                // Это Droplr
                // $url = "http://d.pr/v/" . $videoUid . "+";
                $url = "http://" . $urlPrefix . $videoUid . "+";
            } else {
                // Для owncloud по формату ссылки не понятно, поэтому грузим заголовок
                $url = "https://" . $urlPrefix . $videoUid . "/download";
                $header = get_headers($url, 1);
                if (empty($header) || !isset($header['Content-Type']) ||
                    strpos($header['Content-Type'], 'video/') !== 0) {
                    $url = null;
                }
            }

            if (!empty($url))
                $list[] = (object) compact('type', 'url');
        }

        return $list;
	}
}