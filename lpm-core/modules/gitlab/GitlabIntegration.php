<?php
/**
 * Интеграция с GitLab.
 *
 * Работает двумя клиентами, и в журнал каждый запрос пишется с указанием,
 * каким именно:
 *  - `sudo` - общий токен из конфигурации (`GITLAB_TOKEN`), от имени
 *    `GITLAB_SUDO_USER`; им делается то, на что у обычного пользователя
 *    не хватит прав, в том числе выписка пользовательских токенов;
 *  - `user` - личный токен пользователя, который таск выписывает сам.
 *
 * Пользовательский токен таск чинит без участия человека: перевыписывает
 * заранее, за {@see self::TOKEN_RENEW_BEFORE_DAYS} дней до истечения, а если
 * GitLab всё-таки отказал по токену - перевыписывает и повторяет запрос
 * (см. {@see self::userRequest()}). Общий токен так обновить нельзя - о его
 * истечении предупреждается администратор, см. {@see GitlabSudoTokenStatus}.
 */
class GitlabIntegration
{
    const URL_MR_SUBPATH = 'merge_requests/';
    const URL_PIPELINE_SUBPATH = 'pipelines/';
    const URL_JOB_SUBPATH = 'jobs/';

    /** Запрос идёт личным токеном пользователя - значение поля `client` в журнале. */
    const CLIENT_USER = 'user';
    /** Запрос идёт общим токеном интеграции - значение поля `client` в журнале. */
    const CLIENT_SUDO = 'sudo';

    /**
     * На сколько дней выписывается пользовательский токен.
     *
     * Дольше, чем на год, GitLab токен не выдаёт.
     */
    const TOKEN_LIFETIME_DAYS = 364;

    /**
     * За сколько дней до истечения пользовательский токен перевыписывается.
     *
     * Запас нужен, чтобы токен менялся в спокойном режиме, а не по отказу
     * GitLab посреди пользовательского сценария.
     */
    const TOKEN_RENEW_BEFORE_DAYS = 14;

