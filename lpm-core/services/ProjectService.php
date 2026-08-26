<?php
require_once(__DIR__ . '/../init.inc.php');

class ProjectService extends LPMBaseService
{
    public function addMembers($projectId, $userIds)
    {
        $projectId = (float)$projectId;
        
        if (!$userIds = $this->floatArr($userIds)) {
            return $this->error('Неверные входные параметры');
        }
        
        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }
        
        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) {
            return $this->error('Нет такого проекта');
        }
        
        // пытаемся добавить участников проекта
        $sql = "insert into `%s` ( `userId`, `instanceType`, `instanceId` ) values ";
        
        foreach ($userIds as $i => $userId) {
            if ($i > 0) {
                $sql .= ', ';
            }
            $sql .= "( '" . $userId . "', '" . LPMInstanceTypes::PROJECT . "', '" . $projectId . "' )";
        }
        
        if (!$this->_db->queryt($sql, LPMTables::MEMBERS)) {
            return ($this->_db->errno == 1062) ? $this->error('Участники уже добавлены') : $this->error();
        }
        
        if (!$members = Member::loadListByProject($projectId)) {
            return $this->error();
        }
        
        $this->add2Answer('members', $members);
        return $this->answer();
    }

    /**
     * Возвращает участников проекта.
     *
     * Будут возвращены только незаблокированные участники.
     *
     * @param int $projectId Идентификатор проекта.
     * @return [
     *    list: User[]
     * ]
     */
    public function getMembers($projectId)
    {
        $projectId = (float)$projectId;

        if (!$user = $this->getUser()) {
            return $this->error('Ошибка при загрузке пользователя');
        }
        
        if (!$project = Project::loadById($projectId)) {
            return $this->error('Нет такого проекта');
        }
        
        if (!$project->hasReadPermission($user)) {
            return $this->error('Недостаточно прав доступа');
        }
        
        if (!$members = $project->getMembers(true)) {
            return $this->error('Ошибка при загрузке участников');
        }
        
        $this->add2Answer('members', $members);
        return $this->answer();
    }

    public function getSumOpenedIssuesHours($projectId)
    {
        // TODO проверить права доступа для этого проекта
        
        $count = Project::sumHoursActiveIssues($projectId);

        if ($count === false) {
            return $this->error('Ошибка получения данных суммы часов');
        }

        $this->add2Answer('count', $count);

        return $this->answer();
    }

    /**
     * Устанавливает указанного участника проекта в качестве мастера.
     * @param int $projectId Идентификатор проекта.
     * @param int $masterId  Идентификатор участника, которого надо сделать мастером.
     */
    public function setMaster($projectId, $masterId)
    {
        $projectId = (int)$projectId;
        $masterId  = (int)$masterId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        if ($project->masterId != $masterId) {
            $member = $project->getMember($masterId);

            if (!$member) {
                return $this->error('Мастер не найден в участниках проекта');
            }

            if (!Project::updateMaster($project->id, $masterId)) {
                return $this->error('Не удалось сохранить данные.');
            }
        }

        return $this->answer();
    }

    /**
     * Удаляет мастера проекта.
     * @param  int $projectId Идентификатор проекта.
     */
    public function deleteMaster($projectId)
    {
        $projectId = (int)$projectId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        if ($project->masterId) {
            if (!Project::updateMaster($project->id, 0)) {
                return $this->error('Не удалось сохранить данные.');
            }
        }

        return $this->answer();
    }

    /**
     * Устанавливает указанного участника проекта в качестве мастера для задач с указанным тегом.
     * @param int $projectId Идентификатор проекта.
     * @param int $masterId  Идентификатор участника, которого надо сделать мастером.
     * @param int $labelId   Идентификатор тега.
     */
    public function addSpecMaster($projectId, $masterId, $labelId)
    {
        $projectId = (int)$projectId;
        $masterId  = (int)$masterId;
        $labelId   = (int)$labelId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        $member = $project->getMember($masterId);
        if (!$member) {
            return $this->error('Мастер не найден в участниках проекта');
        }

        $label = Issue::getLabel($labelId);
        if (!$label) {
            return $this->error('Нет такого тега');
        }

        $labelProjectId = intval($label['projectId']);
        if ($labelProjectId != 0 && $labelProjectId != $projectId) {
            return $this->error('Тег не доступен в проекте');
        }

        if (!Member::saveProjectSpecMaster($project->id, $masterId, $labelId)) {
            return $this->error('Не удалось сохранить данные.');
        }

        return $this->answer();
    }


    /**
     * Устанавливает указанного участника проекта в качестве тестировщика для задач с указанным тегом.
     * @param int $projectId Идентификатор проекта.
     * @param int $userId    Идентификатор участника, которого надо сделать тестировщиком.
     * @param int $labelId   Идентификатор тега.
     */
    public function addSpecTester($projectId, $userId, $labelId)
    {
        $projectId = (int)$projectId;
        $userId    = (int)$userId;
        $labelId   = (int)$labelId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        $member = $project->getMember($userId);
        if (!$member) {
            return $this->error('Тестировщик не найден в участниках проекта');
        }

        $label = Issue::getLabel($labelId);
        if (!$label) {
            return $this->error('Нет такого тега');
        }

        $labelProjectId = intval($label['projectId']);
        if ($labelProjectId != 0 && $labelProjectId != $projectId) {
            return $this->error('Тег не доступен в проекте');
        }

        if (!Member::saveProjectSpecTester($project->id, $userId, $labelId)) {
            return $this->error('Не удалось сохранить данные.');
        }

        return $this->answer();
    }

    /**
     * Удаляет указанного участника проекта в качестве мастера для задач с указанным тегом.
     * @param int $projectId Идентификатор проекта.
     * @param int $masterId  Идентификатор участника, которого надо удалить из мастеров.
     * @param int $labelId   Идентификатор тега.
     */
    public function deleteSpecMaster($projectId, $masterId, $labelId)
    {
        $projectId = (int)$projectId;
        $masterId  = (int)$masterId;
        $labelId   = (int)$labelId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        $member = $project->getMember($masterId);
        if (!$member) {
            return $this->error('Мастер не найден в участниках проекта');
        }

        if (!Member::deleteProjectSpecMaster($project->id, $masterId, $labelId)) {
            return $this->error('Не удалось сохранить данные.');
        }

        return $this->answer();
    }

    /**
     * Удаляет указанного участника проекта в качестве тестера для задач с указанным тегом.
     * @param int $projectId Идентификатор проекта.
     * @param int $userId    Идентификатор участника, которого надо удалить из тестеров.
     * @param int $labelId   Идентификатор тега.
     */
    public function deleteSpecTester($projectId, $userId, $labelId)
    {
        $projectId = (int)$projectId;
        $userId    = (int)$userId;
        $labelId   = (int)$labelId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        $member = $project->getMember($userId);
        if (!$member) {
            return $this->error('Тестер не найден в участниках проекта');
        }

        if (!Member::deleteProjectSpecTester($project->id, $userId, $labelId)) {
            return $this->error('Не удалось сохранить данные.');
        }

        return $this->answer();
    }

    public function addIssueMemberDefault($projectId, $memberByDefaultId)
    {
        $projectId = (int)$projectId;
        $memberByDefaultId = (int)$memberByDefaultId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        // проверим, что существует такой проект
        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Нет такого проекта');
        }

        $memberProject = $project->getMember($memberByDefaultId);
        if (!$memberProject) {
            return $this->error('Исполнитель не найден в участниках проекта');
        }

        $defaultIssueMemberId = $project->defaultIssueMemberId;

        if ($defaultIssueMemberId) {
            return $this->error('Исполнитель уже назначен для проекта');
        }

        if (!Project::updateIssueMemberDefault($projectId, $memberByDefaultId)) {
            return $this->error('Не удалось сохранить данные.');
        }

        return $this->answer();
    }

    public function addTester($projectId, $userId)
    {
        $projectId = (float)$projectId;
        $userId = (float)$userId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) {
            return $this->error('Нет такого проекта');
        }

        if (empty($userId)) {
            return $this->error("Неверные входные параметры");
        }

        if (Member::hasMember(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId, $userId)) {
            return $this->error("Тестировщик уже добавлен");
        }

        Member::saveTesterForProject($projectId, $userId);

        $this->add2Answer("projectId", $projectId);
        $this->add2Answer("userId", $userId);

        return $this->answer();
    }

    public function deleteTester($projectId)
    {
        $projectId = (int)$projectId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }


        if (!Member::deleteMembers(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId)) {
            return $this->error("Ошибка удаления тестера.");
        }

        return $this->answer();
    }

    public function setPM($projectId, $userId)
    {
        $projectId = (float)$projectId;
        $userId = (float)$userId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) {
            return $this->error('Нет такого проекта');
        }

        if (empty($userId)) {
            return $this->error("Неверные входные параметры");
        }

        if (Member::hasMember(LPMInstanceTypes::PM_FOR_PROJECT, $projectId, $userId)) {
            return $this->error("PM уже добавлен");
        }

        Member::saveProjectPM($projectId, $userId);

        $this->add2Answer("projectId", $projectId);
        $this->add2Answer("userId", $userId);

        return $this->answer();
    }

    public function deletePM($projectId)
    {
        $projectId = (int)$projectId;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        if (!Member::deleteMembers(LPMInstanceTypes::PM_FOR_PROJECT, $projectId)) {
            return $this->error("Ошибка удаления PM.");
        }

        return $this->answer();
    }

    public function deleteMemberDefault($projectId)
    {
        $projectId = (int)$projectId;
        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        // проверим, что существует такой проект
        if (!Project::loadById($projectId)) {
            return $this->error('Нет такого проекта');
        }

        $defaultIssueMemberId = Project::loadById($projectId)->defaultIssueMemberId;

        if (!$defaultIssueMemberId) {
            return $this->error('Исполнитель не назначен для проекта');
        }

        $result = Project::deleteMemberDefault($projectId);

        if (!$result) {
            return $this->error('Ошибка удаления.');
        }

        $this->add2Answer('$result', $result);

        return $this->answer();
    }

    /**
     * Сохраняет проект: основную информацию (идентификатор, название, описание)
     * и настройки (Scrum, ИИ-сводка задач, обязательные теги, канал Slack,
     * привязки к GitLab) — всё за один вызов.
     *
     * Настройка ИИ-сводки применяется только если интеграция с ИИ настроена,
     * в ином случае сохраняется текущее значение.
     *
     * Настройка, под которую в таблице проектов ещё нет колонки (миграция не
     * применена), пропускается: остальные настройки при этом сохраняются.
     *
     * При изменении идентификатора проверяет его на допустимость и уникальность
     * (исключая сам редактируемый проект). В ответе возвращает новый URL страницы
     * настроек — идентификатор мог измениться.
     */
    public function saveProject(
        $projectId,
        $uid,
        $name,
        $desc,
        $scrum,
        $slackNotifyChannel,
        $gitlabGroupId,
        $gitlabProjectIds,
        $aiSummary,
        $aiTestChecklist,
        $aiIssueDraft,
        $requireLabels
    ) {
        $projectId = (int)$projectId;
        $uid  = strtolower(trim((string)$uid));
        $name = trim((string)$name);
        $desc = trim((string)$desc);
        $slackNotifyChannel = (string)$slackNotifyChannel;
        $gitlabGroupId = (int)$gitlabGroupId;
        $gitlabProjectIds = (string)$gitlabProjectIds;

        if (($scrum !== 0 && $scrum !== 1) || ($aiSummary !== 0 && $aiSummary !== 1)
            || ($aiTestChecklist !== 0 && $aiTestChecklist !== 1)
            || ($aiIssueDraft !== 0 && $aiIssueDraft !== 1)
            || ($requireLabels !== 0 && $requireLabels !== 1)
        ) {
            return $this->error('Неверные входные параметры');
        }

        $scrum = (bool)$scrum;
        $aiSummary = (bool)$aiSummary;
        $aiTestChecklist = (bool)$aiTestChecklist;
        $aiIssueDraft = (bool)$aiIssueDraft;
        $requireLabels = (bool)$requireLabels;

        // проверяем права пользователя
        if (!$this->checkRole(User::ROLE_MODERATOR)) {
            return $this->error('Недостаточно прав');
        }

        if ($uid === '' || $name === '' || $desc === '') {
            return $this->error('Заполнены не все поля');
        }

        $name = mb_substr($name, 0, PROJECT_NAME_MAX_LENGTH);
        $desc = mb_substr($desc, 0, PROJECT_DESC_MAX_LENGTH);

        if (!Project::isValidUid($uid)) {
            return $this->error(
                'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире'
            );
        }

        // проверим, что существует такой проект
        $project = Project::loadById($projectId);
        if (!$project) {
            return $this->error('Проект не найден');
        }

        // обновляем основную информацию только если она действительно изменилась
        if ($uid !== $project->uid || $name !== $project->name || $desc !== $project->desc) {
            // идентификатор должен быть уникальным (исключая сам редактируемый проект)
            if ($uid !== $project->uid && !Project::isUidAvailable($uid, $projectId)) {
                return $this->error('Проект с таким uid уже существует');
            }

            if (!Project::updateProjectInfo($projectId, $uid, $name, $desc)) {
                return $this->error('Ошибка обновления таблицы');
            }
        }

        // без настроенной интеграции с ИИ настройки ИИ-возможностей
        // не отображаются и не редактируются
        if (!AiIntegration::getInstance()->isAvailable()) {
            $aiSummary = $project->aiSummary;
            $aiTestChecklist = $project->aiTestChecklist;
            $aiIssueDraft = $project->aiIssueDraft;
        }

        // обновляем настройки только если они действительно изменились
        if ($scrum !== $project->scrum
            || $slackNotifyChannel !== $project->slackNotifyChannel
            || $gitlabGroupId !== $project->gitlabGroupId
            || $gitlabProjectIds !== $project->gitlabProjectIds
            || $aiSummary !== $project->aiSummary
            || $aiTestChecklist !== $project->aiTestChecklist
            || $aiIssueDraft !== $project->aiIssueDraft
            || $requireLabels !== $project->requireLabels
        ) {
            $result = Project::updateProjectSettings(
                $projectId,
                $scrum,
                $slackNotifyChannel,
                $gitlabGroupId,
                $gitlabProjectIds,
                $aiSummary,
                $aiTestChecklist,
                $aiIssueDraft,
                $requireLabels
            );

            if (!$result) {
                return $this->error('Ошибка обновления таблицы');
            }
        }

        $this->add2Answer('projectId', $projectId);
        $this->add2Answer('uid', $uid);
        $this->add2Answer('name', $name);
        $this->add2Answer('url', Link::getUrl(ProjectPage::UID, [$uid, ProjectPage::PUID_SETTINGS]));

        return $this->answer();
    }

    /**
     * Получает базовую информацию о задаче (название, url и id в проекте),
     * по части id или имени в проекте.
     *
     * @param $needle Начало идентификатора или часть имени.
     */
    public function searchIssueNames($projectId, $needle)
    {
        $projectId = (int)$projectId;

        try {
            $project = $this->getProjectRequireReadPermission($projectId);
            $list = Issue::searchListInProject($project->id, (string)$needle);
            $res = [];
            foreach ($list as $issue) {
                $res[] = [
                    'id' => $issue->getID(),
                    'idInProject' => $issue->idInProject,
                    'name' => $issue->name,
                    'url' => $issue->getConstURL(),
                ];
            }

            $this->add2Answer('list', $res);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Загружает список репозиториев для проекта.
     */
    public function getRepositories($projectId)
    {
        $projectId = (int)$projectId;

        try {
            $project = $this->getProjectRequireReadPermission($projectId);
            $client = $this->requireGitlabIntegration($project);
            
            $list = $client->getProjects($project->gitlabGroupId);
            $loadedProjectIds = array_map(function ($item) {
                return $item->id;
            }, $list);

            $gitlabProjectIds = $project->getGitlabProjectIds();
            foreach ($gitlabProjectIds as $projectId) {
                if (in_array($projectId, $loadedProjectIds)) {
                    continue;
                }
                $gitlabProject = $client->getProject($projectId);
                if (!empty($gitlabProject)) {
                    $list[] = $gitlabProject;
                    $loadedProjectIds[] = $projectId;
                }
            }

            // Загрузим информацию о самом используемом репозитории в этом проекте
            // из последних 5
            $popularRepositoryId = IssueBranch::loadPopularRepository($project->id, 5);
            // А также загружаем топ репозиториев для текущего пользователя
            $myPopularRepositoryIds = IssueBranch::loadPopularRepositories($project->id, $this->getUserId(), 10);

            $this->add2Answer('list', $list);
            $this->add2Answer('popularRepositoryId', $popularRepositoryId);
            $this->add2Answer('myPopularRepositoryIds', $myPopularRepositoryIds);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Загружает список веток для репозитория.
     */
    public function getBranches($projectId, $gitlabProjectId)
    {
        $projectId = (int)$projectId;
        $gitlabProjectId = (int)$gitlabProjectId;

        try {
            $project = $this->getProjectRequireReadPermission($projectId);
            $client = $this->requireGitlabIntegration($project);
           
            $list = $client->getBranches($gitlabProjectId);
            
            $this->add2Answer('list', $list);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }
    
    /**
     * Добавляет цели спринта для текущего проекта.
     * @param int $instanceId id проекта.
     * @param array $target массив целий спринта.
     */
    public function addSprintTarget($instanceId, $target)
    {
        $projectId = (int) $instanceId;
        $targetText = (string) $target;
    
        try {
            $project = Project::loadById($projectId);
            $user = $this->getUser();
            if (!$project || !$project->hasReadPermission($user)) {
                return $this->error("Проект не существует или недостаточно прав");
            }
            
            $result = Project::updateSprintTarget($projectId, $targetText);
            if (!$result) {
                return $this->error('Цели проекта не добавлены. Ошибка записи в БД.');
            }
    
            $markdownText = HTMLHelper::getMarkdownText($targetText);
            
            $this->add2Answer('targetHTML', $markdownText);
            $this->add2Answer('targetText', $targetText);
        } catch (Exception $e) {
            return $this->exception($e);
        }
        
        return $this->answer();
    }
}
