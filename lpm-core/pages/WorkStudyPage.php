<?
class WorkStudyPage extends BasePage
{
	const UID             = 'timetable';
	const PUID_ADD_WORKER = 'add-worker';
	const PUID_STAT       = 'stat';
	const PUID_CHECK      = 'check';
	/**
	 * 
	 * @var Project
	 */
	private $_project;
	
	private $_workers = array();
	/**
	 * 
	 * @var WSUtils
	 */
	private $_utils;
	
	private $_date     = '';
	private $_dateU    = 0;
	private $_dateEnd  = '';
	private $_dateUEnd = 0;
	//private $_week    = -1;
	
	function __construct()
	{
		parent::__construct( 
			self::UID, 'Рабочее время', true, 
			false, 'work-study', 'Рабочее время', User::ROLE_MODERATOR 
		);
		
		//array_push( $this->_js, 'project', 'issues' );		
		
		$this->_utils = new WSUtils();
		
		//получаем дату
		$this->_dateU = DateTimeUtils::date();
		$this->_date  = DateTimeUtils::mysqlDate(  null, false );
		$date = $this->getAddParam();
		
		if (!$this->_utils->checkDate( $date ) 
				 	  || $date > $this->_date) $date = '';
		if (!empty( $date )) {
			$this->_date  = $date;
			$this->_dateU = DateTimeUtils::convertMysqlDate( $this->_date );
		}
		
		$this->_defaultPUID = self::PUID_CHECK;
		
		/*$this->_curPUID = $this->_engine->getParams()->suid;
		switch ($this->_engine->getParams()->suid) 
		{
			case self::PUID_ADD_WORKER : {
				//$this->_pattern = 'add-worker';
				//$this->_title   = 'Добавить сотрудника';
				array_push( $this->_js, 'addworker' );
			} break;
			case self::PUID_STAT : {
				//$this->_pattern = 'workers-stat';
				//$this->_title   = 'Статистика';
				
				//$this->_week    = DateTimeUtils::date( DateTimeFormat::WEEK_NUMBER_OF_YEAR );
				//$dow = DateTimeUtils::date( DateTimeFormat::DAY_OF_WEEK_NUMBER_ISO8601 );
				
				$this->_dateU    = $this->getStartWeekDate( $this->_dateU );
				$this->_dateUEnd = $this->_dateU + 518400;
				$this->_date     = DateTimeUtils::mysqlDate( $this->_dateU   , false );
				$this->_dateEnd  = DateTimeUtils::mysqlDate( $this->_dateUEnd, false );
				
			} break;
			default : {
				$this->_pattern = 'work-study';
				$this->_curPUID = self::PUID_CHECK;
			}
		}*/			
		
		$this->addSubPage( self::PUID_CHECK     , 'Отметить приход/уход' ); 
		//				   'add-worker'         , array( 'addworker' )  );
		$this->addSubPage( self::PUID_STAT      , 'Статистика'         , 'workers-stat' );
		$this->addSubPage( self::PUID_ADD_WORKER, 'Добавить сотрудника', 
						  'add-worker'          , array( 'addworker' ) );		
	}
	
	public function init() {
		if (!parent::init()) return false;

		// если произошла отметка о приходе/уходе
		if (isset( $_POST['tickWorker'], $_POST['come'],  
				   $_POST['leave'], $_POST['late'], $_POST['away'] ) 
			&& is_array( $_POST['come'] )) {
			
			if (!isset( $_POST['lunchBreak'] )) $_POST['lunchBreak'] = false;
			foreach (array_keys( $_POST['come'] ) as $workerId)
			{
				if (!isset( $_POST['late' ][$workerId],  
							$_POST['leave'][$workerId], 
							$_POST['away' ][$workerId])) continue;
											
				if (!isset( 
						$_POST['lunchBreak'], 
						$_POST['lunchBreak'][$workerId] 
					)) $_POST['lunchBreak' ][$workerId] = false;
				if (!empty( $_POST['come'][$workerId] ))
					$this->tickWorker( 
						$workerId, 
						$_POST['come'][$workerId], 
						'come', 
						$this->_date, 
						$_POST['late'      ][$workerId], 
						$_POST['lunchBreak'][$workerId], 
						$_POST['away'      ][$workerId] 
				);
					
				if (!empty( $_POST['leave'][$workerId] ))
					$this->tickWorker( 
						$workerId, 
						$_POST['leave'][$workerId], 
						'leave', 
						$this->_date, 
						$_POST['late'      ][$workerId], 
						$_POST['lunchBreak'][$workerId], 
						$_POST['away'      ][$workerId] 
				);
			}
			
			LightningEngine::go2URL( $this->getUrl() );
		}
		
		return $this;
	}
	
