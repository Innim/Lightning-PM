<?php
/**
 * Внешнее API для обработки hook событий от GitLab.
 *
 * События приходят из двух хуков. Адрес и секрет у них общие, различаются
 * только тем, кто их заводит и какие события присылает:
 *
 * URL: https://task_url/api/gitlab/
 * Secret Token: значение GITLAB_HOOK_TOKEN (передается в конструктор).
 *
 * 1. Системный хук инстанса - заводится вручную, один раз, в
 *    Admin Area > System Hooks. Отметить триггеры:
 *    - Merge request events;
 *    - Push events.
 *
 * 2. Хуки репозиториев - таск заводит и обновляет их сам
 *    (`GitlabWebhookManager`), руками отмечать ничего не нужно. Триггер:
 *    - Pipeline events.
 *
 * События пайплайнов вынесены в отдельный хук, потому что системные хуки их
 * не отдают. Наборы триггеров не пересекаются намеренно: иначе одно событие
 * приходило бы дважды, двумя разными хуками.
 */
class GitlabExternalApi extends ExternalApi
{
    const UID = 'gitlab';

    const FIELD_OBJECT_KIND = 'object_kind';
    const FIELD_OBJECT_ATTRIBUTES = 'object_attributes';
    const FIELD_USER = 'user';
    const FIELD_USER_ID = 'user_id';
    
    const EVENT_TYPE_MR = 'merge_request';
    
    const EVENT_NAME_PUSH = 'push';

    /**
     * Сколько раз спрашивать у GitLab пайплайн запушенного коммита.
     *
     * Пайплайн создаётся тем же пушем, что вызвал хук, поэтому на момент
     * первого запроса его может ещё не быть. Ссылка попадает в комментарий
     * о влитии один раз и навсегда, поэтому подождать выгоднее, чем оставить
     * комментарий без состояния сборки.
     */
    const PIPELINE_LOOKUP_ATTEMPTS = 3;

    /**
     * Пауза между попытками получить пайплайн запушенного коммита, секунды.
     *
     * Хук отвечает синхронно, поэтому суммарное ожидание должно оставаться
     * заметно меньше таймаута вебхука GitLab (по умолчанию 10 секунд).
     */
    const PIPELINE_LOOKUP_RETRY_DELAY_SEC = 1;

    const REPO_BRANCH_PREFIX = 'refs/heads/';
    const DEVELOP_BRANCH = 'develop';
    const MASTER_BRANCH = 'master';
    const MAIN_BRANCH = 'main';

    private $_token;

    public function __construct(LightningEngine $engine, $token)
    {
        parent::__construct($engine, self::UID);

        $this->_token = $token;
    }

    public function run($input)
    {
        try {
            if (!$this->checkToken()) {
                throw new Exception('Token validation failed');
            }

            $data = json_decode($input, true);
            if (!$data) {
                throw new Exception("Can't parse input");
            }

            $eventType = isset($data['event_type']) ? $data['event_type'] : null;
            $eventName = isset($data['event_name']) ? $data['event_name'] : null;
            // У части событий (в том числе pipeline) нет ни event_type, ни
            // event_name - опознать их можно только по object_kind
            $objectKind = isset($data[self::FIELD_OBJECT_KIND]) ? $data[self::FIELD_OBJECT_KIND] : null;

            if (empty($eventType) && empty($eventName) && empty($objectKind)) {
                throw new Exception("Can't find event_type, event_name or object_kind field");
            }

            if ($eventType == self::EVENT_TYPE_MR) {
                return $this->onMREvent($data);
            } elseif ($eventName == self::EVENT_NAME_PUSH) {
                return $this->onPushEvent($data);
            }

            // Хук приносит и то, что таск не разбирает - теги, изменения
            // проектов и пользователей на инстансе. Это штатный ход событий,
            // а не ошибка, поэтому в лог ошибок такое не пишем.
            // Сюда же пока попадают события пайплайнов: подписка на них
            // заводится этой задачей, а обработчик придет отдельной (#436)
            LPMLog::debug('Skipped event', LPMLog::CH_GITLAB, [
                'event_type' => $eventType,
                'event_name' => $eventName,
                'object_kind' => $objectKind,
            ]);

            return null;
        } catch (Exception $e) {
            return $this->onException($e);
        }
    }

