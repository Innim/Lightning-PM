<?php
/**
 * Интеграция с GitLab.
 */
class GitlabIntegration
{
    const URL_MR_SUBPATH = 'merge_requests/';
    const URL_PIPELINE_SUBPATH = 'pipelines/';
    const URL_JOB_SUBPATH = 'jobs/';

    private static $_instance;
    /**
     * @return GitlabIntegration
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
     * Устанавливает токен пользователя.
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
            // нельзя делать токен дольше, чем на год
            $expiresAt = date('Y-m-d', strtotime('+364 days')); 
            $res = $this->sudoClient()->users()->createImpersonationToken(
                $gitlabUser['id'],
                $this->getTokenName(),
                ['api'],
                $expiresAt
            );

            $user->gitlabToken = $res['token'];
            $user->gitlabId = $gitlabUser['id'];
            User::updateGitlabToken($user->userId, $user->gitlabToken, $user->gitlabId);

            return $user->gitlabToken;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает список вебхуков репозитория.
     *
     * Управление хуками требует роли Maintainer или Owner на репозитории,
     * поэтому запрос всегда идет от sudo пользователя, а не от текущего:
     * у обычного разработчика прав не хватит.
     *
     * Список собирается по всем страницам: поиск своего хука по неполному
     * списку завел бы второй такой же.
     *
     * @param  int|string $projectId Идентификатор проекта на GitLab.
     * @return array Список хуков в том виде, в каком их отдает GitLab.
     * @throws Exception Если запрос не удался; код исключения - HTTP статус
     * ответа GitLab (401/403 - недостаточно прав).
     */
    public function sudoGetProjectHooks($projectId)
    {
        $client = $this->requireSudoClient();

        $perPage = 100;
        $page = 0;
        $res = [];

        do {
            $page++;
            $list = $client->projects()->hooks($projectId, [
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if (empty($list)) {
                break;
            }

            $res = array_merge($res, $list);
        } while (count($list) == $perPage);

        return $res;
    }

    /**
     * Создает вебхук на репозитории.
     *
     * @param  int|string $projectId Идентификатор проекта на GitLab.
     * @param  string     $url       Адрес, на который GitLab будет слать события.
     * @param  array      $params    Параметры хука (флаги событий, `token`
     *                               и прочее в терминах GitLab API).
     * @return array Созданный хук.
     * @throws Exception Если запрос не удался; код исключения - HTTP статус
     * ответа GitLab.
     */
    public function sudoAddProjectHook($projectId, $url, array $params)
    {
        return $this->requireSudoClient()->projects()->addHook($projectId, $url, $params);
    }

    /**
     * Обновляет существующий вебхук репозитория.
     *
     * @param  int|string $projectId Идентификатор проекта на GitLab.
     * @param  int        $hookId    Идентификатор хука.
     * @param  array      $params    Параметры хука; GitLab заменяет настройки
     *                               целиком, поэтому передавать надо все
     *                               значимые поля, включая `url`.
     * @return array Обновленный хук.
     * @throws Exception Если запрос не удался; код исключения - HTTP статус
     * ответа GitLab.
     */
    public function sudoUpdateProjectHook($projectId, $hookId, array $params)
    {
        return $this->requireSudoClient()->projects()->updateHook($projectId, $hookId, $params);
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
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает данные Pipeline по URL.
     * @param string $url URL пайплайна вида /group/project/-/pipelines/{id} или /group/project/pipelines/{id}
     * @return GitlabPipeline|null
     */
    public function getPipeline($url)
    {
        $parts = parse_url($url);
        if (empty($parts) || empty($parts['path'])) {
            return null;
        }

        $path = $parts['path'];
        $pos = strpos($path, self::URL_PIPELINE_SUBPATH);
        if ($pos === false) {
            return null;
        }

        // "-" сегмент в пути ("/-/") будет обрезан с обоих концов
        $projectPath = trim(mb_substr($path, 0, $pos), ' -/');
        $pipelineId = intval(mb_substr($path, $pos + mb_strlen(self::URL_PIPELINE_SUBPATH)));

        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $res = $client->projects()->pipeline($projectPath, $pipelineId);
            return $res === null ? null : new GitlabPipeline($res);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает данные Job по URL.
     * @param string $url URL джобы вида /group/project/-/jobs/{id}
     * @return GitlabJob|null
     */
    public function getJob($url)
    {
        $parts = parse_url($url);
        if (empty($parts) || empty($parts['path'])) {
            return null;
        }

        $path = $parts['path'];
        // Берем последнее вхождение маркера: идентификатор джобы всегда следует
        // за ним, а сегмент проекта/группы выше по пути может называться "jobs".
        $pos = strrpos($path, self::URL_JOB_SUBPATH);
        if ($pos === false) {
            return null;
        }

        // "-" сегмент в пути ("/-/") будет обрезан с обоих концов
        $projectPath = trim(mb_substr($path, 0, $pos), ' -/');
        $jobId = intval(mb_substr($path, $pos + mb_strlen(self::URL_JOB_SUBPATH)));

        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $res = $client->jobs()->show($projectPath, $jobId);
            return $res === null ? null : new GitlabJob($res);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает данные проекта по идентификатору проекта.
     *
     * @return GitlabProject
     */
    public function getProject($projectId)
    {
        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $res = $client->projects()->show($projectId);
            return new GitlabProject($res);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает список проектов по идентификатору группы.
     */
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
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает список веток репозитория проекта.
     */
    public function getBranches($projectId, $search = null)
    {
        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $page = 0;
            $perPage = 100;
            $res = [];

            $params = [
                'per_page' => $perPage,
            ];
            if (!empty($search)) {
                $params['search'] = $search;
            }

            do {
                $page++;
                $params['page'] = $page;
                $list = $client->repositories()->branches($projectId, $params);
            
                foreach ($list as $data) {
                    $res[] = new GitlabBranch($data);
                }
            } while (count($list) == $perPage);
            return $res;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Создает ветку на репозитории.
     * @param $projectId Идентификатор проекта на GitLab.
     * @param $parent Имя родительской ветки.
     * @param $name Имя создаваемой ветки.
     * @return GitlabBranch|false
     */
    public function createBranch($projectId, $parent, $name)
    {
        $client = $this->client();
        if ($client == null) {
            return false;
        }

        try {
            $res = $client->repositories()->createBranch($projectId, $name, $parent);
            return new GitlabBranch($res);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return false;
        }
    }

    /**
     * Удаляет ветку на репозитории.
     * @param int|string $projectId Идентификатор проекта на GitLab.
     * @param string $name Имя удаляемой ветки.
     * @return bool true в случае успеха, иначе false.
     */
    public function deleteBranch($projectId, $name)
    {
        $client = $this->client();
        if ($client == null) {
            return false;
        }

        try {
            $client->repositories()->deleteBranch($projectId, $name);
            return true;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return false;
        }
    }

    /**
     * Сравнивает два коммита/ветки/тега и возвращает
     * актуальный коммит в ветку $toShaOrBranch.
     * @param $projectId Идентификатор проекта на GitLab.
     * @param $fromShaOrBranch SHA коммита или имя ветки/тега.
     * @param $toShaOrBranch SHA коммита или имя ветки/тега.
     * @return GitlabCommit|null|false Если в ветке $toShaOrBranch
     * нет изменений, которые не присутствуют в ветке $fromShaOrBranch,
     * то вернется null. В случае ошибки вернется false.
     */
    public function compareBranchesAndGetCommit($projectId, $fromShaOrBranch, $toShaOrBranch)
    {
        $client = $this->client();
        if ($client == null) {
            return false;
        }

        try {
            $res = $client->repositories()->compare($projectId, $fromShaOrBranch, $toShaOrBranch);
            return $res ? new GitlabCommit($res['commit']) : false;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return false;
        }
    }

    /**
     * Создает комментарий к MR.
     * @param $projectId Идентификатор проекта на GitLab.
     * @param $mrId Внутренний идентификатор MR на GitLab.
     * @param $text Текст комментария.
     */
    public function createMRNote($projectId, $mrInternalId, $text)
    {
        $client = $this->client();
        if ($client == null) {
            return false;
        }

        try {
            $res = $client->mergeRequests()->addNote($projectId, $mrInternalId, $text);
            return $res;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return false;
        }
    }

    /**
     * Возвращает файлы, изменённые в merge request'е.
     *
     * Пути возвращаются, только если их не больше `$maxFiles`: у крупного MR
     * список перестаёт что-либо говорить о сути изменений. Количество файлов
     * возвращается всегда.
     *
     * @param  int|string $projectId      Идентификатор проекта на GitLab.
     * @param  int        $mrInternalId   Внутренний идентификатор MR на GitLab.
     * @param  int        $maxFiles       Порог, до которого возвращаются пути.
     * @return array|null <code>['count' => int, 'files' => array<string>]</code>
     * или null, если данные получить не удалось.
     */
    public function getMRChangedFiles($projectId, $mrInternalId, $maxFiles)
    {
        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $res = $client->mergeRequests()->changes($projectId, $mrInternalId);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }

        if (empty($res['changes']) || !is_array($res['changes'])) {
            return ['count' => 0, 'files' => []];
        }

        $files = [];
        foreach ($res['changes'] as $change) {
            $path = empty($change['new_path']) ? ($change['old_path'] ?? '') : $change['new_path'];
            if ($path !== '') {
                $files[] = $path;
            }
        }

        return [
            'count' => count($files),
            'files' => count($files) > $maxFiles ? [] : $files,
        ];
    }

    /**
     * Получает информацию о последнем пайплайне для проекта.
     * @param int|string $projectId Идентификатор проекта на GitLab.
     * @param string $ref Ветка или тег, для которого нужно получить пайплайн.
     * @param bool $useSudo Использовать ли sudo пользователя или текущего.
     * @return GitlabPipeline|null
     */
    public function getLatestPipeline($projectId, $ref, $useSudo = false) {
        $client = $useSudo ? $this->sudoClient() : $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $projects = new GitlabClientProjectsExt($client);
            $res = $projects->pipelineLatest($projectId, $ref);
            if (empty($res)) {
                return null;
            }
            return new GitlabPipeline($res);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    /**
     * Возвращает пайплайн, запущенный для конкретного коммита ветки.
     *
     * @param int|string $projectId Идентификатор проекта на GitLab.
     * @param string $ref Ветка или тег, в которой находится коммит.
     * @param string $sha SHA коммита.
     * @return GitlabPipeline|null Самый свежий пайплайн этого коммита или null,
     * если пайплайна нет либо данные получить не удалось.
     */
    public function getPipelineForCommit($projectId, $ref, $sha)
    {
        if (empty($ref) || empty($sha)) {
            return null;
        }

        $client = $this->client();
        if ($client == null) {
            return null;
        }

        try {
            $res = $client->projects()->pipelines($projectId, [
                'ref' => $ref,
                'sha' => $sha,
                'order_by' => 'id',
                'sort' => 'desc',
                'per_page' => 1,
            ]);

            return empty($res) ? null : new GitlabPipeline($res[0]);
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e);
            return null;
        }
    }

    private function sudoGetUserByEmail($email)
    {
        try {
            $res = $this->sudoClient()->users()->all(['search' => $email]);
            return empty($res) ? null : $res[0];
        } catch (Exception $e) {
            $this->logException($e, 'Exception during ' . __METHOD__);
            return null;
        }
    }

    /**
     * @return \Gitlab\Client
     */
    private function client()
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if ($this->_client === null && $this->_token !== null) {
            $client = new Gitlab\Client();
            $client->setUrl($this->_url);
            $client->authenticate($this->_token, Gitlab\Client::AUTH_HTTP_TOKEN);

            $this->_client = $client;
        }

        return $this->_client;
    }

    /**
     * @return \Gitlab\Client
     */
    private function sudoClient()
    {
        if (!$this->isAvailable()) {
            return null;
        }
        
        if ($this->_sudoClient === null) {
            $client = new Gitlab\Client();
            $client->setUrl($this->_url);
            $client->authenticate($this->_sudoToken, Gitlab\Client::AUTH_HTTP_TOKEN, $this->_sudoUser);

            $this->_sudoClient = $client;
        }

        return $this->_sudoClient;
    }

    /**
     * @return \Gitlab\Client
     * @throws Exception Если интеграция не настроена.
     */
    private function requireSudoClient()
    {
        $client = $this->sudoClient();
        if ($client === null) {
            throw new Exception('Интеграция с GitLab не настроена');
        }

        return $client;
    }

    private function getTokenName()
    {
        return 'Lightning PM at ' . SITE_URL;
    }

    private function onCallException(String $method, Exception $e)
    {
        $this->logException($e, 'Exception during ' . $method);
    }

    private function logException(Exception $e, $message = '')
    {
        $context = empty($message) ? [] : ['context' => $message];
        LPMLog::exception($e, LPMLog::CH_GITLAB, $context);
    }
}
