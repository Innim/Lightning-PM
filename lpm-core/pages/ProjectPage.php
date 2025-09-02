<?php
/**
 * Страница проекта.
 *
 * Сюда входит:
 * - список задач (открытых и завершенных)
 * - отображение задачи
 * - добавление/редактирование задачи
 * - участники проекта
 * - список комментов к задачам проекта
 * - скрам-борд
 * - архив спринтов
 * - статистка спринтов
 * - настройки проекта
 */
class ProjectPage extends LPMPage
{
    const UID = 'project';
    const PUID_MEMBERS = 'members';
    const PUID_ISSUES = 'issues';
    const PUID_COMPLETED_ISSUES = 'completed';
    const PUID_COMMENTS  = 'comments';
    const PUID_ISSUE = 'issue';
    const PUID_SCRUM_BOARD = 'scrum-board';
    const PUID_SCRUM_BOARD_SNAPSHOT = 'scrum-board-snapshot';
    const PUID_SPRINT_STAT = 'sprint-stat';
    const PUID_SETTINGS = 'project-settings';

    /**
     *
     * @var Project
     */
    private $_project;

    private $_issueInput;

    public function __construct()
    {
        parent::__construct(self::UID, '', true, true);
        
        $this->_js[] = 'project';

        $this->_pattern = 'project';
        
        $this->_baseParamsCount = 2;
        $this->_defaultPUID     = self::PUID_ISSUES;

        $this->addSubPage(
            self::PUID_ISSUES,
            'Список задач',
            '',
            array_merge(['project-issues'], $this->getIssuesListJs(), $this->getIssueJs())
        );
        $this->addSubPage(
            self::PUID_COMPLETED_ISSUES,
            'Завершенные',
            '',
            array_merge(['project-completed'], $this->getIssuesListJs(), $this->getIssueJs())
        );
        $this->addSubPage(
            self::PUID_COMMENTS,
            'Комментарии',
            'project-comments',
            $this->getCommentJs()
        );
        $this->addSubPage(
            self::PUID_MEMBERS,
            'Участники',
            'project-members',
            ['project/project-members', 'popups/users-chooser'],
        );
        $this->addSubPage(
            self::PUID_SETTINGS,
            'Настройки проекта',
            'project-settings',
            ['project/project-settings'],
            '',
            User::ROLE_MODERATOR
        );
    }
    
