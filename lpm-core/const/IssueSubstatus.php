<?php
/**
 * Уточнение статуса задачи «В работе»: на каком этапе она внутри этого статуса.
 *
 * Подстатус нигде не хранится - он выводится из состояния стикера задачи
 * на Scrum доске. В проектах без Scrum подстатуса нет.
 * @see Issue::getSubstatus()
 */
class IssueSubstatus
{
    /**
     * Подстатуса нет: задача не в работе либо проект не использует Scrum.
     */
    const NONE = 0;

    /**
     * Задача не на доске - лежит в общем бэклоге проекта.
     */
    const BACKLOG = 1;

    /**
     * Задача на доске в колонке «К выполнению».
     */
    const TODO = 2;

    /**
     * Задача на доске в работе.
     */
    const IN_PROGRESS = 3;

    /**
     * Отображаемое название подстатуса.
     * @param  int $substatus Подстатус.
     * @return string Название. Пустая строка, если подстатуса нет.
     */
    public static function getName($substatus)
    {
        switch ((int)$substatus) {
            case self::BACKLOG: return 'Бэклог';
            case self::TODO: return 'К выполнению';
            case self::IN_PROGRESS: return 'В работе';
            default: return '';
        }
    }
}
