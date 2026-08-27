<?php
require_once __DIR__ . '/../init.inc.php';

/**
 * Сервис для работы с загруженными файлами.
 */
class FilesService extends LPMBaseService
{
    /**
     * Возвращает статус фонового сжатия для указанных файлов.
     *
     * Используется фронтендом для опроса состояния видео, которые
     * сжимаются в фоне: как только сжатие завершается, клиент подменяет
     * заглушку на плеер.
     *
     * @param array $uids Список uid файлов
     * @return
     */
    public function getCompressStatus($uids)
    {
        // Опрос идёт, пока на странице висит заглушка сжатия, — то есть ровно
        // тогда, когда есть кому заметить застрявшую очередь. Это единственный
        // регулярный вызов, из которого можно освободить слоты воркеров,
        // убитых без шанса прибраться за собой (OOM, SIGKILL).
        VideoCompressor::dispatchOnPoll();

        $files = [];
        $userId = $this->_auth->getUserId();

        if (is_array($uids)) {
            foreach ($uids as $uid) {
                $file = LPMFile::loadByUid((string)$uid);
                if (!$file || $file->deleted || !$file->isVideo()) {
                    continue;
                }

                // Тот же контроль доступа, что и при скачивании файла:
                // отдаём статус только если пользователь вправе видеть
                // связанную задачу/комментарий.
                if ($file->checkViewPermit($userId) !== true) {
                    continue;
                }

                $files[] = [
                    'uid'            => $file->uid,
                    'compressStatus' => $file->compressStatus === null ? null : (int)$file->compressStatus,
                    'mimeType'       => $file->mimeType,
                    'name'           => $file->origName,
                    'url'            => $file->getViewUrl(),
                    'downloadUrl'    => $file->getDownloadUrl(),
                ];
            }
        }

        $this->add2Answer('files', $files);

        return $this->answer();
    }
}
