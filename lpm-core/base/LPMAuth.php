<?php
class LPMAuth {
	/**
	 * @return cookieHash
	 */
	public function createCookieHash() {
		return md5(BaseString::randomStr());
	}

	/**
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
    /**
     * @var int
     */
    private $_hashId = 0;

	function __construct($sessionId = null)
	{
		Session::getInstance($sessionId);
		$siteURL = parse_url(SITE_URL);

		//соответствует ли куки-путь пути сайта
		if ($siteURL !== false && !empty($siteURL['path']))
			$this->_cookiePath = $siteURL['path'];

		$this->parseSession();

		self::$current = $this;
	}

    /**
     * Инициализация авторизации
     * @param float $userId
     * @param $email
     * @param string $cookieHash
     * @param null $hashId
     */
	public function init($userId, $email, $cookieHash = '', $hashId = null) {
		$this->_userId  = $userId;
		$this->_email   = $email;
		$this->_isLogin = true;
        $this->_hashId = $hashId;
		$expire = DateTimeUtils::$currentDate + LPMOptions::getInstance()->cookieExpire; // на месяцок

		if ($cookieHash != '') {
			$this->setCookie(self::COOKIE_USER_ID, $userId    , $expire);
			$this->setCookie(self::COOKIE_HASH   , $cookieHash, $expire);
		}

		$this->removeExpiredHash();
		$this->updateSession();

        $db = LPMGlobals::getInstance()->getDBConnect();
        $userAgent = $db->real_escape_string($_SERVER['HTTP_USER_AGENT']);

		if ($hashId != null) {
			$sqlUserData = "update `%s` set `cookieHash`='". $cookieHash ."',`userAgent`='". $userAgent ."',
				`hasCreated`='". DateTimeUtils::mysqlDate() ."' where `userId` = '". $userId ."' and `id` = '". $hashId ."'";
			$db->queryt($sqlUserData, LPMTables::USER_AUTH);
		} else {
            $sqlCookie = "insert into `%s`(`cookieHash`,`userId`,`userAgent`,`hasCreated`) 
                          values ('". $cookieHash ."','". $userId . "','". $userAgent ."','". DateTimeUtils::mysqlDate() ."')";
            if (!$db->queryt($sqlCookie, LPMTables::USER_AUTH)) {
                $this->addError('Ошибка при сохранении данных авторизации');
            } else {
                $this->_hashId = $db->insert_id;

                // еще раз сохраняем, т.к. hashId обновился
                $this->updateSession();
            }
        }
	}

	public function destroy() {
        // удаляем устаревшие
		$this->removeExpiredHash($this->_hashId); // и текущую
		$this->_isLogin = false;
		$this->setCookie(self::COOKIE_USER_ID, '');
		$this->setCookie(self::COOKIE_HASH   , '');
		Session::getInstance()->unsetVar(self::SESSION_NAME);
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

	private function setCookie($name, $value, $expire = 0) {
		setcookie($name, $value, $expire, $this->_cookiePath);
	}

    /**
     * Удаляет старый хэш из базы
     * @param int $withHashId если > 0, то и запись с этим идентификатором
     */
	private function removeExpiredHash($withHashId = 0) {
		$db = LPMGlobals::getInstance()->getDBConnect();
		// проверяем, есть ли просроченный хэш по дате его создания
		$expire = DateTimeUtils::$currentDate - LPMOptions::getInstance()->cookieExpire/* * 2*/; // убрал, не знаю зачем здесь на 2 умножалось
        $currentHashIdCondition = ($withHashId > 0 ? "`id` = '$withHashId' or " : "");
        $sql = "delete from `%s` where (". $currentHashIdCondition. "`hasCreated` < '" . DateTimeUtils::mysqlDate($expire) .
            "') and `userId` = '" . $this->_userId . "'";
		$db->queryt($sql, LPMTables::USER_AUTH);
	}

	private function parseSession() {
		// если сессия живет
		if ($data = unserialize(Session::getInstance()->get(self::SESSION_NAME))) {
			$this->_userId  = (float)$data['uid'];
			$this->_email   = $data['email'];
            $this->_hashId  = (float)$data['hashId'];
			$this->_isLogin = true;
		} else if (!empty($_COOKIE[self::COOKIE_USER_ID]) && !empty($_COOKIE[self::COOKIE_HASH])) {
			// иначе пытаемся авторизоваться по кукам
			$db = LPMGlobals::getInstance()->getDBConnect();
			$hash = $db->real_escape_string($_COOKIE[self::COOKIE_HASH]);
            $expire = DateTimeUtils::$currentDate - LPMOptions::getInstance()->cookieExpire;
			$sql = "select `%1\$s`.`userId`,`%1\$s`.`email`,`%2\$s`.`id` from `%1\$s`". " 
						left join `%2\$s` on `%1\$s`.`userId` = `%2\$s`.`userId` " .
						   	"where `%2\$s`.`cookieHash` = '". $hash ."' ".
                                "and `%2\$s`.`hasCreated` >= '" . DateTimeUtils::mysqlDate($expire) . "' " .
						   		"and `%1\$s`.`userId` = '". (float)$_COOKIE[self::COOKIE_USER_ID] ."' limit 0, 1";
			if ($query = $db->queryt($sql, LPMTables::USERS, LPMTables::USER_AUTH)) {
				if ($data = $query->fetch_assoc()) {
					// создаем новый хэш
					$hash = self::createCookieHash();
					// авторизация с новым хэшем
					$this->init($data['userId'], $data['email'], $hash, $data['id']);
				} else {
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
					'uid'    => $this->_userId,
					'email'  => $this->_email,
                    'hashId' => $this->_hashId
				)
			)
		);
	}
}