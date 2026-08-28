<?php

/**
 * Менеджер для работы с комментариями.
 */
class CommentsManager
{
    const SECONDS_ON_COMMENT_DELETE = 600;

    /**
     * Публикация комментария к задаче.
     *
     * Упомянутые в тексте задачи автоматически связываются с текущей.
     *
     * @return Comment
     */
    public function postComment(
        User $user,
        Issue $issue,
        $text,
        $ignoreSlackNotification = false,
        $ignoreMr = false,
        string $type = null,
        string $data = null,
        array $filesData = null
    ) {
        $result = $this->postCommentWithResult(
            $user,
            $issue,
            $text,
            $ignoreSlackNotification,
            $ignoreMr,
            $type,
            $data,
            $filesData
        );

        return $result['comment'];
    }

    /**
     * Публикация комментария к задаче с данными об изменениях, которые она повлекла.
     *
     * Упомянутые в тексте задачи автоматически связываются с текущей.
     *
     * @return array {
     *     Comment comment    Добавленный комментарий.
     *     int     addedLinks Количество созданных связей с упомянутыми задачами.
     * }
     */
    public function postCommentWithResult(
        User $user,
        Issue $issue,
        $text,
        $ignoreSlackNotification = false,
        $ignoreMr = false,
        string $type = null,
        string $data = null,
        array $filesData = null
    ) {
        $issueId = $issue->id;
        $hasUploads = $filesData !== null && FileUploadManager::hasUploads($filesData);
        if (!$comment = $this->addComment($user, LPMInstanceTypes::ISSUE, $issueId, $text, $hasUploads)) {
            throw new Exception("Не удалось добавить комментарий");
        }
        $comment->issue = $issue;

        if ($filesData !== null) {
            $result = FileUploadManager::upload(
                LPMInstanceTypes::COMMENT,
                $comment->id,
                $user->userId,
                $filesData,
                Comment::MAX_FILES_COUNT,
                Comment::MAX_FILES_COUNT
            );

            if (!empty($result['errors'])) {
                Comment::discard($comment);
                throw new Exception($result['errors'][0]);
            }

            $comment->setFiles($result['uploaded']);
        } else {
            $comment->setFiles([]);
        }

        UserLogEntry::create(
            $user->userId,
            DateTimeUtils::$currentDate,
            UserLogEntryType::ADD_COMMENT,
            $comment->id
        );

        $memberIds = $issue->getMemberIds();
        if (!$ignoreMr && in_array($comment->authorId, $memberIds)) {
            // Если коммент оставил исполнитель, то будем искать MR в нем и запишем их в БД
            $mrList = $comment->getMergeRequests();
            if (!empty($mrList)) {
                foreach ($mrList as $mr) {
                    IssueMR::createByMr($issue->id, $mr);
                }

                if (empty($type)) {
                    $type = IssueCommentType::MERGE_REQUEST;
                }
            }
        }

        if (!empty($type)) {
            $comment->issueComment = IssueComment::create($comment->id, $type, $data);
        }

        // отправка оповещений
        if (!$ignoreSlackNotification) {
            // TODO: учесть тип request_changes - особое оповещение
            $notifiedIds = $this->slackNotificationCommentTesterOrMembers($issue, $comment);
            $this->slackNotificationMentionedUsers($issue, $comment, $notifiedIds);
        }

        Issue::notifyByEmail(
            $issue,
            IssueEmailFormatter::newCommentSubject($issue),
            IssueEmailFormatter::newCommentText($comment, $issue, $user),
            EmailNotifier::PREF_ISSUE_COMMENT
        );

        // обновляем счетчик комментариев для задачи
        Issue::updateCommentsCounter($issueId);

        // Связи по упоминаниям создаём здесь: через этот метод проходят все способы
        // добавить комментарий (веб, внешний API, хуки GitLab)
        $addedLinks = IssueLinked::syncFromText($issue, $text, $user->userId);

        Comment::setTimeToDeleteComment($comment, self::SECONDS_ON_COMMENT_DELETE);

        return ['comment' => $comment, 'addedLinks' => $addedLinks];
    }
    
    /**
     * @return Comment
     */
    protected function addComment(User $user, $instanceType, $instanceId, $text, $allowEmpty = false)
    {
        $text = Comment::normalizeText($text, $allowEmpty);

        $comment = Comment::add($instanceType, $instanceId, $user->userId, $text);
        if ($comment) {
            $comment->author = $user;
        }

        return $comment;
    }

    /**
     * @return int[] Идентификаторы пользователей, которым отправлено оповещение.
     */
    private function slackNotificationCommentTesterOrMembers(Issue $issue, Comment $comment)
    {
        if ($issue->status == Issue::STATUS_WAIT) {
            $testerIssue = $issue->getTesterIds();
            $membersIssue = $issue->getMemberIds();
            $userSendMessage = $comment->author->getID();
            $slack = SlackIntegration::getInstance();

            if (in_array($userSendMessage, $testerIssue)) {
                $slack->notifyCommentTesterToMember($issue, $comment);
                return $membersIssue;
            } elseif (in_array($userSendMessage, $membersIssue)) {
                $slack->notifyCommentMemberToTester($issue, $comment);
                return $testerIssue;
            }
        }

        return [];
    }

    /**
     * @param int[] $excludeUserIds Идентификаторы пользователей, которых не надо
     *                              оповещать (например, уже получивших оповещение).
     */
    private function slackNotificationMentionedUsers(Issue $issue, Comment $comment, array $excludeUserIds = [])
    {
        $userIds = UserMentionHelper::extractMentionedUserIds($comment->text);
        if (empty($userIds)) {
            return;
        }

        // оповещаем только участников проекта (без заблокированных): в тексте
        // может быть указан произвольный id, но пинговать посторонних не нужно
        $userIds = array_intersect($userIds, $issue->getProject()->getMemberIds(true));

        // не оповещаем автора комментария и тех, кому оповещение уже отправлено
        $userIds = array_diff($userIds, array_merge([$comment->authorId], $excludeUserIds));
        if (empty($userIds)) {
            return;
        }

        $users = [];
        foreach ($userIds as $userId) {
            $user = User::load($userId);
            if (!empty($user)) {
                $users[] = $user;
            }
        }

        if (!empty($users)) {
            SlackIntegration::getInstance()->notifyCommentMentioned($issue, $comment, $users);
        }
    }
}
