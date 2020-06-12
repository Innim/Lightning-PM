<?php
require_once __DIR__ . '/../init.inc.php';

/**
 * Сервис, предоставляющий данные для вложений.
 *
 * В качестве вложений могут выступать
 * медиа-файлы (видео, картинки), merge request'ы
 * и так далее.
 */
class AttachmentsService extends LPMBaseService
{
    /**
     * Возвращает информацию о Merge Request по URL.
     * @param  String $url URL merge request'а
     * @return
     */
    public function getMRInfo($url)
    {
        $data = null;
        $client = LightningEngine::getInstance()->gitlab();
        if ($client->isAvailableForUser()) {
            try {
                $data = $client->getMR($url);
            } catch (Gitlab\Exception\RuntimeException $e) {
                // Игнорим если не найдено - может нет прав, может удалили, может url кривой
                if ($e->getCode() != 404) {
                    return $this->exception($e);
                }
            }
        }

        $this->add2Answer('data', $data);

        return $this->answer();
    }
}