    /**
     * Проверяет секретный токен, которым GitLab подписывает вызов хука.
     *
     * Ненастроенный токен - это отказ, а не пропуск: иначе хук, меняющий
     * состояние задач, был бы открыт для любого запроса извне.
     * @return bool
     */
    private function checkToken()
    {
        if (empty($this->_token)) {
            LPMLog::error(
                'GITLAB_HOOK_TOKEN не задан - вызов хука отклонён',
                LPMLog::CH_GITLAB
            );
            return false;
        }

        if (empty($_SERVER['HTTP_X_GITLAB_TOKEN'])) {
            return false;
        }

        // hash_equals - чтобы по времени ответа нельзя было подбирать токен посимвольно
        return hash_equals((string)$this->_token, (string)$_SERVER['HTTP_X_GITLAB_TOKEN']);
    }

    private function onException(Exception $e)
    {
        $context = [];

        if ($e instanceof \GMFramework\ProviderException) {
            $dbError = $this->engine()->getDebugDbError();
            if ($dbError) {
                $context['dbError'] = $dbError;
            }
        }

        LPMLog::exception($e, LPMLog::CH_GITLAB, $context);
        // TODO: формат ошибки
        return $e->getMessage();
    }

    private function onMREvent($data)
    {
        if (!isset($data[self::FIELD_OBJECT_KIND], $data[self::FIELD_OBJECT_ATTRIBUTES])) {
            throw new Exception("Invalid data: there is no object data");
        }

        if ($data[self::FIELD_OBJECT_KIND] != 'merge_request') {
            throw new Exception("Invalid object kind: " . $data[self::FIELD_OBJECT_KIND]);
        }

        LPMLog::debug('MR event received', LPMLog::CH_GITLAB, ['payload' => $data]);

        $objectAttributes = $data[self::FIELD_OBJECT_ATTRIBUTES];
        $mr = new GitlabMergeRequest($data[self::FIELD_OBJECT_ATTRIBUTES]);

        // Если MR был влит, то возможно надо оповестить тестировщика
        if ($mr->isMerged()) {
            // Загружаем задачи по MR
            $issueIds = IssueMR::loadIssueIdsForOpenedMr($mr->id);
            if (!empty($issueIds)) {
                $slack = SlackIntegration::getInstance();
                foreach ($issueIds as $issueId) {
                    $issue = Issue::load($issueId);
                    if (empty($issue)) {
                        continue;
                    }

                    if ($issue->status == Issue::STATUS_WAIT) {
                        // Если задача в тесте - оповещаем тестера что MR влит
                        // (это MR c правками)
                        $testers = $issue->getTesters();
                        if (!empty($testers)) {
                            $slack->notifyMRMergedToTester($issue, $mr);
                        }
                    } elseif ($issue->status == Issue::STATUS_IN_WORK) {
                        // Если задача в работе, то вполне возможно надо перевесить ее в тест,
                        // но предварительно надо убедиться, что все MR задачи влиты.
                        // А даже если все MR по задаче влиты, но возможно есть привязанные задачи,
                        // для которых еще нет MR, тогда отправлять в тест не надо
                        if (!IssueMR::existOpenedMrForIssue($issueId, $mr->id) &&
                                !IssueBranch::existBranchesWithoutMergedMRForIssue($issueId, $mr->id)) {
                            // Перевешиваем задачу в тест
                            // TODO: может перевешивать только то, что сейчас на доске в работе?
                            Issue::setStatus($issue, Issue::STATUS_WAIT, null);
                        }
                    }
                }
            }
        } else {
            $user = $this->getUser($data);
            if (!empty($user)) {
                if ($objectAttributes['action'] == 'open') {
                    $this->onMROpen($user, $mr, $data['project']['name'] ?? null);
                }
            }
        }

        // Обновляем статус MR
        IssueMR::updateState($mr->id, $mr->state);
    }

    private function onPushEvent($data)
    {
        if (!isset($data[self::FIELD_OBJECT_KIND])) {
            throw new Exception("Invalid data: there is no object data");
        }

        if ($data[self::FIELD_OBJECT_KIND] != 'push') {
            throw new Exception("Invalid object kind: " . $data[self::FIELD_OBJECT_KIND]);
        }

        $ref = $data['ref'];
        $repositoryId = $data['project']['id'];

        $user = $this->getUserById($data);
        if ($user) {
            $stableBranches = [self::DEVELOP_BRANCH, self::MASTER_BRANCH, self::MAIN_BRANCH];
            $branchName = str_replace(self::REPO_BRANCH_PREFIX, '', $ref);

            if (in_array($branchName, $stableBranches)) {
                $this->onDevelopOrMainPush($user, $repositoryId, $data, $branchName);
            } elseif (!empty($data['commits'])) {
                $this->updateLastCommit($user, $repositoryId, $branchName, $data);
            }
        }
    }