    public function init()
    {
        $engine = LightningEngine::getInstance();

        // загружаем проект, на странице которого находимся
        if ($engine->getParams()->suid == ''
            || !$this->_project = Project::load($engine->getParams()->suid)) {
            return false;
        }

        // Если это scrum проект - добавляем новый подраздел
        if ($this->_project->scrum) {
            $this->addSubPage(
                self::PUID_SCRUM_BOARD,
                'Scrum доска',
                'scrum-board',
                array_merge(['scrum-board', 'filters/scrum-board-filter'], $this->getIssueJs())
            );
            $this->addSubPage(
                self::PUID_SCRUM_BOARD_SNAPSHOT,
                'Scrum архив',
                'scrum-board-snapshot',
                $this->getIssueJs()
            );
            $this->addSubPage(
                self::PUID_SPRINT_STAT,
                'Статистика спринта',
                'sprint-stat',
                ['sprint-stat'],
                '',
                -1,
                false
            );
        }

        if (!parent::init()) {
            // Если мы на странице задачи, но не авторизовались,
            // запомним заголовок в Open Graph, чтобы в превью нормально показывалось
            if (!$this->_curSubpage && $this->getPUID() == self::PUID_ISSUE) {
                $issueId = $this->getCurrentIssueId((float)$this->getAddParam());
                if ($issueId > 0 && ($issue = Issue::load((float)$issueId))) {
                    // Получаем картинку из задачи
                    // TODO: вообще-то это не очень безопасно, т.к. OG возвращается без авторизации
                    // а в картинках теоретически может быть что-то важное.
                    // Но пока есть такой запрос, сделаем так.
                    $images = $issue->getImages();
                    $imageUrl = empty($images) ? null : $images[0]->getSource();
                    $this->SetOpenGraph($this->getTitleByIssue($issue), null, $imageUrl);
                }
            }
            
            return false;
        }
        
        // проверим, можно ли текущему пользователю смотреть этот проект
        if (!$user = LightningEngine::getInstance()->getUser()) {
            return false;
        }

        if (!$this->_project->hasReadPermission($user)) {
            return false;
        }
        
        $iCount = (int)$this->_project->getImportantIssuesCount();
        if ($iCount > 0) {
            $issuesSubPage = $this->getSubPage(self::PUID_ISSUES);
            $issuesSubPage->link->label .= " (" . $iCount . ")";
        }

        Project::$currentProject = $this->_project;
        
        $this->_header = 'Проект &quot;' . $this->_project->name . '&quot;';
        $this->_title  = $this->_project->name;
        
        // проверяем, не добавили ли задачу или может отредактировали
        if (isset($_POST['actionType'])) {
            if ($_POST['actionType'] == 'addIssue') {
                $this->handleFormAction();
            } elseif ($_POST['actionType'] == 'editIssue' && isset($_POST['issueId'])) {
                $this->handleFormAction(true);
            }
        }

        if (!empty($this->_issueInput)) {
            $this->addTmplVar('input', $this->_issueInput);
        }
        
        $subPageUid = $this->_curSubpage ? $this->_curSubpage->uid : null;
        switch ($subPageUid) {
            case null: {
                // может быть это страница просмотра задачи?
                if ($this->getPUID() == self::PUID_ISSUE) {
                    $this->initIssue();
                    break;
                }
            }
            case self::PUID_ISSUES:{
                $this->initIssues();
                break;
            }
            case self::PUID_COMPLETED_ISSUES: {
                $this->initCompletedIssues();
                break;
            }
            case self::PUID_COMMENTS: {
                $this->initComments();
                break;
            }
            case self::PUID_MEMBERS: {
                $this->initMembers($user);
                break;
            }
            case self::PUID_SCRUM_BOARD: {
                $this->initScrumBoard();
                break;
            }
            case self::PUID_SCRUM_BOARD_SNAPSHOT: {
                $this->initScrumBoardSnapshot();
                break;
            }
            case self::PUID_SPRINT_STAT: {
                $this->initSprintStat();
                break;
            }
            case self::PUID_SETTINGS: {
                $this->initSettings();
                break;
            }
        }
        
        return $this;
    }

    private function initIssues()
    {
        // загружаем задачи
        $openedIssues = $this->loadIssues([Issue::STATUS_IN_WORK, Issue::STATUS_WAIT]);
            
        $this->addTmplVar('issues', $openedIssues);
    }

    private function initIssue()
    {
        $issueId = $this->getCurrentIssueId((float)$this->getAddParam());
        if ($issueId <= 0 || !$issue = Issue::load((float)$issueId)) {
            LightningEngine::go2URL($this->getUrl());
        }
        
        $issue->getMembers();
        $issue->getTesters();
        
        $comments = Comment::getListByInstance(LPMInstanceTypes::ISSUE, $issue->id);
        foreach ($comments as $comment) {
            $comment->issue = $issue;
        }

        $this->_title = $this->getTitleByIssue($issue);
        $this->_pattern = 'issue';
        ArrayUtils::remove($this->_js, 'project');
        $this->_js = array_merge(
            ['issue', 'popups/create-branch', 'popups/pass-test', 'popups/select-project'],
            $this->getIssueJs(),
            $this->getCommentJs()
        );

        $this->addTmplVar('issue', $issue);
        $this->addTmplVar('comments', $comments);
    }

    private function initCompletedIssues()
    {
        // загружаем  завершенные задачи
        $completedIssues = $this->loadIssues([Issue::STATUS_COMPLETED]);
        $this->addTmplVar('issues', $completedIssues);
    }

