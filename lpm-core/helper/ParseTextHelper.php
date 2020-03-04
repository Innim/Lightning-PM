<?php
/**
 * Класс, содержащий вспомогательные методы парсинга для текста.
 */
class ParseTextHelper {
    const URL_PATTERN = "/(https?:\/\/[^<\s]+[[:alnum:]])([^[:alnum:]]*(?:<br ?\/?>)*[^a-zа-я0-9]|\s|$)/iu";

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

            if (strpos($urlPrefix, 'youtube') === 0) {
                $uidParts = explode('&', $videoUid);

                // Это YouTube
                $type = 'youtube';
                $url = "http://www.youtube.com/embed/" .  (!empty($uidParts) ? $uidParts[0] : '');
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

            if (!empty($url))
                $list[] = (object) compact('type', 'url');
        }

        return $list;
	}

    /**
     * Находит url ссылки в тексте и возвращает их список.
     * @param  string $text 
     * @return array<string>
     */
    public static function findLinks($text) {
        $matches = [];
        preg_match_all(self::URL_PATTERN, $text, $matches);
        return $matches[0];
    }

    /**
     * Находит ссылки на MR в тексте, получает для них данные и возвращает список данных MR.
     * @param  string $text 
     * @return array<GitlabMergeRequest>
     */
    public static function findMergeRequests($text) {
        $client = LightningEngine::getInstance()->gitlab();
        if (!$client->isAvailableForUser())
            return [];

        $links = self::findLinks($text);
        $res = [];
        foreach ($links as $url) {
            if ($client->isMRUrl($url)) {
                try {
                    $mr = $client->getMR($url);
                    if ($mr) $res[] = $mr;
                } catch (Gitlab\Exception\RuntimeException $e) {
                    // Игнорим если не найдено - можжет нет прав, может удалили, может url кривой
                    if ($e->getCode() != 404)
                        throw $e;
                }
            }
        }

        return $res;
    }
}