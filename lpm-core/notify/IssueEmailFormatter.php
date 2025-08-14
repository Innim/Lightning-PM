<?php
class IssueEmailFormatter
{
    const PARAM_USER = '{user}';
    const PARAM_ISSUE = '{issue}';

    /**
     * Форматирует сообщение для отправки по email при изменении задачи.
     *
     * @param Issue $issue
     * @param string $action
     * @return string
     */
    private static function formatText($text, Issue $issue, $user, $issueLinkLabel)
    {
        $message = str_replace(self::PARAM_ISSUE, $issue->getName(), $text);

        if (!empty($user)) {
            $message = str_replace(self::PARAM_USER, $user->getName(), $message);
        }


        if ($issueLinkLabel !== null) {
            $message .= "\n\n" . $issueLinkLabel . ' ' .	$issue->getConstURL();
        }

        return $message;
    }

    private static function formatTextDefault($text, Issue $issue, $user) {
        return self::formatText(
            $text,
            $issue,
            $user,
            'Просмотреть задачу можно по ссылке'
        );
    }

    public static function issueAddedText(Issue $issue, User $user)
    {
        $text = '{user} добавил задачу "{issue}".';
        return self::formatTextDefault($text, $issue, $user);
    }

    public static function issueChangedText(Issue $issue, User $user)
    {
        $text = '{user} изменил задачу "{issue}".';
        return self::formatTextDefault($text, $issue, $user);
    }

    public static function issueDeletedText(Issue $issue, User $user)
    {
        $text = '{user} удалил задачу "{issue}".';
        return self::formatText($text, $issue, $user, null);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueCompletedText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" завершена.' 
            : '{user} отметил задачу "{issue}" как завершённую.';

        return self::formatTextDefault($text, $issue, $user);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueReopenedText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" снова открыта.' 
            : '{user} заново открыл задачу "{issue}".';

        return self::formatTextDefault($text, $issue, $user);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueSendForTestText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" отправлена на проверку.' 
            : '{user} отправил задачу "{issue}" на проверку.';

        return self::formatTextDefault($text, $issue, $user);
    }

    public static function newCommentText(Comment $comment, Issue $issue, User $user)
    {
        $text = '{user} оставил комментарий к задаче "{issue}": ' . "\n";
        $text .= $comment->getCleanText();
        return self::formatText($text, $issue, $user, 'Просмотреть все комментарии к задаче можно по ссылке');
    }
}