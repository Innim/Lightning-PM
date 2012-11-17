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
		if ($data = unserialize( Session::getInstance()->get( self::SESSION_NAME ) )) {
			$this->_userId  = (float)$data['uid'];
			$this->_email   = $data['email'];
			$this->_isLogin = true;
		} else if(!empty( $_COOKIE[self::COOKIE_USER_ID] ) 
					&& !empty( $_COOKIE[self::COOKIE_HASH] )) {
			// пытаемся авторизоваться по кукам
			$db = LPMGlobals::getInstance()->getDBConnect();
			$hash = $db->escape_string( $_COOKIE[self::COOKIE_HASH] );
			$sql = "select `userId`, `email` from `%s` " .
			          "where `cookieHash` = '" . $hash . "' " .
						"and `userId` = '" . (float)$_COOKIE[self::COOKIE_USER_ID] . "' limit 0,1";
			if ($query = $db->queryt( $sql, LPMTables::USERS )) {
				if ($data = $query->fetch_assoc()) 				
					$this->init( $data['userId'], $data['email'] );
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