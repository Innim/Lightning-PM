<?php
/**
 * Сопоставляет характеристики задачи с её оформлением, чтобы шаблоны
 * не содержали логики выбора классов.
 *
 * Те же соответствия продублированы в `lpm-scripts/issues.js` (объект Issue) —
 * они нужны для обновления вида задачи без перезагрузки страницы.
 */
class IssueViewHelper
{
    /** Осталось меньше этого числа дней — срок считается срочным. */
    const DEADLINE_URGENT_DAYS = 2;

    /** Осталось меньше этого числа дней — срок считается близким. */
    const DEADLINE_SOON_DAYS = 7;

    /**
     * Насколько поджимает срок выполнения задачи.
     * @param  Issue $issue Задача.
     * @return string Уровень: outdated|urgent|medium|low. Пустая строка, если
     * срок не задан или задача уже завершена — тогда подсвечивать нечего.
     */
    public static function deadlineLevel(Issue $issue)
    {
        if ($issue->isCompleted() || !$issue->hasCompleteDate()) {
            return '';
        }

        $days = $issue->daysTillComplete();
        if ($days < 0) {
            return 'outdated';
        }
        if ($days < self::DEADLINE_URGENT_DAYS) {
            return 'urgent';
        }
        if ($days < self::DEADLINE_SOON_DAYS) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Готовность задачи в тесте: влиты ли правки, которые её изменили.
     *
     * Определяется только для задач в тесте, по которым известно состояние MR.
     * Пока задача ждёт правок (найдены проблемы), проверяется кем-то прямо
     * сейчас или отмечена прошедшей тестирование, состояние MR
     * не показывается - у задачи есть отметка поважнее.
     * @param  Issue $issue Задача.
     * @return string Уровень: wait-merge (правки ещё не влиты) |
     * ready (правки влиты, можно тестировать). Пустая строка, если показывать нечего.
     */
    public static function testMergeLevel(Issue $issue)
    {
        if (!$issue->isTesting() || $issue->hasPassTestMark
            || $issue->isChangesRequested || $issue->isUnderTesting
        ) {
            return '';
        }

        switch ($issue->testMrState) {
            case GitlabMergeRequest::STATE_OPENED:
            case GitlabMergeRequest::STATE_LOCKED:
                return 'wait-merge';
            case GitlabMergeRequest::STATE_MERGED:
                return 'ready';
            default:
                return '';
        }
    }

    /**
     * Классы бейджа срока выполнения.
     * @param  string $level Уровень из deadlineLevel().
     * @return string Список CSS-классов.
     */
    public static function deadlineBadgeClass($level)
    {
        switch ($level) {
            case 'outdated':
                return 'bg-danger';
            case 'urgent':
            case 'medium':
                return 'bg-warning text-dark';
            default:
                return 'bg-white text-dark border';
        }
    }

    /**
     * Классы иконки срока выполнения.
     * @param  string $level Уровень из deadlineLevel().
     * @return string Список CSS-классов FontAwesome.
     */
    public static function deadlineIconClass($level)
    {
        switch ($level) {
            case 'outdated':
                return 'fa-solid fa-calendar-xmark';
            case 'urgent':
                return 'fa-solid fa-fire';
            case 'medium':
                return 'fa-solid fa-calendar-day';
            default:
                return 'fa-regular fa-calendar-check';
        }
    }

    /**
     * Подсказка о том, сколько осталось до срока выполнения задачи.
     * @param  Issue $issue Задача.
     * @return string Текст подсказки. Пустая строка, если срок не задан
     * или задача уже завершена.
     */
    public static function deadlineHint(Issue $issue)
    {
        if (self::deadlineLevel($issue) === '') {
            return '';
        }

        $days = (int)floor($issue->daysTillComplete());
        if ($days < 0) {
            return 'Просрочена на ' . abs($days) . ' дн.';
        }
        if ($days === 0) {
            return 'Срок сегодня';
        }

        return 'Осталось ' . $days . ' дн.';
    }

    /**
     * Отображаемое название статуса задачи.
     * @param  int $status    Статус задачи.
     * @param  int $substatus Уточнение статуса (@see IssueSubstatus).
     * @return string Название подстатуса, если он есть, иначе название статуса.
     */
    public static function statusLabel($status, $substatus = IssueSubstatus::NONE)
    {
        $name = IssueSubstatus::getName($substatus);
        return $name === '' ? Issue::getStatusName($status) : $name;
    }

    /**
     * Классы бейджа статуса задачи.
     *
     * Подстатус задаёт своё оформление: он уточняет статус и показывается
     * вместо него. Цвет идёт по нарастанию готовности задачи:
     * серый - голубой - синий - жёлтый - зелёный. Внутри жёлтого оттенок
     * различает задачу в тесте: приглушённый - её проверяют прямо сейчас,
     * насыщенный - она ждёт проверки.
     * @param  int $status    Статус задачи.
     * @param  int $substatus Уточнение статуса (@see IssueSubstatus).
     * @return string Список CSS-классов.
     */
    public static function statusBadgeClass($status, $substatus = IssueSubstatus::NONE)
    {
        switch ((int)$substatus) {
            case IssueSubstatus::BACKLOG:
                return 'bg-secondary';
            case IssueSubstatus::TODO:
                return 'bg-info text-dark';
            case IssueSubstatus::IN_PROGRESS:
                return 'bg-primary';
            case IssueSubstatus::UNDER_TESTING:
                return 'bg-warning bg-opacity-50 text-dark';
            case IssueSubstatus::PASS_TEST:
                return 'bg-success bg-opacity-75 text-dark';
        }

        switch ((int)$status) {
            case Issue::STATUS_WAIT:
                return 'bg-warning text-dark';
            case Issue::STATUS_COMPLETED:
                return 'bg-success';
            default:
                return 'bg-primary';
        }
    }

    /**
     * Класс состояния задачи. Определяет, какие даты и кнопки видны
     * на странице задачи.
     *
     * Подстатус на состояние не влияет: набор доступных действий
     * задаёт только статус задачи.
     * @param  int $status Статус задачи.
     * @return string CSS-класс.
     */
    public static function statusStateClass($status)
    {
        switch ((int)$status) {
            case Issue::STATUS_WAIT:
                return 'verify-issue';
            case Issue::STATUS_COMPLETED:
                return 'completed-issue';
            default:
                return 'active-issue';
        }
    }

    /**
     * Классы бейджа типа задачи.
     * @param  int $type Тип задачи.
     * @return string Список CSS-классов.
     */
    public static function typeBadgeClass($type)
    {
        switch ((int)$type) {
            case Issue::TYPE_BUG:
                return 'bg-danger';
            case Issue::TYPE_SUPPORT:
                return 'bg-info text-dark';
            default:
                return 'bg-secondary';
        }
    }

    /**
     * Классы иконки типа задачи.
     * @param  int $type Тип задачи.
     * @return string Список CSS-классов FontAwesome.
     */
    public static function typeIconClass($type)
    {
        switch ((int)$type) {
            case Issue::TYPE_BUG:
                return 'fa-solid fa-bug';
            case Issue::TYPE_SUPPORT:
                return 'fa-solid fa-life-ring';
            default:
                return 'fa-solid fa-code';
        }
    }
}
