<?php
class LPMAuth {
	/**
	 * 
	 * 
	 * @var LPMAuth
	 */
	public static $current;
	
	const SESSION_NAME   = 'lightning_auth';
	const COOKIE_USER_ID = 'uid';
	const COOKIE_HASH    = 'authHash';
	
	/**
	 * @var boolean
	 */		
	private $_isLogin = false;
	/**
	 * @var float
	 */
	private $_userId = 0;
	private $_email = '';
	private $_cookiePath = '//';

	
	function __construct()
	{
		Session::getInstance()->startSession();
		
		$this->parseSession();
		
		$siteURL = parse_url( SITE_URL );
		if ($siteURL !== false && !empty( $siteURL['path'] )) {
			$this->_cookiePath = $siteURL['path'];
		}

		self::$current = $this;
	}
	
	/**
	 * Инициализация авторизации
	 * @param float $userId
	 */
	public function init( $userId, $email, $cookieHash = '' ) {
		$this->_userId  = $userId;
		$this->_email   = $email;
		$this->_isLogin = true;

		if ($cookieHash != '') {
			$expire = DateTimeUtils::$currentDate + 2592000; // на месяцок
			$this->setCookie( self::COOKIE_USER_ID, $userId    , $expire );
			$this->setCookie( self::COOKIE_HASH   , $cookieHash, $expire );
		}
		
		$this->updateSession();


		$sql = "update `%s` set `lastVisit` = '" . DateTimeUtils::mysqlDate() . "' where `userId` = '" . $userId . "'";
		 
		$db = LPMGlobals::getInstance()->getDBConnect();
		$db->queryt( $sql, LPMTables::USERS );

	}	
	
	public function destroy() {
		$this->_isLogin = false;
		$this->setCookie( self::COOKIE_USER_ID, '' );
		$this->setCookie( self::COOKIE_HASH   , '' );
		Session::getInstance()->unsetVar( self::SESSION_NAME );
		Session::getInstance()->destroy();
	} 
	
	/**
	 * Определяет авторизован ли пользователь
	 * @return boolean
	 */
	public function isLogin() {
		return $this->_isLogin;
	}
	
	/**
	* Определяет идентификатор авторизованного пользователя
	* @return boolean
	*/
	public function getUserId() {
		return $this->_userId;
	}
	
	private function setCookie( $name, $value, $expire = 0 ) {
		setcookie( $name, $value, $expire, $this->_cookiePath );
	} 
	
	private function parseSession() {
		$db = LPMGlobals::getInstance()->getDBConnect();
		//берем время последнего визита юзера
		$sql = "select `lastVisit` as `visit` from `%s` where `userId` = '" . (float)$_COOKIE[self::COOKIE_USER_ID] . "' limit 0,1";
		
		if ($query = $db->queryt( $sql, LPMTables::USERS  )) {
			if ( $lastVisit = $query->fetch_assoc()['visit'] ) {
				$lastVisit = DateTimeUtils::convertMysqlDate($lastVisit);
				//прибавляем к нему время жизни хэша из опций
				$lastVisit += LPMOptions::getInstance()->cookieExpire;
				//если время жизни хэша истекло
				if (DateTimeUtils::$currentDate > $lastVisit) {
					//удаляем хэш(не даем авторизоваться по кукам,если сессия умерла)
					$sql = "delete from `%s` where `userId`='". (float)$_COOKIE[self::COOKIE_USER_ID] ."' and `cookieHash`='".$_COOKIE[self::COOKIE_HASH]."'";
					$db->queryt( $sql, LPMTables::COOKIE  );
				}
			}
		}

		if ($data = unserialize( Session::getInstance()->get( self::SESSION_NAME ) )) {
			$this->_userId  = (float)$data['uid'];
			$this->_email   = $data['email'];
			$this->_isLogin = true;
		} else if(!empty( $_COOKIE[self::COOKIE_USER_ID] ) 
			&& !empty( $_COOKIE[self::COOKIE_HASH] )) {
			// пытаемся авторизоваться по кукам
			$db = LPMGlobals::getInstance()->getDBConnect();
			$hash = $db->escape_string( $_COOKIE[self::COOKIE_HASH] );
			$sql = "select `%1\$s`.`userId`, `%1\$s`.`email` from `%1\$s` INNER JOIN `%2\$s` ON `%1\$s`.`userId` = `%2\$s`.`userId` " .
				    "where `%2\$s`.`cookieHash` = '" . $hash . "' " .
						"and `%1\$s`.`userId` = '" . (float)$_COOKIE[self::COOKIE_USER_ID] . "' limit 0,1";
			if ($query = $db->queryt( $sql, LPMTables::USERS, LPMTables::COOKIE  )) {

				if ($data = $query->fetch_assoc()) 	{		
					$this->init( $data['userId'], $data['email'] );}
				else $this->destroy();
			}
		}
	}
	
	private function updateSession()
	{
		Session::getInstance()->set( 
			self::SESSION_NAME, 
			serialize( 
				array( 
					'uid'   => $this->_userId,
					'email' => $this->_email
				) 
			) 
		);
	}
}