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

    /**
     * Возвращает информацию о видео по ссылке.
     * @param String $url URL, ссылающийся не видео.
     * Поддерживаются ссылки на YouTube,
     * Innim Cloud и Droplr.
     * @return [
     *  html: String // HTML код для вывода видео или null, если не видео не распознано
     * ]
     */
    public function getVideoInfo($url)
    {
        $res = AttachmentVideoHelper::getInfoByUrl($url);
        if (!empty($res)) {
            $html = $this->getHtml(function () use ($res) {
                PagePrinter::videoItem($res);
            });
            $this->extract2Answer($res);
            $this->add2Answer('html', $html);
        }
        
        return $this->answer();
    }

    /**
     * Возвращает информацию об изображении по ссылке.
     * @param String $url URL, по которому расшарено изобаржение.
     * Поддерживаются ссылки на
     * Innim Cloud, Droplr и GIF с imgur.
     * @return [
     *  html: String // HTML код для вывода видео или null, если не изображение не распознано
     * ]
     */
    public function getImageInfo($url)
    {
        $res = AttachmentImageHelper::getInfoByUrl($url);
        if (!empty($res)) {
            $html = $this->getHtml(function () use ($res) {
                PagePrinter::imageItem($res);
            });
            $this->extract2Answer($res);
            $this->add2Answer('html', $html);
        }
        
        return $this->answer();
    }
}
