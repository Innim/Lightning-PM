<?php
/**
 * Внешнее API для обработки hook событий от GitLab.
 *
 * Для подключения надо вручную добавить нужные хуки в Admin Area > System Hooks.
 *
 * URL: https://task_url/api/gitlab/
 * Secret Token: То, что передается в конструктор этого класса присоздании.
 * Trigger:
 * - Merge request events.
 * - Push events.
 *
 * // TODO: сделать автоматическое создание хука
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

    const REPO_BRANCH_PREFIX = 'refs/heads/';
    const DEVELOP_BRANCH = 'develop';

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

            if (empty($data['event_type']) && empty($data['event_name'])) {
                throw new Exception("Can't find event_type or event_name field");
            }

            $eventType = isset($data['event_type']) ? $data['event_type'] : null;
            $eventName = isset($data['event_name']) ? $data['event_name'] : null;

            if ($eventType == self::EVENT_TYPE_MR) {
                return $this->onMREvent($data);
            } elseif ($eventName == self::EVENT_NAME_PUSH) {
                return $this->onPushEvent($data);
            } else {
                throw new Exception("Unhandled event " . $eventType . ", " . $eventName);
            }
        } catch (Exception $e) {
            return $this->onException($e);
        }
    }

    private function checkToken()
    {
        if (empty($this->_token)) {
            return true;
        }

        return !empty($_SERVER['HTTP_X_GITLAB_TOKEN']) &&
            $_SERVER['HTTP_X_GITLAB_TOKEN'] == $this->_token;
    }

    private function onException(Exception $e)
    {
        $this->log($e->getMessage(), 'error-');
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

        // $this->log(json_encode($data));

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
                                !IssueBranch::existBranchesWithoutMergedMRForIssue($issueId)) {
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
                    $this->onMROpen($user, $mr);
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
            if ($ref == self::REPO_BRANCH_PREFIX . self::DEVELOP_BRANCH) {
                $this->onDevelopPush($user, $repositoryId, $data);
            } elseif (!empty($data['commits'])) {
                $this->updateLastCommit($user, $repositoryId, $ref, $data);
            }
        }
    }

    private function onMROpen(User $user, GitlabMergeRequest $mr)
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

                    // Добавляем коммент со ссылкой на MR в задачу
                    $commentText = $mr->url;

                    if (!empty($mr->description)) {
                        $commentText = $mr->description . "\n\n" . $commentText;
                    }

                    $engine->comments()->postComment($user, $issue, $commentText, false, true);

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

    private function onDevelopPush(User $user, $repositoryId, $data)
    {
        $commitIds = [];
        foreach ($data['commits'] as $commitData) {
            $commitIds[] = $commitData['id'];
        }

        // Загружаем список IssueBranch по последним коммитам
        $issueBranches = IssueBranch::loadByLastCommits($repositoryId, $commitIds, true);

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

            foreach ($issues as $issue) {
                $branches = $branchesByIssueId[$issue->id];

                // Оставляем коммент что влиты в develop
                $commentTextArr = [];
                foreach ($branches as $issueBranch) {
                    $commentTextArr[] = '`' . $issueBranch->name . ' -> ' . self::DEVELOP_BRANCH . '`';
                }

                $commentText = implode("\n", $commentTextArr);
                $comments->postComment($user, $issue, $commentText, true, true);

                // Проверяем права
                if ($issue->checkEditPermit($user->userId)) {
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

    private function updateLastCommit(User $user, $repositoryId, $ref, $data)
    {
        $branchName = str_replace(self::REPO_BRANCH_PREFIX, '', $ref);

        // Надо сначала проверить, есть ли такая ветка на таске вообще
        if (IssueBranch::existIssuesWithBranch($repositoryId, $branchName)) {
            // Не надо обновлять, если ветка в том же состоянии, что develop
            $gitlab = GitlabIntegration::getInstance($user);
            $commit = $gitlab->compareBranchesAndGetCommit($repositoryId, self::DEVELOP_BRANCH, $branchName);
        
            if ($commit) {
                // В ветке есть отличия от develop -
                // обновляем последний коммит
                $lastCommit = $commit->id;
                IssueBranch::updateLastCommit($repositoryId, $branchName, $lastCommit);
            }
        }
    }

    private function getUser($data)
    {
        $userData = $data[self::FIELD_USER];
        if (!empty($userData) && !empty($userData['email'])) {
            $user = User::loadByEmail($userData['email']);

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

    private function log($message, $suffix = '')
    {
        $fileName = DateTimeUtils::mysqlDate(null, false) . '-' .
            DateTimeUtils::date('H-i-s') . '.log';
        file_put_contents(LOGS_PATH . '/api/gitlab' . $suffix . '/' . $fileName, $message);
    }
}
