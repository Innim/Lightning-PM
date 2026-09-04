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

                $files[] = $this->getCompressStateData($file);
            }
        }

        $this->add2Answer('files', $files);

        return $this->answer();
    }

    /**
     * Ставит в очередь новую попытку сжатия видео, прошлая попытка которого
     * завершилась ошибкой.
     *
     * Действие доступно всем, кто вправе видеть файл: оригинал сохраняется
     * при любом исходе, а нагрузку на сервер ограничивает очередь. Файл, не
     * находящийся в состоянии ошибки, остаётся как есть — ответ в любом
     * случае содержит его актуальное состояние.
     *
     * @param string $uid uid файла
     * @return array Ответ с полем `file`: uid, статус сжатия, MIME-тип, имя
     *   файла и ссылки на просмотр и скачивание — состояние на момент ответа,
     *   а не итог этого вызова.
     */
    public function retryCompress($uid)
    {
        $file = LPMFile::loadByUid((string)$uid);
        if (!$file || $file->deleted || !$file->isVideo()) {
            return $this->error('Файл не найден');
        }

        if ($file->checkViewPermit($this->_auth->getUserId()) !== true) {
            return $this->error('Нет доступа к файлу');
        }

        VideoCompressor::retry($file->fileId);

        // Перечитываем запись: статус мог измениться и не этим вызовом
        // (тот же файл в очередь мог вернуть другой пользователь).
        $actual = LPMFile::load($file->fileId);

        $this->add2Answer('file', $this->getCompressStateData($actual ?: $file));

        return $this->answer();
    }

    /**
     * Состояние сжатия файла в виде, в котором его ждёт клиент.
     * @param  LPMFile $file
     * @return array
     */
    private function getCompressStateData(LPMFile $file)
    {
        return [
            'uid'            => $file->uid,
            'compressStatus' => $file->compressStatus === null ? null : (int)$file->compressStatus,
            'mimeType'       => $file->mimeType,
            'name'           => $file->origName,
            'url'            => $file->getViewUrl(),
            'downloadUrl'    => $file->getDownloadUrl(),
        ];
    }
}
