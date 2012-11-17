<?php
/**
 * Пользователь
 * @author GreyMag
 *
 */
class User extends LPMBaseObject 
{	

	public static function loadList( $where ) {
		if ($where != '') $where = ' AND (' . $where . ')'; 
		return StreamObject::loadListDefault( 
					self::getDB(),
					'`%1$s`.`userId` = `%2$s`.`userId`' . $where, 
					array( LPMTables::USERS, LPMTables::USERS_PREF ),
					__CLASS__ 
		);
	}
	
	/**
	 * @param unknown_type $userId
	 * @return User
	 */
	public static function load( $userId ) {
		//return StreamObject::loadListDefault( $where, LPMTables::USERS, __CLASS__ );
		return StreamObject::singleLoad( $userId, __CLASS__, '', '%1$s`.`userId' );
	}
	
	public static function checkCurRole( $curRole, $reqRole ) {
		if ($reqRole == self::ROLE_USER) return true;
		if ($curRole == self::ROLE_USER) return false;	
		
		return $curRole <= $reqRole;
	}
	
	const ROLE_USER      = 0;
	const ROLE_ADMIN     = 1;
	const ROLE_MODERATOR = 2;
	
	public $userId;
	public $email     = '';
	public $nick      = '';
	public $firstName = '';
	public $lastName  = '';
	public $lastVisit = 0;
	public $regDate   = 0;
	public $role      = 0;
	public $secret    = false;
	public $avatarUrl = '';
	
	public $pref;
	
	function __construct() 
	{
		parent::__construct();
		
		$this->pref = new UserPref();
		
		$this->_typeConverter->addIntVars( 'userId' );
		$this->_typeConverter->addBoolVars( 'secret' );
		$this->addDateTimeFields( 'lastVisit', 'regDate' );	
		
		$this->addClientFields( 'userId', 'firstName', 'lastName', 'nick', 'avatarUrl' );
		
		// TODO обновлять последний вход
	}	
	
	public function getID() {
		return $this->userId;
	}

	public function getEmail() {
		return '***';
		// FIXME продумать систему закрытых email'ов
		return $this->secret ? '***' : $this->email;
	}
	
	public function getName() {
		return $this->firstName . ' ' . 
			   ( $this->nick != '' ? $this->nick . ' ' : '' ) . 
			   $this->lastName;
	}
	
	public function getAvatarUrl() {
		return $this->avatarUrl;
	}
	
	public function getLinkedName() {
		return $this->getName();
	}
	
	public function getLastVisit() {
		return self::getDateStr( $this->lastVisit );
	}
	
	public function getRegDate() {
		return self::getDateStr( $this->regDate );
	}
	
	public function canCreateProject() {
		return $this->isModerator();
	}
	
	public function isAdmin() {
		return $this->role == self::ROLE_ADMIN;
	}
	
	public function isModerator() {
		return $this->isAdmin() || $this->role == self::ROLE_MODERATOR;
	}
	
	public function checkRole( $reqRole ) {
		return self::checkCurRole( $this->role, $reqRole );
	}
	
	public function parseData( $hash )
	{
		if (!parent::parseData( $hash )) return false;
		
		$this->pref->parseData( $hash );
		
		if ($this->avatarUrl == '') $this->avatarUrl = $this->getMyGravatar();
		
		return true;
	}
	
	protected function clientObjectCreated( $obj ) {
		$obj = parent::clientObjectCreated( $obj );
		
		$obj['linkedName'] = $this->getLinkedName();
		return $obj;
	} 
	
	private function getMyGravatar() {
		return $this->getGravatar( $this->email );
	} 
	
	/**
	 * Get either a Gravatar URL or complete image tag for a specified email address.
	 *
	 * @param string $email The email address
	 * @param string $s Size in pixels, defaults to 80px [ 1 - 512 ]
	 * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
	 * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
	 * @param boole $img True to return a complete IMG tag False for just the URL
	 * @param array $atts Optional, additional key/value attributes to include in the IMG tag
	 * @return String containing either just a URL or a complete image tag
	 * @source http://gravatar.com/site/implement/images/php/
	 */
	private function getGravatar( 
								  $email, $s = 80, $d = 'mm', $r = 'g', 
								  $img = false, $atts = array() 
								) 
	{
		$url = 'http://www.gravatar.com/avatar/';
		$url .= md5( strtolower( trim( $email ) ) );
		$url .= "?s=$s&d=$d&r=$r";
		
		if ($img) {
			$url = '<img src="' . $url . '"';
			foreach ($atts as $key => $val)
			$url .= ' ' . $key . '="' . $val . '"';
			$url .= ' />';
		}
		return $url;
	}
	
}
?>