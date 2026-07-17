<?php
/**
 * Форматирование прошедшего времени в человекочитаемом виде («N единиц назад»).
 */
class TimeAgoHelper
{
    /**
     * Возвращает относительное время в формате «N единиц назад»
     * (минуты, часы, дни, недели, месяцы, годы), либо «только что» для событий
     * младше минуты. Для пустого времени возвращает пустую строку.
     * @param int $timestamp Unix-время события.
     * @param int|null $now Момент отсчёта; по умолчанию текущее время.
     * @return string
     */
    public static function format($timestamp, $now = null)
    {
        if (empty($timestamp)) {
            return '';
        }

        if ($now === null) {
            $now = time();
        }

        $diff = $now - $timestamp;
        if ($diff < 60) {
            return 'только что';
        }

        $minutes = intdiv($diff, 60);
        if ($minutes < 60) {
            return self::ago($minutes, DeclensionHelper::minutes($minutes));
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            return self::ago($hours, DeclensionHelper::hours($hours));
        }

        $days = intdiv($hours, 24);
        if ($days < 7) {
            return self::ago($days, DeclensionHelper::days($days));
        }

        if ($days < 30) {
            $weeks = intdiv($days, 7);
            return self::ago($weeks, DeclensionHelper::weeks($weeks));
        }

        if ($days < 365) {
            $months = intdiv($days, 30);
            return self::ago($months, DeclensionHelper::months($months));
        }

        $years = intdiv($days, 365);
        return self::ago($years, DeclensionHelper::years($years));
    }

    private static function ago($count, $unit)
    {
        return $count . ' ' . $unit . ' назад';
    }
}
