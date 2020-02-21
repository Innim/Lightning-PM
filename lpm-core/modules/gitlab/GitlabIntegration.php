<?php
/**
 * Интеграция с GitLab.
 */
class GitlabIntegration {
	const URL_MR_SUBPATH = 'merge_requests/';

	private static $_instance;
	/**
	 * @return SlackIntegration
	 */
	public static function getInstance(/*User */$user) {
		if (self::$_instance === null) {
			// TODO: проверка на пустоту и существование?
			// TODO: токен юзера
			$userToken = $user == null || empty($user->gitlabToken) ? null : $user->gitlabToken;
			self::$_instance = new GitlabIntegration(GITLAB_URL, $userToken, GITLAB_TOKEN, GITLAB_SUDO_USER);

			// Если токена нет, то имеет смысл его создать
			if ($userToken == null && $user != null) {
				// TODO: нужен механизм, чтобы не каждый раз запрашивался токен, если не удается создать
				// потому что могут быть пользователи, не подключенные к репозиториям
				$token = self::$_instance->sudoCreateUserToken($user);
				if ($token)
					self::$_instance->setUserToken($token);
			}
		}

		return self::$_instance;
	}

	private $_url;
	private $_token;
	private $_sudoToken;
	private $_sudoUser;

	private $_client;
	private $_sudoClient;

	function __construct($url, $userToken, $sudoToken, $sudoUser) {
		$this->_url = $url;
		$this->_token = $userToken;
		$this->_sudoToken = $sudoToken;
		$this->_sudoUser = $sudoUser;
	}

	/**
	 * Устаналивает токен пользователя.
	 * @param string $token Токен
	 */
	public function setUserToken($token) {
		$this->_token = $userToken;
	}

	/**
	 * Можно ли делать пользовательские (не sudo) запросы.
	 * @return boolean [description]
	 */
	public function isAvailableForUser() {
		return $this->client() != null;
	}

	/**
	 * Создает GitLab токен для пользователя и записывает его в БД
	 * @param  User   $user Пользователь
	 * @return string       Созданный токен
	 */
	public function sudoCreateUserToken(User $user) {
		$gitlabUser = $this->sudoGetUserByEmail($user->email);

		if ($gitlabUser == null)
			return false;
		
		$res = $this->sudoClient()->users()->createImpersonationToken(
			$gitlabUser['id'], $this->getTokenName(), ['api']);

		$user->gitlabToken = $res['token'];
		User::updateGitlabToken($user->userId, $user->gitlabToken);

		return $user->gitlabToken;
	}

	/**
	 * Проверяет, является ли url url'ом merge request'а.
	 * @param  string  $url URL
	 * @return boolean true, если является, иначе false.
	 */
	public function isMRUrl($url) {
		return strpos($url, $this->_url) === 0 && strpos($url, self::URL_MR_SUBPATH) !== false;
	}

	/**
	 * Загружает данные о Merge Request.
	 * @param  string $url URL merge request'а
	 * @return GitlabMergeRequest|null Данные MR или null, если не удалось загрузить данные.
	 */
	public function getMR($url) {
		$parts = parse_url($url);
		$path = $parts['path'];
		$mrPos = strpos($path, self::URL_MR_SUBPATH);
		if ($mrPos === false)
			return null;

		$projectPath = mb_substr($path, 1, $mrPos - 2);
		$mrId = intval(mb_substr($path, $mrPos + mb_strlen(self::URL_MR_SUBPATH)));

		$client = $this->client();
		if ($client == null)
			return null;
		
		$res = $client->mergeRequests()->show($projectPath, $mrId);
		return $res === null ? null : new GitlabMergeRequest($res);
	}

	private function sudoGetUserByEmail($email) {
		$res = $this->sudoClient()->users()->all(['search' => $email]);
		return empty($res) ? null : $res[0];
	}

	private function client() {
		if ($this->_client === null && $this->_token !== null) {
			$this->_client = \Gitlab\Client::create($this->_url)->authenticate(
				$this->_token, \Gitlab\Client::AUTH_URL_TOKEN);
		}

		return $this->_client;
	}

	private function sudoClient() {
		if ($this->_sudoClient === null) {
			$this->_sudoClient = \Gitlab\Client::create($this->_url)->authenticate(
				$this->_sudoToken, \Gitlab\Client::AUTH_URL_TOKEN, $this->_sudoUser);
		}

		return $this->_sudoClient;
	}

	private function getTokenName() {
		return 'Lightning PM at ' . SITE_URL;
	}
}