    private function onMROpen(User $user, GitlabMergeRequest $mr, $repositoryName = null)
    {
        // Открыли новый MR - попробуем найти задачи, которые привязаны
        $issueIds = IssueBranch::loadIssueIdsForBranch($mr->sourceProjectId, $mr->sourceBranch);

        if (!empty($issueIds)) {
            $engine = $this->engine();
            $mrComment = '';

            $exceptIssueIds = IssueMR::loadIssueIdsForOpenedMr($mr->id);

            foreach ($issueIds as $issueId) {
                // Проверим, возможно этот MR уже добавлен
                if (in_array($issueId, $exceptIssueIds)) {
                    continue;
                }

                $issue = Issue::load($issueId);
                if (empty($issue)) {
                    continue;
                }

                // Обрабатываем только задачи в работе или в тесте
                if ($issue->status == Issue::STATUS_IN_WORK || $issue->status == Issue::STATUS_WAIT) {
                    
                    // Связываем
                    IssueMR::createByMr($issueId, $mr);

                    // Добавляем коммент со ссылкой на MR в задачу:
                    // заголовок с репозиторием, ветки, описание MR и ссылка
                    $commentParts = [];

                    if (!empty($repositoryName)) {
                        $commentParts[] = '### ' . $repositoryName;
                    }

                    $commentParts[] = '`' . $mr->sourceBranch . ' → ' . $mr->targetBranch . '`';

                    if (!empty($mr->description)) {
                        $commentParts[] = $mr->description;
                    }

                    $commentParts[] = $mr->url;

                    $commentText = implode("\n\n", $commentParts);

                    $engine->comments()->postComment(
                        $user,
                        $issue,
                        $commentText,
                        false,
                        true,
                        IssueCommentType::MERGE_REQUEST
                    );

                    // Добавляем коммент со ссылкой на задачу в MR
                    if (!empty($mrComment)) {
                        $mrComment .= '\n';
                    }
                    $mrComment .= $issue->getConstURL();
                }
            }

            if (!empty($mrComment)) {
                $gitlab = GitlabIntegration::getInstance($user);
                $gitlab->createMRNote($mr->targetProjectId, $mr->internalId, $mrComment);
            }
        }
    }

    private function onDevelopOrMainPush(User $user, $repositoryId, $data, $branchName)
    {
        $commitIds = [];
        foreach ($data['commits'] as $commitData) {
            $commitId = $commitData['id'];
            if (!empty($commitId)) {
                $commitIds[] = $commitId;
            }
        }

        // Загружаем список IssueBranch по последним коммитам
        $issueBranches = IssueBranch::loadByLastCommits($repositoryId, $commitIds, true, true);

        if (!empty($issueBranches)) {
            $engine = $this->engine();
            $comments = $engine->comments();

            // Все ветки, что вошли сюда - теперь влиты в develop
            $branchesByIssueId = [];
            foreach ($issueBranches as $issueBranch) {
                $issueId = $issueBranch->issueId;
                if (isset($branchesByIssueId[$issueId])) {
                    $branchesByIssueId[$issueId][] = $issueBranch;
                } else {
                    $branchesByIssueId[$issueId] = [$issueBranch];
                }

                // Отмечаем что ветка влита
                IssueBranch::mergedInDevelop(
                    $issueId,
                    $issueBranch->repositoryId,
                    $issueBranch->name
                );
            }

            $issueIds = array_keys($branchesByIssueId);
            $issues = Issue::loadListByIds($issueIds);

            $pipelineUrl = $this->findPushPipelineUrl($user, $repositoryId, $branchName, $data);

            foreach ($issues as $issue) {
                $branches = $branchesByIssueId[$issue->id];

                $repositoryName = $data['project']['name'];

                // Оставляем коммент что влиты в develop
                $commentTextArr = [];
                foreach ($branches as $issueBranch) {
                    $commentTextArr[] = '*' . $repositoryName . '*: `' .
                        $issueBranch->name . ' -> ' . $branchName . '`';
                }

                $commentText = implode("\n", $commentTextArr);
                if (!empty($pipelineUrl)) {
                    $commentText .= "\n\n" . $pipelineUrl;
                }

                $comments->postComment($user, $issue, $commentText, true, true,
                    IssueCommentType::BRANCH_MERGED,
                    IssueCommentBranchMergedData::serializeBy($branches));

                // Проверяем права и вливаем только задачи, которые уже в тесте
                if ($issue->checkEditPermit($user->userId) && $issue->status == Issue::STATUS_WAIT) {
                    // Проверяем что все ветки этой задачи влиты
                    $isAllMerged = !IssueBranch::existNotMergedInDevelopForIssue($issue->id);

                    // Если да - то завершаем задачу
                    if ($isAllMerged) {
                        Issue::setStatus($issue, Issue::STATUS_COMPLETED, $user);
                    }
                }
            }
        }
    }

