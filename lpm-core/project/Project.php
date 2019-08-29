<?php
class Project extends MembersInstance 
{
	/**
	 * 
	 * @var Project
	 */
	public static $currentProject;
	
	private static $_availList = null;

	private static $_isArchive = false;

	/**
	 * Загруженные проекты по идентификаторам
	 * @var array<int, Project>
	 */
	private static $_projectsByIds = [];
	
	public static function loadList( $where ) {
		return StreamObject::loadListDefault( self::getDB(), $where, LPMTables::PROJECTS, __CLASS__ );
	}

    /**
     * Обновляет настройки проекта
     *
     */
    public static function updateProjectSettings($projectId, $scrum, $slackNotifyChannel) {

        $db = LPMGlobals::getInstance()->getDBConnect();

        $hash = [
            'UPDATE' => LPMTables::PROJECTS,
            'SET' => [
                'scrum' => $scrum,
                'slackNotifyChannel' => $slackNotifyChannel
            ],
            'WHERE' => [
                'id' => $projectId
            ]
        ];

        return  $db->queryb($hash);
    }
		
	public static function getAvailList( $isArchive ) {
		if (self::$_availList == null ) {
			if (LightningEngine::getInstance()->isAuth()) {				
				$user = LightningEngine::getInstance()->getUser();
				if (!$user->isModerator()) {
					$sqlDevelop = "select `%1\$s`.* from `%1\$s`, `%2\$s` " .
					 					   "where `%1\$s`.`isArchive` = '0' and `%2\$s`.`userId` = '" . $user->userId   . "' " .
											 "and `%2\$s`.`instanceId`   = `%1\$s`.`id` " .
											 "and `%2\$s`.`instanceType` = '" . LPMInstanceTypes::PROJECT   . "' " .
					"ORDER BY `%1\$s`.`lastUpdate` DESC";

					$sqlArchive = "select `%1\$s`.* from `%1\$s`, `%2\$s` " .
					 					   "where `%1\$s`.`isArchive` = '1' and `%2\$s`.`userId` = '" . $user->userId   . "' " .
											 "and `%2\$s`.`instanceId`   = `%1\$s`.`id` " .
											 "and `%2\$s`.`instanceType` = '" . LPMInstanceTypes::PROJECT   . "' " .
					"ORDER BY `%1\$s`.`lastUpdate` DESC";
					
					self::$_availList['develop'] = StreamObject::loadObjList( self::getDB(), array( $sqlDevelop, LPMTables::PROJECTS, LPMTables::MEMBERS ), __CLASS__ );
					self::$_availList['archive'] = StreamObject::loadObjList( self::getDB(), array( $sqlArchive, LPMTables::PROJECTS, LPMTables::MEMBERS ), __CLASS__ );
				}
				else
				{
					self::$_availList['develop'] = self::loadList( "`isArchive`=0 ORDER BY `%1\$s`.`lastUpdate` DESC" );
					self::$_availList['archive'] = self::loadList( "`isArchive`=1 ORDER BY `%1\$s`.`lastUpdate` DESC" );
				}
			} else self::$_availList = array();
		}
		
		return ( $isArchive ) ? self::$_availList['archive'] : self::$_availList['develop'];
	}


	public static function updateIssuesCount( $projectId ) {
		$db = LPMGlobals::getInstance()->getDBConnect();
		$sql = "UPDATE `%1\$s` ".
				  "SET `issuesCount` = (SELECT COUNT(*) FROM `%2\$s` ".
				  						"WHERE `%1\$s`.`id` = `%2\$s`.`projectId` ".
				  						  "AND  `%2\$s`.`status` IN (0,1) ".
				  						  "AND  `%2\$s`.`deleted` = 0) ".
				"WHERE  `%1\$s`.`id` = '" . $projectId . "'";
				
		return $db->queryt( $sql, LPMTables::PROJECTS, LPMTables::ISSUES );
	}

	public static function sumHoursActiveIssues($projectId)
	{
		$db = LPMGlobals::getInstance()->getDBConnect();
        $sql ="SELECT SUM(`hours`) AS `sum` FROM `%s` WHERE `projectId` = ".$projectId." ".
               "AND `deleted` = 0 ".
               "AND NOT `status` = ".Issue::STATUS_COMPLETED." ";
        $query = $db->queryt( $sql, LPMTables::ISSUES );
        if (!$query || !($row = $query->fetch_assoc())) return false;
       	return (float)$row['sum'];
	}
	
	/**
	 * 
	 * @param string $projectUID
	 * @return Project
	 */
	public static function load( $projectUID ) {
		return StreamObject::singleLoad( $projectUID, __CLASS__, '', 'uid' );
	}		
	
	/**
	 * Загружает данные проекта.
	 * Если проект уже был загружен - вернется сохраненный объект
	 * @param  int     $projectId   Идентификатор проекта
	 * @param  boolean $forceReload Принудительно выполняет загрузку из БД, 
	 * даже если есть уже загруженные данные
	 * @return Project
	 */
	public static function loadById($projectId, $forceReload = false) 
	{
		if ($forceReload || !isset(self::$_projectsByIds[$projectId]))
		{
			$project = StreamObject::singleLoad($projectId, __CLASS__, '');;
			self::$_projectsByIds[$projectId] = $project;
		}
		else 
		{
			$project = self::$_projectsByIds[$projectId];
		}

		return $project;
	}
	
