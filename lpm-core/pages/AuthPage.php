<?php
/**
 * Страница авторизации и регистрации.
 */
class AuthPage extends BasePage {
	const SESSION_REDIRECT = 'lightning_redirect';
	/**
	 * Поле для сериализованных в json Open Graph данных.
	 */
	const SESSION_REDIRECT_OG = 'lightning_redirect_og';

	function __construct() {
		parent::__construct('auth', 'Авторизация', false, true);
		
		$this->_pattern = 'auth';
		
		array_push($this->_js, 'auth');
	}
	
	public function init() {
		if (!parent::init())
			return false;

		$engine = LightningEngine::getInstance();

		// проверяем, не пришли ли данные формы
		if (!empty($_POST)) {
			$input = [];
			foreach ($_POST as $key => $value) {
				$input[$key] = trim($value);
			}

			if (isset($input['email'])) {
				// регистрация 				
				if ($this->validateSignUp($engine, $input)) {
					$pass = User::passwordHash($input['pass']);					
					$cookieHash = $this->createCookieHash();
					
					$values = [
						'email' => $input['email'],
						'pass' => $pass,
						'firstName' => mb_substr($input['firstName'], 0, 128),
						'lastName' => mb_substr($input['lastName'], 0, 128),
						'nick' => mb_substr($input['nick'], 0, 64),
						'lastVisit' => DateTimeUtils::mysqlDate(),
						'regDate' => DateTimeUtils::mysqlDate()
					];

					// пытаемся записать в базу
					$sqlHash = [
						'INSERT' => $values,
						'INTO' => LPMTables::USERS
					];

					if (!$this->_db->queryb($sqlHash)) {
						if ($this->_db->errno == 1062) {
							$engine->addError('Пользователь с таким email уже зарегистрирован');
						} else {
							$engine->addError('Ошибка записи в базу');
						}
					} else {
						$userId = $this->_db->insert_id;
						// записываем еще и настройки для пользователя
						$sql = "INSERT INTO `%s` (`userId`) VALUES (" . $userId . ")";
						if (!$this->_db->queryt($sql, LPMTables::USERS_PREF)) {
							// удаляем  пользователя
							$this->_db->queryt(
								"DELETE FROM `%s` WHERE `userId` = '" . $userId . "'", 
								LPMTables::USERS);
							$engine->addError('Ошибка записи в базу');
						} else {						
							// пользователь успешно записан в базу - авторизуем его
							$this->auth($userId, $input['email'], $cookieHash);
						}
					}
				}
			} else {
				if (empty($input['aemail']) || empty($input['apass']))  {
					$engine->addError('Введите email и пароль для входа');
				} else {
					// авторизация
					$pass  = $input['apass'];
					$email = $this->_db->escape_string($input['aemail']);

					$sql = "select `userId`, `pass`, `locked` from `%s` where `email` = '" . $email . "'";
					if (!$query = $this->_db->queryt($sql, LPMTables::USERS)) {
						$engine->addError('Ошибка чтения из базы');
					} elseif ($userInfo = $query->fetch_assoc()) {
						if (!User::passwordVerify($pass, $userInfo['pass'])) {
							$engine->addError('Неверный пароль');
						} elseif ($userInfo['locked']) {
                            $engine->addError('Пользователь заблокирован');
                        } else {
                            $cookieHash = LPMAuth::createCookieHash();
							$sqlVisit = "update `%s` set `lastVisit` = '" . DateTimeUtils::mysqlDate() .
                                "' where `userId` = '" . $userInfo['userId'] . "'";

							if (!$this->_db->queryt($sqlVisit, LPMTables::USERS)) {
								$engine->addError('Ошибка записи в базу');
							} else {
								$this->auth($userInfo['userId'], $email, $cookieHash);
							}
                        }  
					} else {
						$engine->addError('Пользователь с таким email не зарегистрирован');
					}
				}
			}
		} else {
			// Если есть Open Graph данные от страницы пересылки, то используем их
			$redirectOG = Session::getInstance()->get(self::SESSION_REDIRECT_OG);
			if (!empty($redirectOG)) {
				$og = json_decode($redirectOG);
				$this->_openGraph = $og;
			}
		}
		
		return $this;
	}
	
	private function createCookieHash() {
		return md5(BaseString::randomStr());
	}
	
	private function auth($userId, $email, $cookieHash) {
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
	}

	private function validateSignUp($engine, $input) {
		if (empty($input['email']) || empty($input['pass']) || empty($input['repass'])
					|| empty($input['firstName']) || empty($input['lastName'])) {
			return $engine->addError('Заполнены не все обязательные поля');
		}

		if ($input['pass'] != $input['repass']) {
			return $engine->addError('Введённые пароли не совпадают');
		}

		if (!Validation::checkEmail($input['email'])) {
			return $engine->addError('Введён некорректный email');
		} 

		if (!Validation::checkPass($input['pass'], 24, 1, true)) {
			return $engine->addError('Введён недопустимый пароль - используйте латинские буквы, цифры или знаки');
		}

		return true;
	}
}