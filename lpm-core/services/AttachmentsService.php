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
     * Сколько секунд после влития MR его состояние не выправляется
     * по живым данным GitLab.
     *
     * Вебхук о влитии опознаёт задачи, которым надо оповестить тестировщика
     * и которые пора переводить в тест, по сохранённому состоянию `opened`
     * (см. IssueMR::loadIssueIdsForOpenedMr()). Если выправить состояние
     * раньше, чем вебхук дойдёт, эти действия не выполнятся. Вебхук приходит
     * сразу, а неверное состояние живёт неограниченно долго, поэтому выждать
     * час достаточно, чтобы чинить только по-настоящему потерянные события.
     */
    const MERGED_SYNC_DELAY_SEC = 3600;

    /**
     * Возвращает информацию о Merge Request по URL.
     *
     * Побочный эффект: полученное от GitLab состояние MR сохраняется
     * в привязках MR к задачам (см. {@see self::syncMRState()}).
     *
     * @param  String $url URL merge request'а
     * @return
     */
    public function getMRInfo($url)
    {
        $data = null;
        if ($client = $this->getGitlabIfAvailable()) {
            try {
                $data = $client->getMR($url);
            } catch (Gitlab\Exception\RuntimeException $e) {
                // Игнорируем если не найдено - может нет прав, может удалили, может url кривой
                if ($e->getCode() != 404) {
                    return $this->exception($e);
                }
            }

            if (!empty($data)) {
                $this->syncMRState($data);
            }
        }

        $this->add2Answer('data', $data);

        return $this->answer();
    }

    /**
     * Сохраняет актуальное состояние MR в привязках MR к задачам.
     *
     * Единственный штатный источник состояния — вебхук GitLab, и потерянное
     * событие иначе оставляет состояние в БД неверным навсегда. Здесь оно
     * выправляется по уже полученным живым данным, без отдельного запроса
     * к GitLab.
     *
     * Только что влитый MR пропускается, чтобы не перебить вебхук
     * (см. self::MERGED_SYNC_DELAY_SEC).
     *
     * Сбой синхронизации не должен ломать выдачу данных о MR, поэтому
     * ошибки только логируются.
     *
     * @param GitlabMergeRequest $mr Актуальные данные merge request'а.
     */
    private function syncMRState(GitlabMergeRequest $mr)
    {
        if (empty($mr->id) || empty($mr->state)) {
            return;
        }

        if ($mr->isMerged() && !$this->isMergeSettled($mr)) {
            return;
        }

        try {
            if (IssueMR::syncState($mr->id, $mr->state)) {
                LPMLog::info('MR state restored from live data', LPMLog::CH_GITLAB, [
                    'mrId'  => $mr->id,
                    'state' => $mr->state,
                ]);
            }
        } catch (Exception $e) {
            LPMLog::exception($e, LPMLog::CH_GITLAB, ['mrId' => $mr->id]);
        }
    }

    /**
     * Определяет, прошло ли после влития MR достаточно времени, чтобы вебхук
     * о влитии уже точно был доставлен или потерян.
     *
     * @param GitlabMergeRequest $mr Данные влитого merge request'а.
     * @return bool
     */
    private function isMergeSettled(GitlabMergeRequest $mr)
    {
        // Свежевлитый MR всегда отдаёт дату влития, поэтому MR без неё
        // считаем давним, а не только что влитым.
        if (empty($mr->mergedAt) || $mr->mergedAt->isUndefined()) {
            return true;
        }

        // Дата влития — абсолютный момент времени из GitLab, поэтому сравнивать
        // её надо с реальным временем, а не со сдвинутым на TIMEADJUST.
        $passed = time() - $mr->mergedAt->getUnixtime();

        return $passed >= self::MERGED_SYNC_DELAY_SEC;
    }

    /**
     * Возвращает информацию о Pipeline по URL.
     * @param  String $url URL pipeline'а
     * @return
     */
    public function getPipelineInfo($url)
    {
        $data = null;
        if ($client = $this->getGitlabIfAvailable()) {
            try {
                $data = $client->getPipeline($url);
            } catch (Gitlab\Exception\RuntimeException $e) {
                // Игнорируем если не найдено - может нет прав, может удалили, может url кривой
                if ($e->getCode() != 404) {
                    return $this->exception($e);
                }
            }
        }

        $this->add2Answer('data', $data);

        return $this->answer();
    }

    /**
     * Возвращает информацию о Job по URL.
     * @param  String $url URL джобы
     * @return
     */
    public function getJobInfo($url)
    {
        $data = null;
        if ($client = $this->getGitlabIfAvailable()) {
            try {
                $data = $client->getJob($url);
            } catch (Gitlab\Exception\RuntimeException $e) {
                // Игнорируем если не найдено - может нет прав, может удалили, может url кривой
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
        $res = AttachmentVideoHelper::getInfoByUrl($url, $this->cache());
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
     * @param String $url URL, по которому расшарено изображение.
     * Поддерживаются ссылки на
     * Innim Cloud, Droplr и GIF с imgur.
     * @return [
     *  html: String // HTML код для вывода видео или null, если не изображение не распознано
     * ]
     */
    public function getImageInfo($url)
    {
        $res = AttachmentImageHelper::getInfoByUrl($url, $this->cache());
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
