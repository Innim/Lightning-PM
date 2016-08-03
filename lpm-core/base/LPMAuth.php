<?php
class LPMAuth {
	/**
	 * @return cookieHash
	 */
	public function createCookieHash() {
		return md5( BaseString::randomStr() );
	}

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
		
		$siteURL = parse_url( SITE_URL );
		//соответствует ли куки-путь пути сайта
		if ($siteURL !== false && !empty( $siteURL['path'] ))
			$this->_cookiePath = $siteURL['path'];

		$this->parseSession();
		
		self::$current = $this;
	}
	
	/**
	 * Инициализация авторизации
	 * @param float $userId
	 */
	public function init( $userId, $email, $cookieHash = '', $hashId = null  ) {
		$this->_userId  = $userId;
		$this->_email   = $email;
		$this->_isLogin = true;
		$expire = DateTimeUtils::$currentDate + LPMOptions::getInstance()->cookieExpire; // на месяцок
		if ($cookieHash != '') {
			$this->setCookie( self::COOKIE_USER_ID, $userId    , $expire );
			$this->setCookie( self::COOKIE_HASH   , $cookieHash, $expire );
		}
		
		$this->removeExpiredHash();
		$this->updateSession();
		
		if ($hashId != null) {
			
			$db = LPMGlobals::getInstance()->getDBConnect();
			$userAgent = $db->real_escape_string($_SERVER['HTTP_USER_AGENT']);
			$sqlUserData = "update `%s` set `cookieHash`='". $cookieHash ."',`userAgent`='". $userAgent ."',
				`hasCreated`='". DateTimeUtils::mysqlDate() ."' where `userId` = '". $userId ."' and `id` = '". $hashId ."'";
			$db->queryt( $sqlUserData, LPMTables::USER_AUTH );
		}
	}	
	
	public function destroy() {
		$this->removeExpiredHash();
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

	//Удаляет старый хэш из базы
	private function removeExpiredHash() {
		$db = LPMGlobals::getInstance()->getDBConnect();
		//проверяем, есть ли просроченный хэш по дате его создания
		$expire = DateTimeUtils::$currentDate - LPMOptions::getInstance()->cookieExpire*2;
		$sql = "delete from `%s` where `hasCreated`<'". DateTimeUtils::date( DateTimeFormat::DAY_OF_MONTH_2 . '.' .
			DateTimeFormat::MONTH_NUMBER_2_DIGITS . '.' .
			DateTimeFormat::YEAR_NUMBER_4_DIGITS . ' ' .	
			DateTimeFormat::HOUR_24_NUMBER_2_DIGITS . ':' .
			DateTimeFormat::MINUTES_OF_HOUR_2_DIGITS. ':' . 
			DateTimeFormat::SECONDS_OF_MINUTE_2_DIGITS,
			$expire )  ."' and `userId` = '" . $this->_userId . "'";
		//print_r($sql);
		$db->queryt( $sql, LPMTables::USER_AUTH  );	
	}

	private function parseSession() {
		//если сессия живет
		if ($data = unserialize( Session::getInstance()->get( self::SESSION_NAME ) )) {
			$this->_userId  = (float)$data['uid'];
			$this->_email   = $data['email'];
			$this->_isLogin = true;
		} else if(!empty( $_COOKIE[self::COOKIE_USER_ID] ) 
			&& !empty( $_COOKIE[self::COOKIE_HASH] )) {
			//иначе пытаемся авторизоваться по кукам
			$db = LPMGlobals::getInstance()->getDBConnect();
			$hash = $db->real_escape_string( $_COOKIE[self::COOKIE_HASH] );
			$sql = "select `%1\$s`.`userId`,`%1\$s`.`email`,`%2\$s`.`id` from `%1\$s`". " 
						left join `%2\$s` on `%1\$s`.`userId` = `%2\$s`.`userId` " .
						   	"where `%2\$s`.`cookieHash` = '". $hash ."' ". 
						   		"and `%1\$s`.`userId` = '". (float)$_COOKIE[self::COOKIE_USER_ID] ."' limit 0,1";
			if ($query = $db->queryt( $sql, LPMTables::USERS, LPMTables::USER_AUTH  )) {
				if ($data = $query->fetch_assoc()) {
					//создаем новый хэш
					$hash = self::createCookieHash();
					//авторизация с новым хэшем
					$this->init( $data['userId'], $data['email'], $hash, $data['id']  );
				} 
				else { 
						$this->destroy();
					}
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