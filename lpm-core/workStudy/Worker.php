<?php

/**
 * @author GreyMag
 * @copyright 2009
 */

 /**
  * Класс, описывающий сотрудника 
  */ 
class Worker extends User
{
	/**
	 * 
	 * @param boolean $onlyActive выбирать только активных
	 */
	public static function loadList( $onlyActive = true ) {
		$sql = "select * from `%1\$s`, `%2\$s` " .
						   "where `%1\$s`.`userId` = `%2\$s`.`userId`";
		if ($onlyActive) $sql .= " and `%1\$s`.`active` = '1'";
		return StreamObject::loadObjList( 
					self::getDB(),
					array( $sql, LPMTables::WORKERS, LPMTables::USERS ), 
					__CLASS__ 
			   );
	}
	
	public static function loadAddWorkerList() {
		$sql = "select `%1\$s`.*, `%2\$s`.`id` from `%1\$s` left join  `%2\$s` " .
		                       "on `%1\$s`.`userId` = `%2\$s`.`userId`";		
		return StreamObject::loadObjList(
					self::getDB(), 
					array( $sql, LPMTables::USERS, LPMTables::WORKERS ), 
					__CLASS__ 
			   );
	}
		
	/**
	 * Время на обед в часах
	*/
	const LUNCH_BREAK = 1;
	
	public $id = 0;
	
	/**
     *  Количество часов, которые сотрудник должен отработать
     *  в день
     */ 
    public $hours;
    /**
     * Количество отработанных часов
     * @var int
     */
	public $realHours = 0;
    /**
     * Количество часов, идущих в бюллетень - целое число
     * @var int
     */
	public $statHours = 0;
    /**
     * Остаток от количества часов, идущих в бюллетень
     * @var int
     */
	public $statRest = 0;
    /**
     * Количество опозданий
     * @var int
     */
	public $latesCount = 0;
    /**
     * Остаток часов, которые надо отработать
     * @var int
     */
	public $rest;
	/**
	 * Количество часов, которые сотрудник должен отработать
	 * в неделю
	 * @var int
	 */
	public $mustHours = 0;
	
	protected 
		   /**
		    * Вспомогательные функции
		    */
		    $_utils,
		   /**
		    * Имя сотрудника 
		    */ 
		    //$_name,
		   /**
		    *  Фамилия сотрудника
		    */ 
		  //  $_surname,
		   /**
		    *  Отчество сотрудника
		    */ 
		  //  $_patronymic,		   
		   /**
		    *  Время, в которое должен приходить сотрудник
		    */ 
		    //$_comingTime,
            $_curWeek = 0;//,  
		   /**
		    * Массив рабочих дней с информацией по ним
			*/ 
			/*$_days = array( 1 => array( 'day' => 'Понедельник', 'shortDay' => 'пн' ),
						   2 => array( 'day' => 'Вторник', 'shortDay' => 'вт' ),
						   3 => array( 'day' => 'Среда', 'shortDay' => 'ср' ),
						   4 => array( 'day' => 'Четверг', 'shortDay' => 'чт' ),
						   5 => array( 'day' => 'Пятница', 'shortDay' => 'пт' ),
						   6 => array( 'day' => 'Суббота', 'shortDay' => 'сб' ),
						   7 => array( 'day' => 'Воскресенье', 'shortDay' => 'вс' ) );*/ 
	
	private $_records       = array();
	private $_recordsByDays = array();
	
	function __construct( /*$name, $surname, $patronymic*/ )
	{
		parent::__construct();
		$this->utils = new WSUtils();
		
		//if (!$this->utils->checkTime( $comingTime ) ) $comingTime = 0;
		//$this->_name = htmlspecialchars( $name );
		//$this->_surname = htmlspecialchars( $surname );
		//$this->_patronymic = htmlspecialchars( $patronymic );
		//$this->_hours = (int)$hours;
		//$this->_rest = $this->_hours;
		//$this->_comingTime = $comingTime;		
		$this->_typeConverter->addFloatVars( 'id' );		
	}

	
	/** 
	 * Возврат свойств
	 */ 
	public function __get( $var )
	{
		switch( $var )
		{
			/*case 'worker' : return $this->surname . ' ' . $this->name . ' ' . $this->patronymic; break;
			default : {
				if( property_exists( $this, '_' . $name ) ) return $this->{'_' . $name};
				else return false;
			}*/
			case 'comingTime'  : 
			case 'leavingTime' : {
				if (count( $this->_records ) > 0) {
					if ($this->_records[0]->$var == 0) return '';
					else return $this->_records[0]->{'get' . ucfirst( $var )}();
				} else {
					return false;
				}
			} break;
			case 'late'        : 
			case 'lunchBreak'  : 
			case 'hoursAway'   : {
				if (count( $this->_records ) > 0) {
					return $this->_records[0]->$var;
				} else {
					return ($var == 'late') ? -1 : false;
				}
			} break;
 		}
	} 
	
	public function isRegistered() {
		return $this->id > 0;
	}
	
	/**
	 * Возвращает запись по дню недели (0-6)
	 */
	public function getRecord( $dayOfWeek ) {
		return isset( $this->_recordsByDays[$dayOfWeek] ) 
			   ? $this->_recordsByDays[$dayOfWeek] 
			   : false; 
	}
	
	public function addRecord( WSRecord $record ) {
		array_push( $this->_records, $record );
		$this->_recordsByDays[$record->getDayOfWeek()] = $record;
		//$this->addDay( $, $comingTime, $leavingTime, $late, $lunchBreak, $hours)
		
		// поправка для рабочего времени дня
		$delta = 0;
		if ($record->lunchBreak) $delta += self::LUNCH_BREAK;

		
		// добиваем дельту если есть часы отсутствия
		$delta += $record->hoursAway;	
		
		// считаем часы
		if ($record->leavingTime == 0 && $record->isToday()) $leavingTime = DateTimeUtils::date() - DateTimeUtils::dayBegin();
		else $leavingTime = $record->leavingTime;
		
		$record->hours = 0;//- $delta;
		if ($record->comingTime != 0 && $leavingTime != 0) 
			$record->hours +=  round( ( $leavingTime - $record->comingTime ) / 3600, 2 );
		//else $hours = 0;
		$record->hours = max( 0, $record->hours );
		

		// остаток по статистике распеределяем
		$__statHours = floor( $record->hours );
			
		$this->statRest += $record->hours - $__statHours;
		if ($this->statRest > 0)
		{
			$__statHours    += round( $this->statRest );
			$this->statRest -= round( $this->statRest );
		}
		$this->statHours += $__statHours;
		//$this->_days[$dayWeekNumber]['statHours'] = $__statHours;
		
		//$this->_
		/*if( ( $mustComingTime != '00:00' ) && ( $this->utils->getTimeIntervar( $mustComingTime, $comingTime, 'number' ) > .5 ) )
		 {
		$late = true;
		$this->_latesCount++;
		}
		else $late = false;*/
		if ($record->late) $this->latesCount++;					
			
		$this->realHours += $record->hours;
		$this->mustHours += $record->mustHours - $this->hours;
			
		$this->rest = round( $this->mustHours - $this->realHours, 2 );
	}
	
	/*public function initHours() {
		
	}*/
		
	protected function setVar( $var, $value ) {
		if ($var == 'hours') {
			$this->mustHours = $value * 5;
			$this->rest      = $this->mustHours;
		}
		return parent::setVar( $var, $value );
	}
}
?>