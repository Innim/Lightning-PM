<?php
class IssueEmailFormatter
{
    const PARAM_USER = '{user}';
    const PARAM_ISSUE = '{issue}';
    const PARAM_PROJECT = '{project}';

    /**
     * Форматирует сообщение для отправки по email при изменении задачи.
     */
    private static function formatText($text, Issue $issue, $user, $issueLinkLabel)
    {
        $project = $issue->getProject();
        $message = self::format(
            $text,
            compact('project', 'issue', 'user'),
        );

        if ($issueLinkLabel !== null) {
            $message .= "\n\n" . $issueLinkLabel . ' ' .	$issue->getConstURL();
        }

        return $message;
    }

    private static function formatSubject($subject, Issue $issue)
    {
        $project = $issue->getProject();
        $res = self::format(
            '{project}: ' . $subject,
            compact('project', 'issue'),
        );

        return $res;
    }
    
    private static function format($text, $params)
    {
        extract($params);
        $message = $text;

        if (!empty($issue)) {
            $message = str_replace(self::PARAM_ISSUE, $issue->getName(), $message);
        }

        if (!empty($user)) {
            $message = str_replace(self::PARAM_USER, $user->getName(), $message);
        }

        if (!empty($project)) {
            $message = str_replace(self::PARAM_PROJECT, $project->name, $message);
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

    public static function issueAddedSubject(Issue $issue)
    {
        $subject = 'Добавлена задача "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    public static function issueAddedText(Issue $issue, User $user)
    {
        $text = '{user} добавил задачу "{issue}" в проекте "{project}".';
        return self::formatTextDefault($text, $issue, $user);
    }

    public static function issueChangedSubject(Issue $issue)
    {
        $subject = 'Изменена задача "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    public static function issueChangedText(Issue $issue, User $user)
    {
        $text = '{user} изменил задачу "{issue}" в проекте "{project}".';
        return self::formatTextDefault($text, $issue, $user);
    }

    public static function issueDeletedSubject(Issue $issue)
    {
        $subject = 'Удалена задача "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    public static function issueDeletedText(Issue $issue, User $user)
    {
        $text = '{user} удалил задачу "{issue}" в проекте "{project}".';
        return self::formatText($text, $issue, $user, null);
    }

    public static function issueCompletedSubject(Issue $issue)
    {
        $subject = 'Завершена задача "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueCompletedText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" проекта "{project}" завершена.' 
            : '{user} отметил задачу "{issue}" в проекте "{project}" как завершённую.';

        return self::formatTextDefault($text, $issue, $user);
    }

    public static function issueReopenedSubject(Issue $issue)
    {
        $subject = 'Открыта задача "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueReopenedText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" проекта "{project}" снова открыта.' 
            : '{user} заново открыл задачу "{issue}" в проекте "{project}".';

        return self::formatTextDefault($text, $issue, $user);
    }


    public static function issueSendForTestSubject(Issue $issue)
    {
        $subject = 'Задача "{issue}" ожидает проверки';
        return self::formatSubject($subject, $issue);
    }

    /**
     * @param Issue $issue
     * @param User|null $user
     * @return string
     */
    public static function issueSendForTestText(Issue $issue, $user)
    {
        $text = empty($user) 
            ? 'Задача "{issue}" проекта "{project}" отправлена на проверку.' 
            : '{user} отправил задачу "{issue}" в проекте "{project}" на проверку.';

        return self::formatTextDefault($text, $issue, $user);
    }

    public static function newCommentSubject(Issue $issue)
    {
        $subject = 'Новый комментарий к задаче "{issue}"';
        return self::formatSubject($subject, $issue);
    }

    public static function newCommentText(Comment $comment, Issue $issue, User $user)
    {
        $text = '{user} оставил комментарий к задаче "{issue}" проекта "{project}": ' . "\n";
        $text .= $comment->getCleanText();
        return self::formatText($text, $issue, $user, 'Просмотреть все комментарии к задаче можно по ссылке');
    }
}