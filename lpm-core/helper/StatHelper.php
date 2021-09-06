<?php
/**
 * Вспомогательный класс для работы со статистикой,
 *
 * Здесь идет о статистике SP, которая считается по спринтам.
 */
class StatHelper
{
    public static function parseMonthYearFromArg($arg)
    {
        $nowYear = (int)date('Y');
        $nowMonth = (int)date('m');

        $monthStr = $arg;
        if (!empty($monthStr)) {
            $monthArr = explode('-', $monthStr);
            $month = intval($monthArr[0]);
            $year = intval($monthArr[1]);
        } else {
            $month = $nowMonth;
            $year = $nowYear;
        }

        return [$month, $year];
    }

    public static function getMonthForUrl($month, $year)
    {
        return sprintf('%02d-%04d', $month, $year);
    }

    public static function getNextMonthYear($month, $year)
    {
        $nextMonth = $month == 12 ? 1 : $month + 1;
        $nextYear = $month == 12 ? $year + 1 : $year;

        return [$nextMonth, $nextYear];
    }

    public static function getPrevMonthYear($month, $year)
    {
        $prevMonth = $month == 1 ? 12 : $month - 1;
        $prevYear = $month == 1 ? $year - 1 : $year;

        return [$prevMonth, $prevYear];
    }

    public static function getStatDaysRange($month, $year)
    {
        // День в месяце, законченный до которого спринт относим к предыдущему
        // TODO: вынести в опции? или просто константу?
        $dayInMonthForSprint = 5;
        list($nextMonth, $nextYear) = StatHelper::getNextMonthYear($month, $year);

        $startDate = strtotime(sprintf('%02d.%02d.%04d', $dayInMonthForSprint + 1, $month, $year));
        $endDate = strtotime(sprintf('%02d.%02d.%04d', $dayInMonthForSprint, $nextMonth, $nextYear));

        return [$startDate, $endDate];
    }

    public static function isAvailable($month, $year)
    {
        $nowYear = (int)date('Y');
        $nowMonth = (int)date('m');

        return $year < $nowYear || $month <= $nowMonth;
    }
}
