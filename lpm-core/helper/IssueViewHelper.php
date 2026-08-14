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
     * Классы бейджа статуса задачи.
     * @param  int $status Статус задачи.
     * @return string Список CSS-классов.
     */
    public static function statusBadgeClass($status)
    {
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