	public function getAddWorkerList() {
		return Worker::loadAddWorkerList();
	}
	
	/**
	 * Получение списка сотрудников
	 * @return Массив сотрудников с полями id, name, comingTime, leavingTime, late
	 */  
	public function getWorkers( $date = '' ) {
		if (!$this->_workers) {
			if (empty( $date )) $date = $this->_date;
			
			$workers = Worker::loadList();
			if ($workers === false) return false;
			
			$sql = "select `comingTime`, `leavingTime`, `late`, `lunchBreak`, `hoursAway` " .
					 "from `%s` " .
					"where `workerId` = ? and `date` = '" . $date . "'";
			
			if (!$prepare = $this->_db->preparet( $sql, LPMTables::WORK_STUDY )) return false;
			
			foreach ($workers as /*@var $worker Worker */ $worker) {
				$prepare->bind_param( 's', $worker->id );
				$prepare->execute();
								
				if ($rows = $this->_db->fetchAssocPrepare( $prepare )) {
					for ($i = 0; $i < count( $rows ); $i++) {
						$record = new WSRecord();
						$record->loadStream( $rows[$i] );						
						
						$worker->addRecord( $record );
					}
				}
				
			}
			$prepare->close();
			
			$this->_workers = $workers;
		}
		
		return $this->_workers;
	}
	
	/**
	 * Получение статистики по сотрудникам
	 * @return array <code>Array of Worker</code>
	 */
	public function getStat()
	{
		if (!$this->_workers) {
			$workers = Worker::loadList();
			if ($workers === false) return false;
			
			$sql = "select `date`, `comingTime`, `leavingTime`, `late`, `lunchBreak`, " .
			             " `hoursAway`, `mustHours`, `mustComingTime` from `%s` " .
								"where `workerId` = ? " .
								  "and `date` >= '" . $this->_date . "' " .
								  "and `date` <= '" . $this->_dateEnd . "' " .
								"order by `date` asc";
			
			if (!$prepare = $this->_db->preparet( $sql, LPMTables::WORK_STUDY )) return false;
			
			foreach ($workers as /*@var $worker Worker */ $worker) {
				$prepare->bind_param( 's', $worker->id );
				$prepare->execute();
			
				if ($rows = $this->_db->fetchAssocPrepare( $prepare )) {
					for ($i = 0; $i < count( $rows ); $i++) {
						$record = new WSRecord();
						$record->loadStream( $rows[$i] );
			
						$worker->addRecord( $record );
					}
				}
				
				//$worker->initHours();
			
			}
			$prepare->close();
				
			$this->_workers = $workers;
		}
		
		return $this->_workers;
		
		
		/*
	
		$sql = "select `%1\$s`.`id`, `%1\$s`.`userId`, `%1\$s`.`patronymic`, `%2\$s`.`firstName`, `%2\$s`.`secondName` " .
						"from `%1\s` " .
					   "where `%1\$s`.`active` = '1', " .
							 "`%1\$s`.`userId` = `%2\$s`.`userId` and  order by `id` asc";
		if ($query = $this->db->queryt( $sql, LPMTables::WORKERS, LPMTables::USERS ))
		{
			while ($result = $query->fetch_assoc())
			{
				// создаём сотрудника
				$workers[$result['id']] = new Worker( $result['name'], $result['surname'], $result['patronymic'] );
	
				$sql = "select `date`, `comingTime`, `leavingTime`, `late`, `lunchBreak`, `mustHours`, `mustComingTime`, `hoursAway` from `" . $this->db->prefix . WorkStudy::WORK_STUDY_TABLE . "` where `workerId` = '" . $result['id'] . "' and `date` >= '" . $week['start'] . "' and `date` <= '" . $week['end'] . "' order by `date` asc";
				if( $workerQuery = $this->db->query( $sql ) )
				{
					while( $workerResult = $workerQuery->fetch_assoc() )
					$workers[$result['id']]->addDay( $workerResult['date'], $this->utils->getTimeFromDB( $workerResult['comingTime'] ), $this->utils->getTimeFromDB( $workerResult['leavingTime'] ), $workerResult['late'], $workerResult['lunchBreak'], $workerResult['mustHours'], $this->utils->getTimeFromDB( $workerResult['mustComingTime'] ), $workerResult['hoursAway'] );
						
					$workerQuery->close();
				} else return $this->error( $this->db->error );
	
			}
				
			$query->close();
		} else return $this->error( $this->db->error );
	
		return $workers;*/
	}
	
