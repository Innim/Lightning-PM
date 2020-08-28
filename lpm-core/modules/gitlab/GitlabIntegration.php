<?php
/**
 * Интеграция с GitLab.
 */
class GitlabIntegration
{
    const URL_MR_SUBPATH = 'merge_requests/';

    private static $_instance;
    /**
     * @return SlackIntegration
     */
    public static function getInstance(/*User */$user)
    {
        if (self::$_instance === null) {
            $userToken = $user == null || empty($user->gitlabToken) ? null : $user->gitlabToken;
            self::$_instance = new GitlabIntegration(
                defined('GITLAB_URL') ? GITLAB_URL : '',
                $userToken,
                defined('GITLAB_TOKEN') ? GITLAB_TOKEN : '',
                defined('GITLAB_SUDO_USER') ? GITLAB_SUDO_USER : ''
            );

            // Если токена нет, то имеет смысл его создать
            if ($userToken == null && $user != null) {
                // TODO: нужен механизм, чтобы не каждый раз запрашивался токен, если не удается создать
                // потому что могут быть пользователи, не подключенные к репозиториям
                $token = self::$_instance->sudoCreateUserToken($user);
                if ($token) {
                    self::$_instance->setUserToken($token);
                }
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

    public function __construct($url, $userToken, $sudoToken, $sudoUser)
    {
        $this->_url = $url;
        $this->_token = $userToken;
        $this->_sudoToken = $sudoToken;
        $this->_sudoUser = $sudoUser;
    }

    /**
     * Устаналивает токен пользователя.
     * @param string $token Токен
     */
    public function setUserToken($token)
    {
        $this->_token = $token;
    }

    /**
     * Доступна ли интеграция.
     */
    public function isAvailable()
    {
        return !empty($this->_url) && !empty($this->_sudoToken) && !empty($this->_sudoUser);
    }

    /**
     * Можно ли делать пользовательские (не sudo) запросы.
     * @return boolean [description]
     */
    public function isAvailableForUser()
    {
        return $this->isAvailable() && $this->client() != null;
    }

    /**
     * Создает GitLab токен для пользователя и записывает его в БД
     * @param  User   $user Пользователь
     * @return string       Созданный токен
     */
    public function sudoCreateUserToken(User $user)
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $gitlabUser = $this->sudoGetUserByEmail($user->email);

        if ($gitlabUser == null) {
            return false;
        }
        
        try {
            $res = $this->sudoClient()->users()->createImpersonationToken(
                $gitlabUser['id'],
                $this->getTokenName(),
                ['api']
            );

            $user->gitlabToken = $res['token'];
            User::updateGitlabToken($user->userId, $user->gitlabToken);

            return $user->gitlabToken;
        } catch (Exception $e) {
            GMLog::writeLog('Exception during ' . __METHOD__ . ': ' . $e);
            return null;
        }
    }

    /**
     * Проверяет, является ли url url'ом merge request'а.
     * @param  string  $url URL
     * @return boolean true, если является, иначе false.
     */
    public function isMRUrl($url)
    {
        return strpos($url, $this->_url) === 0 && strpos($url, self::URL_MR_SUBPATH) !== false;
    }

    /**
     * Загружает данные о Merge Request.
     * @param  string $url URL merge request'а
     * @return GitlabMergeRequest|null Данные MR или null, если не удалось загрузить данные.
     */
    public function getMR($url)
    {
        $parts = parse_url($url);
        $path = $parts['path'];
        $mrPos = strpos($path, self::URL_MR_SUBPATH);
        if ($mrPos === false) {
            return null;
        }

        $projectPath = trim(mb_substr($path, 0, $mrPos), ' -/');
        $mrId = intval(mb_substr($path, $mrPos + mb_strlen(self::URL_MR_SUBPATH)));

        $client = $this->client();
        if ($client == null) {
            return null;
        }
        try {
            $res = $client->mergeRequests()->show($projectPath, $mrId);
            return $res === null ? null : new GitlabMergeRequest($res);
        } catch (Exception $e) {
            GMLog::writeLog('Exception during ' . __METHOD__ . ': ' . $e);
            return null;
        }
    }

    /// Возвращает список проектов по идентификатору группы.
    public function getProjects($groupId)
    {
        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $list = $client->groups()->projects($groupId);
            $res = [];
            foreach ($list as $data) {
                $res[] = new GitlabProject($data);
            }
            return $res;
        } catch (Exception $e) {
            GMLog::writeLog('Exception during ' . __METHOD__ . ': ' . $e);
            return null;
        }
    }

    private function sudoGetUserByEmail($email)
    {
        try {
            $res = $this->sudoClient()->users()->all(['search' => $email]);
            return empty($res) ? null : $res[0];
        } catch (Exception $e) {
            GMLog::writeLog('Exception during ' . __METHOD__ . ': ' . $e);
            return null;
        }
    }

    private function client()
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if ($this->_client === null && $this->_token !== null) {
            $this->_client = \Gitlab\Client::create($this->_url)->authenticate(
                $this->_token,
                \Gitlab\Client::AUTH_URL_TOKEN
            );
        }

        return $this->_client;
    }

    private function sudoClient()
    {
        if (!$this->isAvailable()) {
            return null;
        }
        
        if ($this->_sudoClient === null) {
            $this->_sudoClient = \Gitlab\Client::create($this->_url)->authenticate(
                $this->_sudoToken,
                \Gitlab\Client::AUTH_URL_TOKEN,
                $this->_sudoUser
            );
        }

        return $this->_sudoClient;
    }

    private function getTokenName()
    {
        return 'Lightning PM at ' . SITE_URL;
    }
}