    /**
     * Возвращает URL пайплайна, запущенного пушем в стабильную ветку.
     *
     * В комментарий о влитии кладётся именно ссылка, а не состояние сборки:
     * состояние меняется уже после публикации комментария, поэтому актуальное
     * подтягивается по этой ссылке при просмотре задачи.
     *
     * @param User $user Пользователь, от имени которого идёт запрос к GitLab.
     * @param int|string $repositoryId Идентификатор проекта на GitLab.
     * @param string $branchName Ветка, в которую сделан пуш.
     * @param array $data Данные события push.
     * @return string|null URL пайплайна или null, если пайплайн для коммита
     * так и не появился (в том числе если в проекте нет CI).
     */
    private function findPushPipelineUrl(User $user, $repositoryId, $branchName, $data)
    {
        $sha = empty($data['checkout_sha']) ? null : $data['checkout_sha'];
        if (empty($sha)) {
            return null;
        }

        $gitlab = GitlabIntegration::getInstance($user);

        for ($attempt = 1; $attempt <= self::PIPELINE_LOOKUP_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                sleep(self::PIPELINE_LOOKUP_RETRY_DELAY_SEC);
            }

            $pipeline = $gitlab->getPipelineForCommit($repositoryId, $branchName, $sha);
            if (!empty($pipeline) && !empty($pipeline->url)) {
                return $pipeline->url;
            }
        }

        LPMLog::debug('No pipeline for pushed commit', LPMLog::CH_GITLAB, [
            'repositoryId' => $repositoryId,
            'ref'          => $branchName,
            'sha'          => $sha,
        ]);

        return null;
    }

    private function updateLastCommit(User $user, $repositoryId, $branchName, $data)
    {
        // Надо сначала проверить, есть ли такая ветка на таске вообще
        if (IssueBranch::existIssuesWithBranch($repositoryId, $branchName)) {
            // Не надо обновлять, если ветка в том же состоянии, что develop
            $gitlab = GitlabIntegration::getInstance($user);
            $stableBranch = $this->findStableBranch($gitlab, $repositoryId);
            $commit = $gitlab->compareBranchesAndGetCommit($repositoryId, $stableBranch, $branchName);
        
            if ($commit) {
                // В ветке есть отличия от develop -
                // обновляем последний коммит
                $lastCommit = $commit->id;
                IssueBranch::updateLastCommit($repositoryId, $branchName, $lastCommit);
            } else {
                // TODO: Если нет отличий от develop - надо обновить начальный коммит
                // IssueBranch::updateInitialCommit($repositoryId, $branchName, $brachCommit);
            }
        }
    }

    private function getUser($data)
    {
        $userData = $data[self::FIELD_USER];
        if (!empty($userData) && !empty($userData['email'])) {

            $email = $userData['email'];
            if ($email === '[REDACTED]') {
                if (!empty($userData['id'])) {
                    $user = User::loadByGitlabId($userData['id']);
                    // TODO: обработать, если не нашлось по id, например загрузить данные запросом
                } else {
                    $user = null;
                }
            } else {
                $user = User::loadByEmail($email);
            }

            if ($user != null && !empty($user->gitlabToken)) {
                return $user;
            }
        }

        return null;
    }

    private function getUserById($data)
    {
        $gitlabUserId = $data[self::FIELD_USER_ID];
        if (!empty($gitlabUserId)) {
            return User::loadByGitlabId($gitlabUserId);
        }

        return null;
    }

    private function findStableBranch(GitlabIntegration $gitlab, $projectId)
    {
        $candidates = [self::DEVELOP_BRANCH, self::MASTER_BRANCH, self::MAIN_BRANCH];
        foreach ($candidates as $branchName) {
            $res = $gitlab->getBranches($projectId, '^' . $branchName . '$');
            if (!empty($res)) {
                return $branchName;
            }
        }

        return null;

    }
}