	/**
	 * Возвращает URL страницы проекта.
	 * @param  string $projectUID Строковый идентификатор проекта.
	 * @param  string $hash       Хэш параметр.
	 * @return URL страницы проекта.
	 */
	public static function getURLByProjectUID($projectUID, $hash = '') {
		return Link::getUrl(ProjectPage::UID, [$projectUID], $hash);
	} 

	/**
	 * Сбрасывает загруженные 
	 * @return [type] [description]
	 */
	public static function resetLoaded()
	{
	    
	}
	
	/**
	 * 
	 * @var int
	 */
	public $id = 0;
	public $uid;
	public $name;
	public $desc;

	/**
	 * Проект ведется с помощью методологии Scrum
	 * @var Boolean
	 */
	public $scrum = false;

	/**
	 * Имя канала для оповещений в Slack (без решетки).
	 * @var string
	 */
	public $slackNotifyChannel;

	/**
	 * Идентификатор пользователя, являющегося мастером в проекте.
	 * @var int
	 */
	public $masterId;

	private $_importantIssuesCount = -1;

	private $_sumOpenedIssuesHours = -1;
	private $_totalIssuesCount = -1;

	/**
	 * @var User
	 */
	private $_master;
	
	function __construct() 
	{
		parent::__construct();
		$this->_typeConverter->addIntVars('id');
		$this->_typeConverter->addBoolVars('scrum');
	}
	
	public function getID() {
		return $this->id;
	}
	
	public function getShortDesc() {
		parent::getShort( $this->desc, 200 );
	}
	
	public function getUrl() {
		//return Link::getUrlByUid( ProjectPage::UID, $this->uid );
		return self::getURLByProjectUID( $this->uid );
	}
	
	public function getDesc() {
		$text = nl2br( $this->desc);
		$text = HTMLHelper::linkIt($text);
		return $text;
	}

	/**
	 * Возвращает количество важных задач, открытых для текугео пользователя по этому проекту
	 * @return [type] [description]
	 */
	public function getImportantIssuesCount()
	{
	    if ($this->_importantIssuesCount === -1)
	    {
	    	$userId = LightningEngine::getInstance()->getUserId();
	    	$this->_importantIssuesCount = empty($userId) ? 0 : 
	    		Issue::getCountImportantIssues($userId, $this->id);
	    }

	    return $this->_importantIssuesCount;
	}

	/**
	 * Возвращает количество всех задач текущего проекта
	 * @return int
	 */
	public function getTotalIssuesCount()
	{
	    if ($this->_totalIssuesCount === -1)
	    {
	    	$this->_totalIssuesCount = Issue::loadTotalCountIssuesByProject($this->id);
	    }

	    return $this->_totalIssuesCount;
	}

	/**
	 * Возвращает сумму часов открытых задач
	 * @return int
	 */
	public function getSumOpenedIssuesHours()
	{
		if ($this->_sumOpenedIssuesHours === -1)
	    {
	    	$this->_sumOpenedIssuesHours = self::sumHoursActiveIssues($this->id);
	    }

	    return $this->_sumOpenedIssuesHours;
	}
	
	/**
	 * Возвращает лейбл для параметра hours в задаче из проекта (без значения)
	 * @param  int $value
	 * @param  boolean $short Использовать сокращение 
	 * @return Лейбл, со склонением, зависящим от значения hours. Например: часов, SP
	 */
	public function getNormHoursLabel($value, $short = false)
	{
		if ($this->scrum)
			return DeclensionHelper::storyPoints($value, $short);
		else 
			return $short ? 'ч' : DeclensionHelper::hours($value);
	}

	/**
	 * Определяет, есть ли у пользователя права на чтение проекта.
	 * @param  User    $user Пользователь.
	 * @return boolean       true если есть права, в ином случае false.
	 */
	public function hasReadPermission(User $user) {
		if ($user->isModerator())
			return true;

		if ($this->_members != null) {
			foreach ($this->_members as $member) {
				if ($user->userId == $member->userId)
					return true;
			}
			return false;
		} else {
			$sql = "SELECT `instanceId` FROM `%s` " .
			                 "WHERE `instanceId`   = '" . $this->id . "' " .
							   "AND `instanceType` = '" . LPMInstanceTypes::PROJECT . "' " .
							   "AND `userId`       = '" . $user->userId . "'";

			$db = LPMGlobals::getInstance()->getDBConnect();
			if (!$query = $db->queryt( $sql, LPMTables::MEMBERS ))
                return false;

			return $query->num_rows > 0;
		}
	}
    
    /**
     * Возвращает пользователя, назначенного мастером проекта.
     * Если пользователь не выставлен, он будет загружен.
     * @return User|null
     */
    public function getMaster() {
        if ($this->_master === null && $this->masterId > 0)
        	$this->_master = User::load($this->masterId);

        return $this->_master;
    }
	
	protected function loadMembers() {
		if (!$this->_members = Member::loadListByProject( $this->id )) return false;
		return true;
	}

	// public function setIsArchive( $projectId , $value ){
	// 	$db = LPMGlobals::getInstance()->getDBConnect();
	// 	$sql = "UPDATE `%1\$s`".
	// 			"SET `isArchive` = '" . $value . "'" .
	// 			"WHERE `id` = '" . $projectId . "'";
	// 	return $db->queryt( $sql, LPMTables::PROJECTS );
	// }

}
?>