    private function initComments()
    {
        $page = $this->getProjectedCommentsPage();
        $commentsPerPage = 100;

        $comments = Comment::getIssuesListByProject(
            $this->_project->id,
            ($page - 1) * $commentsPerPage,
            $commentsPerPage
        );
        $issueIds = [];
        $commentsByIssueId = [];
        foreach ($comments as $comment) {
            if (!isset($commentsByIssueId[$comment->instanceId])) {
                $commentsByIssueId[$comment->instanceId] = [];
                $issueIds[] = $comment->instanceId;
            }
            $commentsByIssueId[$comment->instanceId][] = $comment;
        }

        $issues = Issue::loadListByIds($issueIds);
        foreach ($issues as $issue) {
            if (isset($commentsByIssueId[$issue->id])) {
                foreach ($commentsByIssueId[$issue->id] as $comment) {
                    $comment->issue = $issue;
                }
            }
        }

        $this->addTmplVar('project', $this->_project);
        $this->addTmplVar('comments', $comments);
        $this->addTmplVar('page', $page);
        if ($page > 1) {
            $this->addTmplVar('prevPageUrl', $this->getUrl('page', $page - 1));
        }
        // Упрощенная проверка, да, есть косяк если общее кол-во комментов делиться нацело
        if (count($comments) === $commentsPerPage) {
            $this->addTmplVar('nextPageUrl', $this->getUrl('page', $page + 1));
        }
    }

    private function initMembers(User $user)
    {
        $project = $this->_project;
        $canEdit = $user->isModerator();

        $projectMembers = $project->getMembers(true);
        $projectTester = $project->getTester();

        $labels = Issue::getLabels($project->id);
        
        $this->addTmplVar('project', $project);
        $this->addTmplVar('projectMembers', $projectMembers);
        $this->addTmplVar('projectTester', $projectTester);
        $this->addTmplVar('canEdit', $canEdit);
        $this->addTmplVar('labels', $labels);
    }

    private function initScrumBoard()
    {
        $this->addTmplVar('project', $this->_project);
        $this->addTmplVar('stickers', ScrumSticker::loadBoard($this->_project->id));
    }

    private function initScrumBoardSnapshot()
    {
        $this->addTmplVar('project', $this->_project);
        $snapshots = ScrumStickerSnapshot::loadList($this->_project->id);
        $this->addTmplVar('snapshots', $snapshots);

        $sidInProject = (int) $this->getParam(3);

        if ($sidInProject > 0) {
            foreach ($snapshots as $key => $snapshot) {
                if ($snapshot->idInProject == $sidInProject) {
                    $this->addTmplVar('snapshot', $snapshot);

                    // Массив отсортирован по дате, поэтому здесь идём в обратную сторону
                    if ($key > 0) {
                        $this->addTmplVar('nextSnapshot', $snapshots[$key - 1]);
                    }

                    if (($nextKey = $key + 1) < count($snapshots)) {
                        $this->addTmplVar('prevSnapshot', $snapshots[$nextKey]);
                    }

                    break;
                }
            }
        }
    }

    private function initSprintStat()
    {
        $sidInProject = (int) $this->getParam(3);
        $snapshot = ScrumStickerSnapshot::loadSnapshot($this->_project->id, $sidInProject);

        if (empty($snapshot)) {
            LightningEngine::go2URL($this->getBaseUrl(self::PUID_SCRUM_BOARD_SNAPSHOT));
            return false;
        }

        $this->addTmplVar('project', $this->_project);
        $this->addTmplVar('snapshot', $snapshot);
    }

    private function initSettings()
    {
        $this->addTmplVar('project', $this->_project);
    }

    private function getIssuesListJs()
    {
        return [
            'issues-export-to-excel',
            'filters/issue-list-filter', 
        ];
    }

    private function getIssueJs()
    {
        return [
            'issues',
            'filters/issues-filter',
            'issue-form',
            'formatting',
            'libs/tribute',
            'libs/character-counter',
        ];
    }

    private function getCommentJs()
    {
        return [
            'comments',
            'attachments',
            'formatting',
        ];
    }
    
    /**
     * Глобальный номер задания
     * @param mixed $idInProject
     * @return $issueId
     */
    private function getCurrentIssueId($idInProject)
    {
        return Issue::loadIssueId($this->_project->id, $idInProject);
    }
    
