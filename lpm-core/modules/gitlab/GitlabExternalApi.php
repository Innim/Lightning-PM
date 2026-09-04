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

    const OBJECT_KIND_PIPELINE = 'pipeline';

    /**
     * Сколько раз спрашивать у GitLab пайплайн коммита.
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
            } elseif ($objectKind == self::OBJECT_KIND_PIPELINE) {
                return $this->onPipelineEvent($data);
            }

            // Хук приносит и то, что таск не разбирает - теги, изменения
            // проектов и пользователей на инстансе. Это штатный ход событий,
            // а не ошибка, поэтому в лог ошибок такое не пишем.
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
            $this->registerMergedMrPipeline($mr, $data);

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

    /**
     * Обрабатывает событие пайплайна: сохраняет состояние сборки у задач,
     * merge request'ы которых влиты этим коммитом.
     *
     * Пайплайны, к задачам отношения не имеющие, отсеиваются самим сохранением
     * (см. IssuePipeline::applyPipeline()): в репозитории собирается много
     * такого, о чём таск ничего не знает.
     *
     * @param array $data Данные события.
     */
    private function onPipelineEvent($data)
    {
        if (!isset($data[self::FIELD_OBJECT_ATTRIBUTES])) {
            throw new Exception("Invalid data: there is no object data");
        }

        $projectId = isset($data['project']['id']) ? (int)$data['project']['id'] : 0;
        if (empty($projectId)) {
            throw new Exception("Invalid data: there is no project id");
        }

        $pipeline = new GitlabPipeline($data[self::FIELD_OBJECT_ATTRIBUTES]);
        // Идентификатор проекта лежит вне данных пайплайна, а ссылки на него
        // в событии может не быть вовсе - собираем её из адреса репозитория
        $pipeline->projectId = $projectId;
        if (empty($pipeline->url) && !empty($data['project']['web_url'])) {
            $pipeline->url = rtrim($data['project']['web_url'], '/') . '/-/' .
                GitlabIntegration::URL_PIPELINE_SUBPATH . $pipeline->id;
        }

        $updated = IssuePipeline::applyPipeline($pipeline);

        LPMLog::debug('Pipeline event received', LPMLog::CH_GITLAB, [
            'projectId'  => $projectId,
            'pipelineId' => $pipeline->id,
            'ref'        => $pipeline->ref,
            'sha'        => $pipeline->sha,
            'status'     => $pipeline->status,
            'updated'    => $updated,
        ]);
    }

    /**
     * Заводит у задач влитого MR запись о сборке, которую запустило влитие,
     * и сразу заполняет её, если сборка уже создана.
     *
     * Состояние потом обновляют события пайплайна, поэтому не найденная сейчас
     * сборка - не ошибка: она может появиться на секунду позже.
     *
     * @param GitlabMergeRequest $mr   Влитый merge request.
     * @param array              $data Данные события.
     */
    private function registerMergedMrPipeline(GitlabMergeRequest $mr, $data)
    {
        $sha = $mr->getMergedCommitSha();
        if (empty($sha) || empty($mr->targetProjectId) || empty($mr->targetBranch)) {
            LPMLog::warning('Can\'t detect merged commit for MR', LPMLog::CH_GITLAB, [
                'mrId' => $mr->id,
            ]);
            return;
        }

        $issueIds = IssueMR::loadIssueIdsForMr($mr->id);
        if (empty($issueIds)) {
            return;
        }

        foreach ($issueIds as $issueId) {
            IssuePipeline::registerForMr(
                $issueId,
                $mr->id,
                $mr->targetProjectId,
                $mr->sourceBranch,
                $mr->targetBranch,
                $sha
            );
        }

        $user = $this->getUser($data);
        if (empty($user)) {
            return;
        }

        $pipeline = $this->findPipelineForCommit($user, $mr->targetProjectId, $mr->targetBranch, $sha);
        if (!empty($pipeline)) {
            IssuePipeline::applyPipeline($pipeline);
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

            // Пуш в стабильную ветку ставит её на этот коммит: по нему
            // комментарий о влитии находит состояние своей сборки
            $mergedSha = empty($data['checkout_sha']) ? '' : (string)$data['checkout_sha'];

            $pipeline = $this->findPushPipeline($user, $repositoryId, $branchName, $data);
            // Пуш в стабильную ветку - это и есть влитие MR, так что найденная
            // сборка закрывает состояние тех задач, чьи MR уже зарегистрированы
            if (!empty($pipeline)) {
                IssuePipeline::applyPipeline($pipeline);
            }

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
                if (!empty($pipeline) && !empty($pipeline->url)) {
                    $commentText .= "\n\n" . $pipeline->url;
                }

                $comments->postComment($user, $issue, $commentText, true, true,
                    IssueCommentType::BRANCH_MERGED,
                    IssueCommentBranchMergedData::serializeBy($branches, $mergedSha));

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
     * Возвращает пайплайн, запущенный пушем в стабильную ветку.
     *
     * @param User $user Пользователь, от имени которого идёт запрос к GitLab.
     * @param int|string $repositoryId Идентификатор проекта на GitLab.
     * @param string $branchName Ветка, в которую сделан пуш.
     * @param array $data Данные события push.
     * @return GitlabPipeline|null Пайплайн или null, если пайплайн для коммита
     * так и не появился (в том числе если в проекте нет CI).
     */
    private function findPushPipeline(User $user, $repositoryId, $branchName, $data)
    {
        $sha = empty($data['checkout_sha']) ? null : $data['checkout_sha'];
        if (empty($sha)) {
            return null;
        }

        return $this->findPipelineForCommit($user, $repositoryId, $branchName, $sha);
    }

    /**
     * Возвращает пайплайн коммита, подождав, пока GitLab его создаст.
     *
     * Пайплайн создаётся тем же пушем, что вызвал хук, поэтому на момент
     * первого запроса его может ещё не быть.
     *
     * @param User $user Пользователь, от имени которого идёт запрос к GitLab.
     * @param int|string $repositoryId Идентификатор проекта на GitLab.
     * @param string $ref Ветка, в которой находится коммит.
     * @param string $sha SHA коммита.
     * @return GitlabPipeline|null Пайплайн или null, если он так и не появился
     * (в том числе если в проекте нет CI).
     */
    private function findPipelineForCommit(User $user, $repositoryId, $ref, $sha)
    {
        $gitlab = GitlabIntegration::getInstance($user);

        for ($attempt = 1; $attempt <= self::PIPELINE_LOOKUP_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                sleep(self::PIPELINE_LOOKUP_RETRY_DELAY_SEC);
            }

            $pipeline = $gitlab->getPipelineForCommit($repositoryId, $ref, $sha);
            if (!empty($pipeline) && !empty($pipeline->url)) {
                return $pipeline;
            }
        }

        LPMLog::debug('No pipeline for commit', LPMLog::CH_GITLAB, [
            'repositoryId' => $repositoryId,
            'ref'          => $ref,
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
