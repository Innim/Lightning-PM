<?php
class WSDay {
	/**
	 * День недели
	 * 0 - понедельник, 6 - воскресенье
	 * @var int
	 */
	public $day;
	public $date;
	
	private $_dateU;
	
	public function setDate( $unixtime ) {
		$this->_dateU = $unixtime;
		$this->date   = DateTimeUtils::mysqlDate( $this->_dateU, false );
		$this->day    = DateTimeUtils::date( 
							DateTimeFormat::DAY_OF_WEEK_NUMBER_ISO8601, 
							$this->_dateU 
						) - 1;		
	}
	
	public function getDayName() {
		switch ($this->day) {
			case 0 : return 'понедельник';
			case 1 : return 'вторник';
			case 2 : return 'среда';
			case 3 : return 'четверг';
			case 4 : return 'пятница';
			case 5 : return 'суббота';
			case 6 : return 'воскресенье';
		}
	}

	public function getDayShortName() {
		switch ($this->day) {
			case 0 : return 'пн';
			case 1 : return 'вт';
			case 2 : return 'ср';
			case 3 : return 'чт';
			case 4 : return 'пт';
			case 5 : return 'сб';
			case 6 : return 'вс';
		}
	}
}
?>