	public function getWeekDays() {		
		$days = array();
		for ($i = 0; $i < 7; $i++) {
			$day = new WSDay();
			$day->setDate( $this->_dateU + $i * 86400 );
			array_push( $days, $day );
		} 
		return $days;
	}
	
	public function getDateLinks() {
		$dates = $this->getDates();
		$links = array();
		foreach ($dates as $i => $date) {
			$link = new Link( $date, $this->getUrl( $date ) );
			if ($i == 0) $link->setCurrent();
			array_push( $links, $link );
		}
		
		return $links;
	}
	
	public function getWeekLinks() {
		$dates = $this->getWeeks();
		$links = array();
		foreach ($dates as $date) {
			$link = new Link( 
						$date[0] . ' - ' . $date[1],
						$this->getUrl( $date[0] ) 
					);
			if ($date[0] == $this->_date) $link->setCurrent();
			array_push( $links, $link );
		}
		
		return $links;
	}
	
	private function tickWorker( 
								 $workerId, $time, $type = 'come', $date = '', 
								 $late = -1, $lunchBreak = 1, $hoursAway = 0  
							   )
	{				
		// проверяем время
		$time = trim( $time );
		if (!$this->_utils->checkTime( $time )) 
			return $this->error( 'Время должно быть в формате ЧЧ:ММ' );
		
		// приводим тип для часов отсутствия
		$hoursAway = (float)$hoursAway;	
		
		// проверяем Id пользователя
		$workerId = (int)$workerId;
		$sql = "select `hours`, `comingTime` from `%s` where `id` = '" . $workerId . "'";
		if ($query = $this->_db->queryt( $sql, LPMTables::WORKERS ))
		{			
			if ($query->num_rows < 1) return $this->error( 'Нет такого сотрудника' );
			else {
				$result = $query->fetch_assoc();
				$__comingTime = $this->_utils->getTimeFromDB( $result['comingTime'] );
				$__mustHours  = $result['hours'];
			}
			
			$query->close();
		} else return $this->error();
		
		// TODO восстановить функционал авто метки об опоздании
		
		// проверяем метку об опоздании
		$late = (int)$late;
		if (!in_array( $late, array( -1, 0, 1 ) )) 
			return $this->error( 'Неверная отметка об опоздании' );
		
		// проверяем отметку об обеде		
		$lunchBreak = (int)$lunchBreak;
		if (!in_array( $lunchBreak, array( 0, 1 ) )) 
			return $this->error( 'Неверная отметка об обеде' );
		
		// проверяем тип 
		switch ($type)
		{
			case 'come'  : {
				if ($late == -1) {
					if (( $__comingTime != '00:00' ) && 
						( $this->_utils->getTimeIntervar( $__comingTime, $time, 'number' ) > .5 )) 
							$late = 1;
					else $late = 0;
				}	
				$field = 'comingTime';
			}  break;
			case 'leave' : $field = 'leavingTime'; break;
			default      : return $this->error( 'Неверный тип' );
		}			
		
		// проверяем не было ли уже добавлено такой записи
		if ($type != 'come') $late = 0; 
		$sql = "insert into `%s` ( `workerId`, `date`, `" . $field . "`, `lunchBreak`, " .
		                          "`hoursAway`, `mustHours`, `mustComingTime`, `late` ) " .
		                 "values ( '" . $workerId . "', '" . $date . "', '" . $time . "', " . 
		                 		  "'" . $lunchBreak . "', '" . $hoursAway . "', " . 
		                 		  "'" . $__mustHours . "', '" . $__comingTime . "', " . 
		                 		  "'" . $late . "' ) " .
				"on duplicate key update `" . $field . "` = values( `" . $field . "` ), " . 
										"`lunchBreak`     = values( `lunchBreak` )," .
			 		 ($type == 'come' ? "`late`           = values( `late` )," : "" ) .
										"`hoursAway`      = values( `hoursAway` )";
		if (!$this->_db->queryt( $sql, LPMTables::WORK_STUDY )) return $this->error();
		
		return true;
	}
	
