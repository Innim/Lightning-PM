<?php 
namespace GMFramework;

/**
 * DateTimeUtils
 * Утилиты для работы со временем и датой
 * @package ru.vbinc.gm.framework.datetime
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.6
 * @access public
 * @link http://www.php.net/manual/ru/class.datetime.php
 */
class DateTimeUtils// extends DateTime
{	
	/**
	 * Текущая дата (unixtime в секундах)
	 * на момент подключения класса
	 * @var int
	 */
	public static $currentDate;
	/**
	 * Сдвиг относительно времени сервера (в секундах)
	 * @var int
	 */
	private static $_timeAdjust = 0;
	
	/**
	 * Получает поправку для времени в секундах
	 * @return int
	 */
	public static function getTimeAdjust()
	{
		return ( self::$_timeAdjust > 0 || !defined( 'TIMEADJUST' ) ) 
					? self::$_timeAdjust : TIMEADJUST;
	}

	/**
	 * Возвращает дату в заданном формате
	 * @param string $format = 'U'
	 * @param integer $unixtime = null Дата для преобразования, по умолчанию - текущая
	 * @return string
	 */
	public static function date( $format = 'U', $unixtime = null )
	{
		if ($unixtime === null) $unixtime = time() + DateTimeUtils::getTimeadjust();
		return date( $format, $unixtime );
	}

	/**
	 * Возвращает дату на момент вермени 00:00:00 дня 
	 * @param string $format = 'U'
	 * @param integer $unixtime = null Дата, по умолчанию - текущая
	 * @return string
	 */
	public static function dayStart( $format = 'U', $unixtime = null )
	{
		if ($unixtime == null) $unixtime = (float)self::date();
		$unixtime -= DateTimeUtils::date( 
				 		DateTimeFormat::HOUR_24_NUMBER, 
				 		$unixtime 
				 	 ) * 3600
				   + DateTimeUtils::date( 
				 		DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS, 
				 		$unixtime 
				 	 ) * 60
				   + DateTimeUtils::date( 
						DateTimeFormat::SECONDS_OF_MINUTE_2_DIGITS, 
						$unixtime 
					 );
		
		return date( $format, $unixtime );
	}

	/**
	 * Преобразует дату в формат datetime mysql
	 * @param int $unixtime Дата для преобразования, по умолчанию - текущая
	 * @param boolean $datetime использовать формать datetime, а не date
	 * @return string Дата в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС
	 */
	public static function mysqlDate( $unixtime = null, $datetime = true )
	{
		return DateTimeUtils::date( DateTimeFormat::YEAR_NUMBER_4_DIGITS       . '-' .
		                            DateTimeFormat::MONTH_NUMBER_2_DIGITS      . '-' .
		                            DateTimeFormat::DAY_OF_MONTH_2             . ( $datetime ? ' ' .
		                            DateTimeFormat::HOUR_24_NUMBER_2_DIGITS    . ':' .
		                            DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS   . ':' .
		                            DateTimeFormat::SECONDS_OF_MINUTE_2_DIGITS : '' ), 
		                            $unixtime );
	}
	 
	/**
	 * Преобразование даты из mysql datetime формата в заданный
	 * @param string $mysqlDate Дата в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС 
	 * (или ГГГГ-ММ-ДД, тогда ЧЧ:ММ:СС = 00:00:00)
	 * @param string $printFormat Формат возврата даты, по умолчанию unixtime
	 * @param string $locale Массив локалей, по умолчанию - русская
	 * @return string Дата в заданном формате
     * @link http://ru.php.net/manual/en/function.strftime.php
	 * @link http://php.net/manual/ru/function.setlocale.php
	 */
	public static function convertMysqlDate( $mysqlDate, $printFormat = null, $locale = null )
	{
		//GM2DO переделать на strtotime

		preg_match( 
		  "/^([0-9]{4})-([0-9]{2})-([0-9]{2})(?: ([0-9]{2}):([0-9]{2}):([0-9]{2}))?$/", 
		  $mysqlDate, 
		  $date 
		);
		
		if (count( $date ) == 4) {
			$_h = 0;
			$_j = 0;
			$_s = 0; 
		} elseif (count( $date ) == 7) {
			$_h = (int)$date[4];
			$_j = (int)$date[5];
			$_s = (int)$date[6];
		} else return false;
		 
		$timestamp = mktime( $_h, $_j, $_s, (int)$date[2], (int)$date[3], (int)$date[1] );
		 
		if (empty( $printFormat )) return $timestamp;
		else
		{
			if ($locale == null) 
			    setlocale( LC_ALL, 'ru_RU.UTF-8', 'rus_RUS.UTF-8', 'Russian_Russia.65001');
			else setlocale( LC_ALL, $locale );
			
			return strftime( $printFormat, $timestamp );
		}
	}
	
	public static function getTimestamp( $year, $month, $day, $hours = 0, $minutes = 0, $seconds = 0 ) 
	{
		return mktime( $hours, $minutes, $seconds, $month, $day, $year );
	}

	/**
	 * Установить разницу во времени в секундах
	 * @param int $value
	 */
	public static function setTimeAdjust( $value )
	{
		self::$_timeAdjust = (float)$value;
		// устанавливаем текущую дату
		self::$currentDate = self::date();
	}
	
	/**
	 * Проверяет, попадает ли дата в заданный интервал (закрытый)
	 * @param float $date
	 * @param int $delta продолжительность периода в секундах 
	 * (может быть отрицательна)
	 * @param float $startDate по умолчанию - текущая 
	 */
	public static function checkDate( $date, $delta, $startDate = null ) {
		if ($startDate === null) $startDate = self::$currentDate;
		
		if ($delta < 0) {
			$endDate   = $startDate;
			$startDate = $endDate + $delta;
		} else {
			$endDate   = $startDate + $delta;
		}
		
		return $date >= $startDate && $date <= $endDate;
	}
	
	public static function isToday( $unixtime ) {
		return self::checkDate( $unixtime, 86400, DateTimeUtils::dayBegin() );
	}
}

// замена статического конструктора
// долбанный php, почему здесь нет таких простых вещей
DateTimeUtils::$currentDate = (float)DateTimeUtils::date();
?>