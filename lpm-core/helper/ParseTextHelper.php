<?php
/**
 * Класс, содержащий вспомогательные методы парсинга для текста.
 */
class ParseTextHelper
{
    const URL_PATTERN = "/(https?:\/\/[^<\s]+[[:alnum:]])([^[:alnum:]]*(?:<br ?\/?>)*[^a-zа-я0-9]|\s|$)/iu";

    /**
     * Получает ссылки на видео из текста.
     * @param  string $html Текст (с ссылками на видео в формате html).
     * @return array<{
     *  type:string = youtube|video,
     *  url:string
     * ]>
     */
    public static function parseVideoLinks($html)
    {
        $pattern = AttachmentVideoHelper::getPattern(null, '"');
        return AttachmentVideoHelper::getVideoWith($html, $pattern);
    }

    /**
     * Находит url ссылки в тексте и возвращает их список.
     * @param  string $text
     * @return array<string>
     */
    public static function findLinks($text)
    {
        $matches = [];
        preg_match_all(self::URL_PATTERN, $text, $matches);
        return $matches[0];
    }

    /**
     * Находит ссылки на MR в тексте, получает для них данные и возвращает список данных MR.
     * @param  string $text
     * @return array<GitlabMergeRequest>
     */
    public static function findMergeRequests($text)
    {
        $client = LightningEngine::getInstance()->gitlab();
        if (!$client->isAvailableForUser()) {
            return [];
        }

        $links = self::findLinks($text);
        $res = [];
        foreach ($links as $url) {
            if ($client->isMRUrl($url)) {
                try {
                    $mr = $client->getMR($url);
                    if ($mr) {
                        $res[] = $mr;
                    }
                } catch (Gitlab\Exception\RuntimeException $e) {
                    // Игнорим если не найдено - можжет нет прав, может удалили, может url кривой
                    if ($e->getCode() != 404) {
                        throw $e;
                    }
                }
            }
        }

        return $res;
    }
}