	/**
	 * Даты по обе стороны от данной, ограничены сверху текущей датой
	 * @param string $date Дата от которой вести отсчёт, по умолчанию текущая дата
	 * @param integer $datesOnOneSide Количество дат по одну сторону
	 * @return array Массив дат 
	 */
	private function getDates( $datesOnOneSide = 2 ) 
	{		
		$dates = array();
		
		$datesInList = 2 * $datesOnOneSide + 1;
		$rightDates = $this->_utils->getDaysInterval( 
						$this->_date, DateTimeUtils::mysqlDate( null, false ) 
					  );
		
		//if ($rightDates < 0) return $this->error( 'Неправильная дата' );
		
		$rightDates = min( $rightDates, $datesOnOneSide );
		for ($i = 0; $i < $datesInList; $i++)
		{
			$addDays  = $rightDates + 1 - $datesInList + $i;
			$tempDate = $this->_utils->addDays( $this->_date, $addDays );
			$dates[$addDays] = $tempDate;
		}
		
		return $dates;
	}
	
	/**
	* Недели по обе стороны от данной, ограничены сверху текущей неделей
	* @param integer $week Неделя от которой вести отсчёт, по умолчанию текущая неделя
	* @param integer $weeksOnOneSide Количество дат по одну сторону
	* @return array Массив недель
	*/
	private function getWeeks( $weeksOnOneSide = 2 )
	{
		$weeksOnOneSide = (int)$weeksOnOneSide;
		
		$curWeekDate = $this->getStartWeekDate( DateTimeUtils::date() );	
		$weeksInList = 2 * $weeksOnOneSide + 1;
		$rightWeeks  = round( ( $curWeekDate - $this->_dateU ) / 604800 );
		$rightWeeks  = min( $rightWeeks, $weeksOnOneSide );
	
		$weeks = array();
		for ($i = 0; $i < $weeksInList; $i++)
		{
			$addWeeks = $rightWeeks + 1 - $weeksInList + $i;
			$tmpDate = $this->_dateU + $addWeeks * 604800;
			array_push( 
					$weeks, 
					array(
							DateTimeUtils::mysqlDate( $tmpDate         , false ),
							DateTimeUtils::mysqlDate( $tmpDate + 518400, false )
						 ) 
			);
		}
	
		return $weeks;
	}
	
	private function getStartWeekDate( $date ) {
		return $date 
			 - ( DateTimeUtils::date( 
			 		DateTimeFormat::DAY_OF_WEEK_NUMBER_ISO8601, 
			 		$date 
			 	 ) - 1 ) * 86400
			 -   DateTimeUtils::date( 
			 		DateTimeFormat::HOUR_24_NUMBER, 
			 		$date 
			 	 ) * 3600
			 -   DateTimeUtils::date( 
			 		DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS, 
			 		$date 
			 	 ) * 60
			 -   DateTimeUtils::date( 
					DateTimeFormat::SECONDS_OF_MINUTE_2_DIGITS, 
					$date 
				 );
	}
}
?>