    /**
     * Предел ожидания ответа при запросе сведений об общем токене, секунды.
     */
    const TOKEN_INFO_TIMEOUT_SEC = 5;

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
                defined('GITLAB_SUDO_USER') ? GITLAB_SUDO_USER : '',
                $user instanceof User ? $user : null
            );

            self::$_instance->ensureUserToken();
        }

        return self::$_instance;
    }

    /**
     * Подпись владельца личного токена для журнала.
     *
     * @param  User|null $user Владелец токена.
     * @return string
     */
    private static function getUserLabel(User $user = null)
    {
        return $user === null ? 'неизвестен' : $user->getPlainName() . ' #' . $user->userId;
    }

    private $_url;
    private $_token;
    private $_sudoToken;
    private $_sudoUser;

    /**
     * Пользователь, которому принадлежит личный токен.
     * @var User|null
     */
    private $_user;

    /**
     * Перевыписывался ли уже токен по отказу GitLab.
     * @var bool
     */
    private $_userTokenRenewed = false;

    private $_client;
    private $_sudoClient;

    /**
     * @param string    $url       Адрес инстанса GitLab.
     * @param string    $userToken Личный токен пользователя или null.
     * @param string    $sudoToken Общий токен интеграции (`GITLAB_TOKEN`).
     * @param string    $sudoUser  Пользователь, от имени которого идут sudo-запросы.
     * @param User|null $user      Владелец личного токена.
     */
    public function __construct($url, $userToken, $sudoToken, $sudoUser, User $user = null)
    {
        $this->_url = $url;
        $this->_token = $userToken;
        $this->_sudoToken = $sudoToken;
        $this->_sudoUser = $sudoUser;
        $this->_user = $user;
    }

    /**
     * Устанавливает токен пользователя.
     * @param string $token Токен
     */
    public function setUserToken($token)
    {
        $this->_token = $token;
        // Клиент держит токен внутри, поэтому с новым токеном он
        // пересоздаётся: иначе запросы продолжат идти со старым
        $this->_client = null;
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
            $expiresAt = date('Y-m-d', strtotime('+' . self::TOKEN_LIFETIME_DAYS . ' days'));
            $res = $this->sudoClient()->users()->createImpersonationToken(
                $gitlabUser['id'],
                $this->getTokenName(),
                ['api'],
                $expiresAt
            );

            // Инстанс вправе сократить запрошенный срок, поэтому запоминается
            // тот, что вернул GitLab, а не тот, что просили
            if (!empty($res['expires_at'])) {
                $expiresAt = substr((string)$res['expires_at'], 0, 10);
            }

            $expiresTs = strtotime($expiresAt);

            $user->gitlabToken = $res['token'];
            $user->gitlabId = $gitlabUser['id'];
            $user->gitlabTokenExpiresAt = $expiresTs === false ? 0 : $expiresTs;
            User::updateGitlabToken($user->userId, $user->gitlabToken, $user->gitlabId, $expiresAt);

            LPMLog::info('Выписан токен GitLab', LPMLog::CH_GITLAB, [
                'client' => self::CLIENT_USER,
                'owner' => self::getUserLabel($user),
                'expiresAt' => $expiresAt,
            ]);

            return $user->gitlabToken;
        } catch (Exception $e) {
            $this->onCallException(__METHOD__, $e, self::CLIENT_SUDO);
            return null;
        }
    }

    /**
     * Срок действия общего токена интеграции (`GITLAB_TOKEN`).
     *
     * @return array|null <code>['expired' => bool, 'expiresAt' => string]</code>,
     * где `expiresAt` - дата в формате `Y-m-d` либо пустая строка у бессрочного
     * токена. Возвращается null, если срок узнать не удалось - в том числе
     * когда у токена нет разрешения читать сведения о самом себе.
     */
    public function getSudoTokenInfo()
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $tokens = new GitlabClientTokensExt($this->tokenInfoClient());
            $res = $tokens->current();
        } catch (Exception $e) {
            if ($this->isInvalidTokenException($e)) {
                LPMLog::error(
                    'GitLab не принял общий токен интеграции: ' . $e->getMessage(),
                    LPMLog::CH_GITLAB,
                    $this->logContext(self::CLIENT_SUDO)
                );

                return ['expired' => true, 'expiresAt' => ''];
            }

            // Сведений о токене может не быть вовсе: у токена нет
            // разрешения «Personal Access Token: Read» (403), инстанс старее
            // 13.5 или токен не личный, а проектный либо групповой. Это
            // не ошибка интеграции - просто срок остаётся неизвестным,
            // и предупредить администратора заранее нечем; требование
            // к правам токена описано в lpm-config.inc.template.php
            LPMLog::warning(
                'Не удалось узнать срок действия общего токена: ' . $e->getMessage(),
                LPMLog::CH_GITLAB,
                $this->logContext(self::CLIENT_SUDO)
            );

            return null;
        }

        if (!is_array($res)) {
            return null;
        }

        return [
            'expired' => !empty($res['revoked']) || (isset($res['active']) && !$res['active']),
            'expiresAt' => empty($res['expires_at']) ? '' : substr((string)$res['expires_at'], 0, 10),
        ];
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

        $res = $this->userRequest(__METHOD__, function ($client) use ($projectPath, $mrId) {
            return $client->mergeRequests()->show($projectPath, $mrId);
        });

        return empty($res) ? null : new GitlabMergeRequest($res);
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

        $res = $this->userRequest(__METHOD__, function ($client) use ($projectPath, $pipelineId) {
            return $client->projects()->pipeline($projectPath, $pipelineId);
        });

        return empty($res) ? null : new GitlabPipeline($res);
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

        $res = $this->userRequest(__METHOD__, function ($client) use ($projectPath, $jobId) {
            return $client->jobs()->show($projectPath, $jobId);
        });

        return empty($res) ? null : new GitlabJob($res);
    }

    /**
     * Возвращает данные проекта по идентификатору проекта.
     *
     * @return GitlabProject
     */
    public function getProject($projectId)
    {
        $res = $this->userRequest(__METHOD__, function ($client) use ($projectId) {
            return $client->projects()->show($projectId);
        });

        return empty($res) ? null : new GitlabProject($res);
    }

    /**
     * Возвращает список проектов по идентификатору группы.
     */
    public function getProjects($groupId)
    {
        $list = $this->userRequest(__METHOD__, function ($client) use ($groupId) {
            return $client->groups()->projects($groupId);
        });

        if ($list === null) {
            return null;
        }

        $res = [];
        foreach ($list as $data) {
            $res[] = new GitlabProject($data);
        }

        return $res;
    }

    /**
     * Возвращает список веток репозитория проекта.
     */
    public function getBranches($projectId, $search = null)
    {
        return $this->userRequest(__METHOD__, function ($client) use ($projectId, $search) {
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
        });
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
        $res = $this->userRequest(__METHOD__, function ($client) use ($projectId, $parent, $name) {
            return $client->repositories()->createBranch($projectId, $name, $parent);
        }, false);

        return empty($res) ? false : new GitlabBranch($res);
    }

    /**
     * Удаляет ветку на репозитории.
     * @param int|string $projectId Идентификатор проекта на GitLab.
     * @param string $name Имя удаляемой ветки.
     * @return bool true в случае успеха, иначе false.
     */
    public function deleteBranch($projectId, $name)
    {
        return $this->userRequest(__METHOD__, function ($client) use ($projectId, $name) {
            $client->repositories()->deleteBranch($projectId, $name);
            return true;
        }, false);
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
        $res = $this->userRequest(
            __METHOD__,
            function ($client) use ($projectId, $fromShaOrBranch, $toShaOrBranch) {
                return $client->repositories()->compare($projectId, $fromShaOrBranch, $toShaOrBranch);
            },
            false
        );

        return $res ? new GitlabCommit($res['commit']) : false;
    }

    /**
     * Создает комментарий к MR.
     * @param $projectId Идентификатор проекта на GitLab.
     * @param $mrId Внутренний идентификатор MR на GitLab.
     * @param $text Текст комментария.
     */
    public function createMRNote($projectId, $mrInternalId, $text)
    {
        return $this->userRequest(__METHOD__, function ($client) use ($projectId, $mrInternalId, $text) {
            return $client->mergeRequests()->addNote($projectId, $mrInternalId, $text);
        }, false);
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
        $res = $this->userRequest(__METHOD__, function ($client) use ($projectId, $mrInternalId) {
            return $client->mergeRequests()->changes($projectId, $mrInternalId);
        });

        if ($res === null) {
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
    public function getLatestPipeline($projectId, $ref, $useSudo = false)
    {
        $request = function ($client) use ($projectId, $ref) {
            $projects = new GitlabClientProjectsExt($client);
            return $projects->pipelineLatest($projectId, $ref);
        };

        $res = $useSudo
            ? $this->sudoRequest(__METHOD__, $request)
            : $this->userRequest(__METHOD__, $request);

        return empty($res) ? null : new GitlabPipeline($res);
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

        $res = $this->userRequest(__METHOD__, function ($client) use ($projectId, $ref, $sha) {
            return $client->projects()->pipelines($projectId, [
                'ref' => $ref,
                'sha' => $sha,
                'order_by' => 'id',
                'sort' => 'desc',
                'per_page' => 1,
            ]);
        });

        return empty($res) ? null : new GitlabPipeline($res[0]);
    }

    /**
     * Выписывает пользователю личный токен, если действующего нет.
     *
     * Токен перевыписывается не только когда его нет вовсе, но и когда до
     * истечения осталось меньше {@see self::TOKEN_RENEW_BEFORE_DAYS} дней:
     * иначе он однажды истечёт посреди пользовательского сценария.
     *
     * Токены, выписанные до того, как таск начал хранить срок, срока не имеют
     * и заранее не перевыписываются - такой чинится повтором по отказу
     * (см. {@see self::userRequest()}).
     */
    private function ensureUserToken()
    {
        if ($this->_user === null || !$this->isAvailable()) {
            return;
        }

        if ($this->_token !== null && !$this->isUserTokenExpiring()) {
            return;
        }

        // TODO: нужен механизм, чтобы не каждый раз запрашивался токен, если не удается создать
        // потому что могут быть пользователи, не подключенные к репозиториям
        $token = $this->sudoCreateUserToken($this->_user);
        if ($token) {
            $this->setUserToken($token);
        }
    }

    /**
     * Истекает ли личный токен пользователя в ближайшие дни.
     *
     * Неизвестный срок не считается истекающим: заранее перевыписывать такой
     * токен не на основании чего.
     *
     * @return bool
     */
    private function isUserTokenExpiring()
    {
        $expiresAt = (float)$this->_user->gitlabTokenExpiresAt;
        if ($expiresAt <= 0) {
            return false;
        }

        return $expiresAt <= time() + self::TOKEN_RENEW_BEFORE_DAYS * 86400;
    }

    /**
     * Выполняет запрос личным токеном пользователя.
     *
     * Если GitLab отказал из-за недействительного токена, токен перевыписывается
     * через sudo и запрос повторяется - ровно один раз за время жизни интеграции.
     * Отказы другой природы (нет прав, нет объекта, сеть) повтора не вызывают:
     * новый токен их не изменит.
     *
     * @param  string   $method  Метод, от имени которого идёт запрос - для журнала.
     * @param  callable $request Запрос: получает \Gitlab\Client, возвращает ответ.
     * @param  mixed    $default Что вернуть, если запрос не удался.
     * @return mixed Ответ GitLab либо $default.
     */
    private function userRequest($method, callable $request, $default = null)
    {
        $client = $this->client();
        if ($client === null) {
            return $default;
        }

        try {
            return $request($client);
        } catch (Exception $e) {
            if (!$this->isInvalidTokenException($e)) {
                $this->onCallException($method, $e, self::CLIENT_USER);
                return $default;
            }

            // Это штатно восстановимый отказ, поэтому не ошибка: ниже токен
            // будет перевыписан, а запрос повторён
            LPMLog::warning(
                'GitLab не принял токен пользователя, перевыписываем: ' . $e->getMessage(),
                LPMLog::CH_GITLAB,
                $this->logContext(self::CLIENT_USER, ['context' => $method])
            );
        }

        $client = $this->renewUserToken();
        if ($client === null) {
            return $default;
        }

        try {
            return $request($client);
        } catch (Exception $e) {
            $this->onCallException($method, $e, self::CLIENT_USER);
            return $default;
        }
    }

    /**
     * Выполняет запрос общим токеном интеграции.
     *
     * Повтора здесь нет: общий токен задан в конфигурации, перевыписать его
     * таск не может.
     *
     * @param  string   $method  Метод, от имени которого идёт запрос - для журнала.
     * @param  callable $request Запрос: получает \Gitlab\Client, возвращает ответ.
     * @param  mixed    $default Что вернуть, если запрос не удался.
     * @return mixed Ответ GitLab либо $default.
     */
    private function sudoRequest($method, callable $request, $default = null)
    {
        $client = $this->sudoClient();
        if ($client === null) {
            return $default;
        }

        try {
            return $request($client);
        } catch (Exception $e) {
            $this->onCallException($method, $e, self::CLIENT_SUDO);
            return $default;
        }
    }

    /**
     * Перевыписывает личный токен пользователя после отказа GitLab.
     *
     * Больше одного раза за время жизни интеграции не перевыписывает: если
     * GitLab не принимает и только что выписанный токен, причина не в сроке,
     * и повторять нечего - иначе каждый запрос плодил бы новые токены.
     *
     * @return \Gitlab\Client|null Клиент с новым токеном либо null, если
     * перевыписать не удалось.
     */
    private function renewUserToken()
    {
        if ($this->_userTokenRenewed || $this->_user === null) {
            return null;
        }

        $this->_userTokenRenewed = true;

        $token = $this->sudoCreateUserToken($this->_user);
        if (empty($token)) {
            return null;
        }

        $this->setUserToken($token);

        return $this->client();
    }

    /**
     * Отказал ли GitLab именно из-за токена.
     *
     * 401 в ответ на запрос с токеном означает, что токен не действует -
     * истёк, отозван или удалён. Прочие отказы (403 - нет прав, 404 - нет
     * объекта, сетевые ошибки) к токену отношения не имеют, и новый токен
     * их не исправит.
     *
     * @param  Exception $e Исключение запроса к GitLab.
     * @return bool
     */
    private function isInvalidTokenException(Exception $e)
    {
        return $e instanceof \Gitlab\Exception\RuntimeException && (int)$e->getCode() === 401;
    }

    private function sudoGetUserByEmail($email)
    {
        return $this->sudoRequest(__METHOD__, function ($client) use ($email) {
            $res = $client->users()->all(['search' => $email]);
            return empty($res) ? null : $res[0];
        });
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
     * Клиент с общим токеном, но без sudo.
     *
     * Сведения о самом токене GitLab отдаёт только его владельцу, а с
     * заголовком sudo запрос пошёл бы от имени другого пользователя.
     *
     * Запрос этим клиентом делается по ходу отрисовки обычной страницы,
     * а не по просьбе пользователя, поэтому он жёстко ограничен по времени:
     * недоступный GitLab не должен держать страницу администратора.
     * Таймаут ставится только там, где доступен транспорт, который его умеет;
     * иначе остаётся клиент по умолчанию.
     *
     * @return \Gitlab\Client
     */
    private function tokenInfoClient()
    {
        $builder = null;
        if (class_exists('\Symfony\Component\HttpClient\Psr18Client')) {
            $builder = new \Gitlab\HttpClient\Builder(new \Symfony\Component\HttpClient\Psr18Client(
                \Symfony\Component\HttpClient\HttpClient::create([
                    'timeout' => self::TOKEN_INFO_TIMEOUT_SEC,
                    'max_duration' => self::TOKEN_INFO_TIMEOUT_SEC,
                ])
            ));
        }

        $client = new Gitlab\Client($builder);
        $client->setUrl($this->_url);
        $client->authenticate($this->_sudoToken, Gitlab\Client::AUTH_HTTP_TOKEN);

        return $client;
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

    private function onCallException($method, Exception $e, $client)
    {
        LPMLog::exception($e, LPMLog::CH_GITLAB, $this->logContext($client, [
            'context' => 'Exception during ' . $method,
        ]));
    }

    /**
     * Контекст записи журнала: каким клиентом шёл запрос и от чьего имени.
     *
     * @param  string $client Вид клиента: self::CLIENT_USER или self::CLIENT_SUDO.
     * @param  array  $extra  Дополнительные поля записи.
     * @return array
     */
    private function logContext($client, array $extra = [])
    {
        $context = [
            'client' => $client,
            'owner' => $client === self::CLIENT_USER
                ? self::getUserLabel($this->_user)
                : $this->_sudoUser,
        ];

        return array_merge($context, $extra);
    }
}
