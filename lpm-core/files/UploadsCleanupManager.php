<?php

/**
 * Удаление вложений вместе с сущностью, к которой они были прикреплены.
 *
 * Удаление задачи и комментария — мягкое, но ни один загрузчик удалённую
 * запись больше не отдаёт и вернуть её через интерфейс нельзя, поэтому
 * вложения такой записи хранить незачем: записи вложений помечаются
 * удалёнными, а файлы стираются с диска.
 *
 * Файл, привязанный ещё к одной сущности, с диска не удаляется — снимается
 * только связь с удаляемой (см. {@see LPMFile::delete()}).
 */
class UploadsCleanupManager
{
    /**
     * Удаляет вложения задачи: её файлы, её изображения и файлы всех
     * её комментариев (включая ранее удалённые).
     *
     * Ошибка удаления вложений не пробрасывается наружу, а пишется в лог:
     * незачищенное вложение не должно мешать удалить саму задачу.
     * @param int $issueId
     */
    public static function removeIssueUploads($issueId)
    {
        $issueId = (int)$issueId;
        if ($issueId <= 0) {
            return;
        }

        try {
            LPMFile::deleteAllByInstance(LPMInstanceTypes::ISSUE, $issueId);
            LPMImg::removeAllByInstance(LPMInstanceTypes::ISSUE, $issueId);

            foreach (Comment::loadIdsByInstance(LPMInstanceTypes::ISSUE, $issueId) as $commentId) {
                LPMFile::deleteAllByInstance(LPMInstanceTypes::COMMENT, $commentId);
            }
        } catch (\Exception $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['issueId' => $issueId]);
        }
    }

    /**
     * Удаляет вложения комментария.
     *
     * Ошибка удаления вложений не пробрасывается наружу, а пишется в лог:
     * незачищенное вложение не должно мешать удалить сам комментарий.
     * @param int $commentId
     */
    public static function removeCommentUploads($commentId)
    {
        $commentId = (int)$commentId;
        if ($commentId <= 0) {
            return;
        }

        try {
            LPMFile::deleteAllByInstance(LPMInstanceTypes::COMMENT, $commentId);
        } catch (\Exception $e) {
            LPMLog::exception($e, LPMLog::CH_APP, ['commentId' => $commentId]);
        }
    }
}