    /**
     * Номер последнего задания в проекте
     * @return idInProject
     */
    private function getLastIssueId()
    {
        return Issue::getLastIssueId($this->_project->id);
    }

    private function loadIssues($statuses) 
    {
        $projectId = $this->_project->id;

        $loadMembers = true;
        $loadTesters = true;
        $loadMasters = false;
        // Загружаем всех участников задач (для оптимизации)
        $issueParticipants = Member::loadListAnyForIssuesInProject($projectId, $statuses, $loadMembers, $loadTesters, $loadMasters);

        $list = Issue::loadListByProject($projectId, $statuses);
        foreach ($list as $issue) {
            $issue->extractParticipantsFrom($issueParticipants, $loadMembers, $loadTesters, $loadMasters);
        }
        return $list;
    }
    
    private function handleFormAction($editMode = false)
    {
        $engine = $this->_engine;
        $userId = $engine->getAuth()->getUserId();

        // TODO: вынеси отсюда все сохранение и выделить работу с БД
        $db = LPMGlobals::getInstance()->getDBConnect();
        // Сохраняем весь input, чтобы в случае ошибки восстановить форму
        $this->_issueInput = [
            'data' => $_POST,
        ];

        $projectId = $this->_project->id;

        // если это редактирование, то проверим идентификатор задачи
        // на соответствие её проекту и права пользователя
        if ($editMode) {
            $issueId = (float)$_POST['issueId'];
            $curIssue = Issue::load($issueId);

            if (empty($curIssue) || $curIssue->projectId !== $projectId) {
                return $engine->addError('Нет такой задачи для текущего проекта');
            }

            if (!$curIssue->checkEditPermit($userId)) {
                return $engine->addError('У вас нет прав для редактирования этой задачи');
            }

            $idInProject = $curIssue->idInProject;
            $issueName = $curIssue->name;
        } else {
            $issueId = null;
            $idInProject = (int)$this->getLastIssueId();
            $issueName = null;
        }

        if (!$this->checkRequiredFields($_POST)) {
            $this->addError('Заполнены не все обязательные поля');
            return;
        }

        if (!$this->validateInputData($_POST, $completeDateArr)) {
            return;
        }

        if (isset($curIssue)) {
            $revision = isset($_POST['revision']) ? trim($_POST['revision']) : null;
            if (!$this->unlockIssue($curIssue, $userId, $revision)) {
                return;
            }
        }

        // TODO наверное нужен "белый список" тегов
        $_POST['desc'] = str_replace('%', '%%', $_POST['desc']);
        $_POST['hours']= str_replace('%', '%%', $_POST['hours']);
        $_POST['name'] = trim(str_replace('%', '%%', $_POST['name']));

        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['members', 'clipboardImg', 'imgUrls', 'testers', 'membersSp', 'masters'])) {
                $_POST[$key] = $db->real_escape_string($value);
            }
        }

        $type = (int)$_POST['type'];
        $completeDate = empty($completeDateArr) ? null : $completeDateArr[3] . '-' .
                        $completeDateArr[2] . '-' .
                        $completeDateArr[1] . ' ' .
                        '00:00:00';
        $priority = min(99, max(0, (int)$_POST['priority']));

        // Обновляем меткам кол-во использований.
        $origLabels = Issue::getLabelsByName($_POST['name']);
        $labels = array_merge($origLabels);

        if ($issueName != null) {
            $oldLabels = Issue::getLabelsByName($issueName);
            foreach ($labels as $key => $value) {
                if (in_array($value, $oldLabels)) {
                    unset($labels[$key]);
                }
            }
        }

        if (!empty($labels)) {
            $allLabels = Issue::getLabels($projectId);
            $countedLabels = [];
            foreach ($allLabels as $value) {
                $index = array_search($value['label'], $labels);
                if ($index !== false) {
                    $countedLabels[] = $labels[$index];
                    unset($labels[$index]);
                }
            }

            if (!empty($countedLabels)) {
                Issue::addLabelsUsing($countedLabels, $this->_project->id);
            }

            if (!empty($labels)) {
                foreach ($labels as $newLabel) {
                    Issue::saveLabel($newLabel, $this->_project->id, 0, 1);
                }
            }
        }

        // Считаем SP
        $hours = $this->parseSP($_POST['hours']);
        $membersSp = null;
        if (isset($_POST['membersSp']) && is_array($_POST['membersSp'])) {
            $membersSp = [];
            $spTotal = 0;
            foreach ($_POST['membersSp'] as $sp) {
                $sp = $this->parseSP($sp, true);
                $membersSp[] = $sp;
                $spTotal += $sp;
            }

            if ($spTotal > 0 && $spTotal != $hours) {
                return $engine->addError('Количество SP по исполнителям не совпадает с общим');
            }
        }

        // сохраняем задачу
        $issueId = $this->saveIssue($db, $issueId, $idInProject, $_POST['name'], $_POST['desc'], $userId, $hours, $type, $completeDate, $priority);
        if (!$issueId) return;

        if (!$editMode) {
            $this->saveLinkedIssues($userId, $issueId, $_POST['baseIds'], false);
            $this->saveLinkedIssues($userId, $issueId, $_POST['linkedIds'], true);
        }

        // Сохраняем участников
        $memberIds = empty($_POST['members']) || !is_array($_POST['members']) ? [] : $_POST['members'];
        if (!$this->saveMembers($db, $issueId, $memberIds, $editMode, $membersSp)) {
            return;
        }

        // Сохраняем тестеров
        $testers = isset($_POST['testers']) ? $_POST['testers'] : [];
        if (!$this->saveTesters($db, $issueId, $testers, $editMode)) {
            return;
        }

        // Сохраняем мастеров
        $masters = isset($_POST['masters']) ? $_POST['masters'] : [];
        if (!$this->saveMasters($db, $issueId, $masters, $editMode)) {
            return;
        }

        // удаление старых изображений
        if (!empty($_POST["removedImages"])) {
            $this->removeImagesFromIssue($db, $issueId, $_POST["removedImages"]);
        }

        // загружаем изображения
        if ($editMode) {
            // если задача редактируется
            // считаем из базы кол-во картинок, имеющихся для задачи
            $loadedImgs = LPMImg::loadCountByInstance(LPMInstanceTypes::ISSUE, $issueId);
        } else {
            // если добавляется
            $loadedImgs = 0;
        }

        $uploader = $this->saveImages4Issue($issueId, $loadedImgs);

        if ($uploader === false) {
            return $engine->addError('Не удалось загрузить изображение');
        }

        // перезагружаем данные задачи
        $issue = Issue::load($issueId);
        if (!$issue) {
            $engine->addError('Не удалось загрузить данные задачи');
            return;
        }

        // Если это SCRUM проект
        if ($this->_project->scrum) {
            $putOnBoard = !empty($_POST['putToBoard']);
            if (!$this->updateScrumBoard($issue, $putOnBoard)) return;
        }

        // обновляем счетчики изображений
        if ($uploader->getLoadedCount() > 0 || $editMode) {
            Issue::updateImgsCounter($issueId, $uploader->getLoadedCount());
        }
        
        // отсылаем оповещения
        $this->notifyAboutIssueChange($issue, $editMode);

        Project::updateIssuesCount($issue->projectId);

        // Записываем лог
        UserLogEntry::create(
            $userId,
            DateTimeUtils::$currentDate,
            $editMode ? UserLogEntryType::EDIT_ISSUE : UserLogEntryType::ADD_ISSUE,
            $issue->id,
            $editMode ? 'Full edit' : ''
        );

        // Очищаем сохраненные данные
        $this->_issueInput = null;
    
        $issueURL = $this->getBaseUrl(ProjectPage::PUID_ISSUE, $idInProject);
        LightningEngine::go2URL($issueURL);
    }

    private function checkRequiredFields()
    {
        $required = ['type', 'priority'];
        $notEmpty = ['name'];

        foreach ($required as $field) {
            if (!isset($_POST[$field])) {
                return false;
            }
        }

        foreach ($notEmpty as $field) {
            if (empty($_POST[$field])) {
                return false;
            }
        }
     
        return true;
    }

    private function getProjectedCommentsPage()
    {
        return $this->getPageArg();
    }

    private function saveLinkedIssues($userId, $issueId, $linkedIdsInput, $isCurrentBase)
    {
        $linkedIds = empty($linkedIdsInput) ? null : explode(',', $linkedIdsInput);
        if (empty($linkedIds)) {
            return;
        }

        foreach ($linkedIds as $linkedId) {
            $linkedId = (int)$linkedId;
            if ($linkedId > 0) {
                $linkedIssue = Issue::load($linkedId);

                if ($linkedIssue != null && $linkedIssue->checkViewPermit($userId)) {
                    IssueLinked::create(
                        $isCurrentBase ? $issueId : $linkedIssue->id,
                        $isCurrentBase ? $linkedIssue->id : $issueId,
                        DateTimeUtils::$currentDate
                    );
                }
            }
        }
    }

    private function validateInputData($input, &$completeDateMatches) 
    {
        $inputCompleteDate = $input['completeDate'];
        if (!empty($inputCompleteDate)) {
            $res = preg_match(
                "/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/",
                $inputCompleteDate,
                $completeDateMatches
            );
            if (!$res) 
            {
                 return $this->addError('Недопустимый формат даты. Требуется формат ДД/ММ/ГГГГ');
            }
        }

        $type = (int)$input['type'];
        if (!in_array($type, [Issue::TYPE_BUG, Issue::TYPE_DEVELOP, Issue::TYPE_SUPPORT])) {
            $this->addError('Недопустимый тип');
        }
        
        if ($_POST['priority'] < 0 || $_POST['priority'] > 99) {
            $this->addError('Недопустимое значение приоритета');
        } 
        
        if (mb_strlen($_POST['desc']) > Issue::DESC_MAX_LEN) {
            $this->addError('Слишком длинное описание. Максимальная длина: ' . Issue::DESC_MAX_LEN . ' символов');
        } 

        return !$this->hasErrors();
    }

    private function unlockIssue(Issue $issue, $userId,  $revision)
    {
        if ($revision === null) {
            return $this->addError('Требуется указать ревизию задачи для разблокировки');
        }

        if ($issue->revision != $revision) {
            // TODO: опцию переписать изменения
            return $this->addError('Задача была изменена кем-то другим. Невозможно сохранить изменения');
        }

        $issueId = $issue->getId();
        $lock = UserLock::getIssueLock($issueId);
        if (empty($lock)) return true;

        if ($userId != $lock->userId) {
            // TODO: опцию перехватить блокировку
            // TODO: данные о блокировке для отображения
            return $this->addError('Задача заблокирована другим пользователем. Невозможно сохранить изменения');
        }

        UserLock::removeIssueLocks($issueId);

        return true;
    }

    private function saveIssue(DBConnect $db, $issueId, $idInProject, $name, $desc, $userId, $hours, $type, $completeDate, $priority) 
    {
        $issueIdVal = $issueId === null ? 'NULL' : $issueId;
        $revision = Issue::getNewRevision();
        $sql = "INSERT INTO `%s` (`id`, `projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
                                    "`authorId`, `createDate`, `completeDate`, `priority`, `revision` ) " .
                            "VALUES (". $issueIdVal . ", '" . $this->_project->id . "', '" . $idInProject . "', " .
                                        "'" . $name . "', '" . $hours . "', '" . $desc . "', " .
                                        "'" . $type . "', " .
                                        "'" . $userId . "', " .
                                    "'" . DateTimeUtils::mysqlDate() . "', " .
                                    "" . (empty($completeDate) ? 'NULL' :  "'" . $completeDate  . "'") . ", " .
                                    "'" . $priority . "', '" . $revision . "' ) " .
        "ON DUPLICATE KEY UPDATE `name` = VALUES( `name` ), " .
                                "`hours` = VALUES( `hours` ), " .
                                "`desc` = VALUES( `desc` ), " .
                                "`type` = VALUES( `type` ), " .
                                "`completeDate` = VALUES( `completeDate` ), " .
                                "`priority` = VALUES( `priority` ), " .
                                "`revision` = VALUES( `revision` )";

         if (!$db->queryt($sql, LPMTables::ISSUES)) {
            return $this->addError('Ошибка записи в базу');
         }

         if ($issueId === null) {
             $issueId = $db->insert_id;
         }

         return $issueId;
    }

    private function saveImages4Issue($issueId, $hasCnt = 0)
    {
        $uploader = new LPMImgUpload(
            Issue::MAX_IMAGES_COUNT - $hasCnt,
            true,
            [LPMImg::PREVIEW_WIDTH, LPMImg::PREVIEW_HEIGHT],
            'issues',
            'scr_',
            LPMInstanceTypes::ISSUE,
            $issueId,
            false
        );

        // Выполняем загрузку для изображений из поля загрузки
        // Вставленных из буфера
        // И добавленных по URL
        if (!$uploader->uploadViaFiles('images') ||
            isset($_POST['clipboardImg']) && !$uploader->uploadFromBase64($_POST['clipboardImg']) ||
            isset($_POST['imgUrls']) && !$uploader->uploadFromUrls($_POST['imgUrls'])) {
            $errors = $uploader->getErrors();
            $this->_engine->addError($errors[0]);
            return false;
        }
        return $uploader;
    }

    private function removeImagesFromIssue(DBConnect $db, $issueId, $imagesIdsStr) 
    {
        $delImg = explode(',', $imagesIdsStr);
        $imgIds = [];
        foreach ($delImg as $imgId) {
            $imgId = (int)$imgId;
            if ($imgId > 0) {
                $imgIds[] = $imgId;
            }
        }

        if (!empty($imgIds)) {
            $sql = "UPDATE `%s` ".
                        "SET `deleted`='1' ".
                        "WHERE `imgId` IN (".implode(',', $imgIds).") ".
                            "AND `deleted` = '0' ".
                            "AND `itemId`='".$issueId."' ".
                            "AND `itemType`='".LPMInstanceTypes::ISSUE."'";
            $db->queryt($sql, LPMTables::IMAGES);
        }
    }

    private function saveMembers($db, $issueId, $memberIds, $editMode, $spByMembers = null)
    {
        $engine = $this->_engine;
        $users4Delete = [];
        if (!$this->saveMembersByInstanceType(
            $db,
            $issueId,
            $memberIds,
            $editMode,
            LPMInstanceTypes::ISSUE,
            $users4Delete
        )) {
            return false;
        }
            
        // Удаляем пользователей из таблицы информации об участниках задачи
        if (!empty($users4Delete) && !IssueMember::deleteInfo($issueId, $users4Delete)) {
            return $engine->addError('Ошибка при удалении информации об участниках');
        }

        if ($this->_project->scrum) {
            $membersCount = count($memberIds);
            if ($membersCount > 0) {
                if ($spByMembers == null || !is_array($spByMembers)) {
                    return $engine->addError('Требуется количество SP по участникам');
                }

                if (count($spByMembers) != $membersCount) {
                    return $engine->addError('Количество SP по участникам не соответствует количеству участников');
                }
            }

            // Записываем информацию об участниках
            $sql = "REPLACE INTO `%s` (`userId`, `instanceId`, `sp`) VALUES (?, '" . $issueId . "', ?)";
            if (!$prepare = $db->preparet($sql, LPMTables::ISSUE_MEMBER_INFO)) {
                return $engine->addError('Ошибка при сохранении информации об участниках');
            }

            foreach ($memberIds as $i => $memberId) {
                $memberId = (float)$memberId;
                $sp = $spByMembers[$i];
                $prepare->bind_param('dd', $memberId, $sp);
                $prepare->execute();
            }

            $prepare->close();
        }

        return true;
    }

    private function saveTesters($db, $issueId, $testerIds, $editMode)
    {
        return $this->saveMembersByInstanceType(
            $db,
            $issueId,
            $testerIds,
            $editMode,
            LPMInstanceTypes::ISSUE_FOR_TEST
        );
    }

    private function saveMasters($db, $issueId, $masterIds, $editMode)
    {
        return $this->saveMembersByInstanceType(
            $db,
            $issueId,
            $masterIds,
            $editMode,
            LPMInstanceTypes::ISSUE_FOR_MASTER
        );
    }

    private function saveMembersByInstanceType($db, $issueId, $userIds, $editMode, $instanceType, &$users4Delete = null)
    {
        $engine = $this->_engine;
        if (empty($userIds)) {
            $userIds = [];
        }
        if ($editMode) {
            // выберем из базы текущих участников
            $sql = "SELECT `userId` FROM `%s` " .
                    "WHERE `instanceType` = '" . $instanceType . "' " .
                      "AND `instanceId` = '" . $issueId . "'";
            if (!$query = $db->queryt($sql, LPMTables::MEMBERS)) {
                return $engine->addError('Ошибка загрузки участников');
            }
            
            if ($users4Delete === null) {
                $users4Delete = [];
            }
            while ($row = $query->fetch_assoc()) {
                $tmpId = (float)$row['userId'];
                $userInArr = false;
                foreach ($userIds as $i => $memberId) {
                    if ($memberId == $tmpId) {
                        ArrayUtils::removeByIndex($userIds, $i);
                        $userInArr = true;
                        break;
                    }
                }
                if (!$userInArr) {
                    $users4Delete[] = $tmpId;
                }
            }
            
            if (!empty($users4Delete) && !Member::deleteMembers($instanceType, $issueId, $users4Delete)) {
                return $engine->addError('Ошибка при удалении участников');
            }
        }
        if (empty($userIds)) {
            return true;
        }

        // сохраняем исполнителей задачи
        $sql = "INSERT INTO `%s` ( `userId`, `instanceType`, `instanceId` ) " .
                         "VALUES ( ?, '" . $instanceType . "', '" . $issueId . "' )";
        if (!$prepare = $db->preparet($sql, LPMTables::MEMBERS)) {
            if (!$editMode) {
                $db->queryt("DELETE FROM `%s` WHERE `id` = '" . $issueId . "'", LPMTables::ISSUES);
            }
            return $engine->addError('Ошибка при сохранении участников');
        } else {
            $saved = [];
            foreach ($userIds as $memberId) {
                $memberId = (float)$memberId;
                if (!in_array($memberId, $saved)) {
                    $prepare->bind_param('d', $memberId);
                    $prepare->execute();
                    $saved[] = $memberId;
                }
            }
            $prepare->close();
            return true;
        }
    }

    private function updateScrumBoard(Issue $issue, $putOnBoard)
    {
        if ($issue->isOnBoard() != $putOnBoard) {
            if ($putOnBoard) {
                if (!ScrumSticker::putStickerOnBoard($issue)) {
                    return $this->addError('Не удалось поместить стикер на доску');
                }
            } else {
                if (!ScrumSticker::updateStickerState(
                    $issue->id,
                    ScrumStickerState::BACKLOG
                )) {
                    return $this->addError('Не удалось снять стикер с доски');
                }
            }
        }

        return true;
    }

    private function notifyAboutIssueChange(Issue $issue, $editMode)
    {
        $engine = $this->_engine;
        $user = $engine->getUser();
        if ($editMode) {
            Issue::notifyByEmail(
                $issue,
                'Изменена задача "' . $issue->name . '"',
                IssueEmailFormatter::issueChangedText($issue, $user),
                EmailNotifier::PREF_EDIT_ISSUE
            );
        } else {
            Issue::notifyByEmail(
                $issue,
                'Добавлена задача "' . $issue->name . '"',
                IssueEmailFormatter::issueAddedText($issue, $user),
                EmailNotifier::PREF_ADD_ISSUE,
                false
            );
        }
    }

    private function getTitleByIssue(Issue $issue)
    {
        return '#' . $issue->idInProject . '. ' . $issue->name . ' - ' . $this->_project->name;
    }

    private function parseSP($value, $allowFloat = false)
    {
        // из дробных разрешаем только 1/2
        return ($value == "0.5" || $value == "0,5" || $value == "1/2") ? 0.5 :
            ($allowFloat ? floatval(str_replace(',', '.', (string)$value)) : (int)$value);
    }
}
