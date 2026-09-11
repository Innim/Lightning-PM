<?php
/**
 * Фильтр личной scrum доски: по какой роли пользователя в задаче отбирать стикеры.
 *
 * Значение хранится в настройках пользователя ({@see UserPref::$myBoardRole}),
 * поэтому константы менять нельзя - в БД лежат именно эти числа.
 */
class ScrumBoardRoleFilter extends \GMFramework\Enum
{
    /**
     * Все задачи пользователя - и где он исполнитель, и где тестировщик.
     */
    const ANY = 0;
    /**
     * Только задачи, где пользователь исполнитель.
     */
    const MEMBER = 1;
    /**
     * Только задачи, где пользователь тестировщик.
     */
    const TESTER = 2;

    /**
     * Возвращает названия фильтров для выбора пользователем.
     * @return array Массив `значение фильтра => название`.
     */
    public static function getLabels()
    {
        return [
            self::ANY    => 'Все задачи',
            self::MEMBER => 'Где я исполнитель',
            self::TESTER => 'Где я тестировщик',
        ];
    }

    /**
     * Приводит значение к одному из фильтров.
     * @param  mixed $value Проверяемое значение.
     * @return int Значение фильтра; {@see ANY}, если значение неизвестно.
     */
    public static function sanitize($value)
    {
        $value = (int)$value;
        return self::validateValue($value) ? $value : self::ANY;
    }

    /**
     * Возвращает типы участия в задаче, которые отбирает фильтр.
     * @param  int $filter Значение фильтра.
     * @return array<int> Типы участия, см. {@see LPMInstanceTypes}.
     */
    public static function getInstanceTypes($filter)
    {
        switch ($filter) {
            case self::MEMBER: return [LPMInstanceTypes::ISSUE];
            case self::TESTER: return [LPMInstanceTypes::ISSUE_FOR_TEST];
            default: return [LPMInstanceTypes::ISSUE, LPMInstanceTypes::ISSUE_FOR_TEST];
        }
    }

    /**
     * Определяет, попадают ли при этом фильтре на доску задачи тестировщика,
     * снятые с доски проекта.
     * @param  int $filter Значение фильтра.
     * @return boolean
     */
    public static function withOffBoardTesterIssues($filter)
    {
        return $filter === self::ANY || $filter === self::TESTER;
    }

    /**
     * Определяет, имеет ли при этом фильтре смысл показ свободных задач.
     *
     * Свободная задача - это задача, которую можно взять себе исполнителем,
     * поэтому к отбору по роли тестировщика она отношения не имеет.
     * @param  int $filter Значение фильтра.
     * @return boolean
     */
    public static function allowsFreeIssues($filter)
    {
        return $filter !== self::TESTER;
    }
}
