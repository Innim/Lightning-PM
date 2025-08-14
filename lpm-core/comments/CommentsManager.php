<?php

/**
 * Менеджер для работы с комментариями.
 */
class CommentsManager
{
    const SECONDS_ON_COMMENT_DELETE = 600;

    /**
     * Публикация комментария к задаче.
     * @return Comment
     */
    public function postComment(
        User $user,
        Issue $issue,
        $text,
        $ignoreSlackNotification = false,
        $ignoreMr = false,
        string $type = null,
        string $data = null
    ) {
        $issueId = $issue->id;
        if (!$comment = $this->addComment($user, LPMInstanceTypes::ISSUE, $issueId, $text)) {
            throw new Exception("Не удалось добавить комментарий");
        }
        $comment->issue = $issue;

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
            $this->slackNotificationCommentTesterOrMembers($issue, $comment);
        }

        Issue::notifyByEmail(
            $issue,
            'Новый комментарий к задаче "' . $issue->name . '"',
            IssueEmailFormatter::newCommentText($comment, $issue, $user),
            EmailNotifier::PREF_ISSUE_COMMENT
        );

        // обновляем счетчик комментариев для задачи
        Issue::updateCommentsCounter($issueId);

        Comment::setTimeToDeleteComment($comment, self::SECONDS_ON_COMMENT_DELETE);

        return $comment;
    }
    
    /**
     * @return Comment
     */
    protected function addComment(User $user, $instanceType, $instanceId, $text)
    {
        // TODO: перенести в Comment
        $text = trim($text);
        if ($text == '') {
            throw new Exception('Недопустимый текст');
        }

        $comment = Comment::add($instanceType, $instanceId, $user->userId, $text);
        if ($comment) {
            $comment->author = $user;
            // Записываем лог
            UserLogEntry::create(
                $user->userId,
                DateTimeUtils::$currentDate,
                UserLogEntryType::ADD_COMMENT,
                $comment->id
            );
        }

        return $comment;
    }

    private function slackNotificationCommentTesterOrMembers(Issue $issue, Comment $comment)
    {
        if ($issue->status == Issue::STATUS_WAIT) {
            $testerIssue = $issue->getTesterIds();
            $membersIssue = $issue->getMemberIds();
            $userSendMessage = $comment->author->getID();
            $slack = SlackIntegration::getInstance();

            if (in_array($userSendMessage, $testerIssue)) {
                $slack->notifyCommentTesterToMember($issue, $comment);
            } elseif (in_array($userSendMessage, $membersIssue)) {
                $slack->notifyCommentMemberToTester($issue, $comment);
            }
        }
    }
}
