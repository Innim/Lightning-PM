<?php
/**
 * Объект даты
 * @package ru.vbinc.gm.framework.datetime
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.1
 * @access public
 */
class GMDate
{
	private $_u;
	
	function __construct( $unixtime = '' ) {
		$this->_u = ($unixtime === '') ? DateTimeUtils::$currentDate : $unixtime;
	}
	
	public function setDayBegin() {
		$this->_u = DateTimeUtils::dayBegin( 
		              DateTimeFormat::UNIXTIME_SECONDS, 
		              $this->_u 
		            );
	}
	
	/**
	 * Отнимает из текущей даты переданную и возвращает количество секунд разницы
	 * @param GMDate $date
	 */
	public function diff( GMDate $date ) {
		return $this->_u - $date->getUnixtime();
	}
    
	/**
	 * Возвращает количество полных дней разности между датами
	 * @param GMDate $date
	 */
    public function diffDays( GMDate $date ) {
        return floor( $this->diff( $date ) / 86400 );
    }
	
	public function addDays( $value = 1 ) {
        return $this->addSec( $value * 86400 );
	}
    
    public function addHours( $value = 1 ) {
        return $this->addSec( $value * 3600 );
    }
    
    public function addMinutes( $value = 1 ) {
        return $this->addSec( $value * 60 );
    }
	
	public function addSec( $value = 1 ) {
		$this->_u += $value;
		return $this->_u;
	}
	
	public function getUnixtime() {
		return $this->_u;
	}
	
	public function mysqlDate() {
		return DateTimeUtils::date( 
		          DateTimeFormat::YEAR_NUMBER_4_DIGITS  . '-' .
                  DateTimeFormat::MONTH_NUMBER_2_DIGITS . '-' .
                  DateTimeFormat::DAY_OF_MONTH_2        , 
                  $this->_u 
        );
	}
    
    public function mysqlDateTime() {
        return DateTimeUtils::date( 
                  DateTimeFormat::YEAR_NUMBER_4_DIGITS       . '-' .
                  DateTimeFormat::MONTH_NUMBER_2_DIGITS      . '-' .
                  DateTimeFormat::DAY_OF_MONTH_2             . ' ' .
                  DateTimeFormat::HOUR_24_NUMBER_2_DIGITS    . ':' .
                  DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS   . ':' .
                  DateTimeFormat::SECONDS_OF_MINUTE_2_DIGITS, 
                  $this->_u 
        );
    }
}
?>