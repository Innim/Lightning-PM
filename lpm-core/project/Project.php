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
	
	public static function loadList( $where ) {
		return StreamObject::loadListDefault( self::getDB(), $where, LPMTables::PROJECTS, __CLASS__ );
	}
		
	public static function getAvailList( $isArchive ) {
		if (self::$_availList == null ) {
			if (LightningEngine::getInstance()->isAuth()) {				
				$user = LightningEngine::getInstance()->getUser();
				if (!$user->isModerator()) {
					$sqlDevelop = "select `%1\$s`.* from `%1\$s`, `%2\$s` " .
					 					   "where `%1\$s`.`isArchive` = 'false' and `%2\$s`.`userId` = '" . $user->userId   . "' " .
											 "and `%2\$s`.`instanceId`   = `%1\$s`.`id` " .
											 "and `%2\$s`.`instanceType` = '" . Project::ITYPE_PROJECT   . "' " .
					"ORDER BY `%1\$s`.`lastUpdate` DESC";

					$sqlArchive = "select `%1\$s`.* from `%1\$s`, `%2\$s` " .
					 					   "where `%1\$s`.`isArchive` = 'true' and `%2\$s`.`userId` = '" . $user->userId   . "' " .
											 "and `%2\$s`.`instanceId`   = `%1\$s`.`id` " .
											 "and `%2\$s`.`instanceType` = '" . Project::ITYPE_PROJECT   . "' " .
					"ORDER BY `%1\$s`.`lastUpdate` DESC";
					
					self::$_availList['develop'] = StreamObject::loadObjList( self::getDB(), array( $sqlDevelop, LPMTables::PROJECTS, LPMTables::MEMBERS ), __CLASS__ );
					self::$_availList['archive'] = StreamObject::loadObjList( self::getDB(), array( $sqlArchive, LPMTables::PROJECTS, LPMTables::MEMBERS ), __CLASS__ );
				}
				else
				{
					self::$_availList['develop'] = self::loadList( "`isArchive`='" . false . "' ORDER BY `%1\$s`.`lastUpdate` DESC" );
					self::$_availList['archive'] = self::loadList( "`isArchive`='" . true . "' ORDER BY `%1\$s`.`lastUpdate` DESC" );
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

	private $_importantIssuesCount = -1;
	
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