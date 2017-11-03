<?php
class AuthPage extends BasePage
{
	const SESSION_REDIRECT = 'lightning_redirect';

	function __construct() {
		parent::__construct('auth', 'Авторизация', false, true);
		
		$this->_pattern = 'auth';
		
		array_push( $this->_js, 'auth' );
	}
	
	public function init() {
		if (!parent::init())
			return false;

		$engine = LightningEngine::getInstance();

		// проверяем, не пришли ли данные формы
		if (count( $_POST ) > 0) {
			foreach ($_POST as $key => $value) {
				$_POST[$key] = trim( $value );
			}

			if (isset( $_POST['email'] )) {
				// регистрация 				
				if (empty( $_POST['email'] ) || empty( $_POST['pass'] ) || empty( $_POST['repass'] )
					|| empty( $_POST['firstName'] ) || empty( $_POST['lastName'] ))  {
					$engine->addError( 'Заполнены не все обязательные поля' );					
				} elseif ($_POST['pass'] != $_POST['repass']) {
					$engine->addError( 'Введённые пароли не совпадают' );
				} elseif (!Validation::checkEmail( $_POST['email'] )) {
					$engine->addError( 'Введён некорректный email' );
				} elseif (!Validation::checkPass( $_POST['pass'], 24, 1, true )) {
					$engine->addError( 'Введён недопустимый пароль - используйте латинские буквы, цифры или знаки' );
				} else {
					$pass = User::passwordHash($_POST['pass']);
					unset( $_POST['pass'], $_POST['repass'] );
					
					$_POST['firstName'] = mb_substr( $_POST['firstName'], 0, 128 );
					$_POST['lastName' ] = mb_substr( $_POST['lastName' ], 0, 128 );
					$_POST['nick'     ] = mb_substr( $_POST['nick'     ], 0,  64 );
					
					foreach ($_POST as $key => $value) {
						$_POST[$key] = $this->_db->escape_string( $value );
					}
					
					$cookieHash = $this->createCookieHash();
					
					// пытаемся записать в базу
					$sql = "insert into `%s` ( `email`, `pass`, `firstName`, `lastName`, `nick`, `lastVisit`, `regDate`, `cookieHash` ) " .
									 "values ( '" . $_POST['email'] . "', '" . $pass . "', '" . $_POST['firstName'] . "', " .
									 		  "'" . $_POST['lastName'] . "', '" . $_POST['nick'] . "', '" . DateTimeUtils::mysqlDate() . "', " . 
									 		  "'" . DateTimeUtils::mysqlDate() . "', '" . $cookieHash . "' )";
					if (!$this->_db->queryt( $sql, LPMTables::USERS )) {
						if ($this->_db->errno == 1062) {
							$engine->addError( 'Пользователь с таким email уже зарегистрирован' );
						} else {
							$engine->addError( 'Ошибка записи в базу' );
						}
					} else {
						$userId = $this->_db->insert_id;
						// записываем еще и настройки для пользователя
						$sql = "INSERT INTO `%s` (`userId`) VALUES (" . $userId . ")";
						if (!$this->_db->queryt( $sql, LPMTables::USERS_PREF )) {
							// удаляем  пользователя
							$this->_db->queryt( 
								"DELETE FROM `%s` WHERE `userId` = '" . $userId . "'", 
								LPMTables::USERS 
							);
							$engine->addError( 'Ошибка записи в базу' );
						} else {						
							// пользователь успешно записан в базу - авторизуем его
							$this->auth( $userId, $_POST['email'], $cookieHash );
						}
					}
				}
			} else {
				if (empty( $_POST['aemail'] ) || empty( $_POST['apass'] ))  {
					$engine->addError( 'Введите email и пароль для входа' );
				} else {
					// авторизация
					$pass  = $_POST['apass'];
					$email = $this->_db->escape_string( $_POST['aemail'] );	

					$sql = "select `userId`, `pass`, `locked` from `%s` where `email` = '" . $email . "'";
					if (!$query = $this->_db->queryt( $sql, LPMTables::USERS )) {
						$engine->addError( 'Ошибка чтения из базы' );
					} elseif ($userInfo = $query->fetch_assoc()) {
						if (!User::passwordVerify($pass, $userInfo['pass']))
						{
							$engine->addError( 'Неверный пароль' );
						}
						elseif ($userInfo['locked']) {
                            $engine->addError( 'Пользователь заблокирован' );
                        } else {
                            $cookieHash = LPMAuth::createCookieHash();
							$sqlVisit = "update `%s` set `lastVisit` = '" . DateTimeUtils::mysqlDate() .
                                "' where `userId` = '" . $userInfo['userId'] . "'";

							if (!$this->_db->queryt( $sqlVisit, LPMTables::USERS ))
								$engine->addError( 'Ошибка записи в базу' );
							else {
								$this->auth( $userInfo['userId'], $email, $cookieHash );
							}
                        }  
					} else {
						$engine->addError( 'Пользователь с таким email не зарегистрирован' );
					}
				}
			}
		}
		
		return $this;
	}

	public function printContent() 
	{
		parent::printContent();		
	}
	
	private function createCookieHash() {
		return md5( BaseString::randomStr() );
	}
	
	private function auth( $userId, $email, $cookieHash ) 
	{
		LightningEngine::getInstance()->getAuth()->init(
			$userId, $email, $cookieHash);

		$redirect = Session::getInstance()->get(self::SESSION_REDIRECT);
		if (empty($redirect))
		{
			$redirect = '';
		}
		else 
		{
			Session::getInstance()->unsetVar(self::SESSION_REDIRECT);
		}

		LightningEngine::go2URL($redirect);
		//header( 'Location: ' . SITE_URL );
	}
}