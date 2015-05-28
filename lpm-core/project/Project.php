<?php
class Project extends MembersInstance 
{
	/**
	 * 
	 * @var Project
	 */
	public static $currentProject;
	
	private static $_availList = null;
	
	public static function loadList( $where ) {
		return StreamObject::loadListDefault( self::getDB(), $where, LPMTables::PROJECTS, __CLASS__ );
	}
		
	public static function getAvailList() {
		if (self::$_availList == null ) {
			if (LightningEngine::getInstance()->isAuth()) {				
				$user = LightningEngine::getInstance()->getUser();
				if (!$user->isModerator()) {
					$sql = "select `%1\$s`.* from `%1\$s`, `%2\$s` " .
					 					   "where `%2\$s`.`userId`       = '" . $user->userId   . "' " .
											 "and `%2\$s`.`instanceId`   = `%1\$s`.`id` " .
											 "and `%2\$s`.`instanceType` = '" . Project::ITYPE_PROJECT   . "' " .
					"ORDER BY `%1\$s`.`lastUpdate` DESC";
					
					self::$_availList = StreamObject::loadObjList( self::getDB(), array( $sql, LPMTables::PROJECTS, LPMTables::MEMBERS ), __CLASS__ );
				} else self::$_availList = self::loadList( "1 ORDER BY `lastUpdate` DESC" );
			} else self::$_availList = array();
		}
		
		return self::$_availList;
	}

	public static function updateIssuesCount( $projectId ) {
		$db = LPMGlobals::getInstance()->getDBConnect();
		$sql = "UPDATE `%1\$s` ".
				  "SET `issuesCount` = (SELECT COUNT(*) FROM `%2\$s` ".
				  						"WHERE `%1\$s`.`id` = `%2\$s`.`projectId` ".
				  						  "AND  `%2\$s`.`status` = 0 ".
				  						  "AND  `%2\$s`.`deleted` = 0) ".
				"WHERE  `%1\$s`.`id` = '" . $projectId . "'";
				
		return $db->queryt( $sql, LPMTables::PROJECTS, LPMTables::ISSUES );
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
	*
	* @param string $projectIв
	* @return Project
	*/
	public static function loadById( $projectId ) {
		return StreamObject::singleLoad( $projectId, __CLASS__, '' );
	}
	
	/*public static function getURLByProjectUID( $projectUID ) {
		return Link::getUrlByUid( ProjectPage::UID, $projectUID );
	} */
	
	
	const ITYPE_PROJECT = 2;
	
	/**
	 * 
	 * @var int
	 */
	public $id = 0;
	public $uid;
	public $name;
	public $desc;
	
	function __construct() 
	{
		parent::__construct();
		$this->_typeConverter->addIntVars( 'id' );
	}
	
	public function getID() {
		return $this->id;
	} 
	
	public function getShortDesc() {
		parent::getShort( $this->desc, 200 );
	}
	
	public function getUrl() {
		return Link::getUrlByUid( ProjectPage::UID, $this->uid );
		//return self::getURLByProjectUID( $this->uid );
	}
	
	protected function loadMembers() {
		if (!$this->_members = Member::loadListByProject( $this->id )) return false;
		return true;
	}
	public static function sumHoursActiveIssues()
	{
		$projectId = lpm_get_project()->id;
		$db = LPMGlobals::getInstance()->getDBConnect();
        $sql ="SELECT SUM(`hours`) AS `sum` FROM `%s` WHERE `projectId` = ".$projectId." ".
               "AND `deleted` = 0 ".
               "AND NOT `status` = ".Issue::STATUS_COMPLETED." "; 
        $query = $db->queryt( $sql, LPMTables::ISSUES );
        if (!$query || !($row = $query->fetch_assoc())) return false;
       	return (int)$row['sum'];
	}
}
?>