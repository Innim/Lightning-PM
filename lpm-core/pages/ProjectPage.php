<?php
/**
 * Страница проекта.
 *
 * Сюда входит:
 * - список задач (открытых и завершенных)
 * - отображение задачи
 * - добавление/редакторивание задачи
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
    private $_currentPage;

    private $_issueInput;

    public function __construct()
    {
        parent::__construct(self::UID, '', true, true);
        
        array_push(
            $this->_js,
            'project',
            'issues',
            'issue-form',
            'comments',
            'attachments',
            'libs/tribute'
        );

        $this->_pattern = 'project';
        
        $this->_baseParamsCount = 2;
        $this->_defaultPUID     = self::PUID_ISSUES;

        $this->addSubPage(
            self::PUID_ISSUES,
            'Список задач',
            '',
            ['project-issues', 'issues-export-to-excel']
        );
        $this->addSubPage(
            self::PUID_COMPLETED_ISSUES,
            'Завершенные',
            '',
            ['project-completed', 'issues-export-to-excel']
        );
        $this->addSubPage(self::PUID_COMMENTS, 'Комментарии', 'project-comments');
        $this->addSubPage(
            self::PUID_MEMBERS,
            'Участники',
            'project-members',
            ['users-chooser'],
            '',
            User::ROLE_MODERATOR
        );
        $this->addSubPage(
            self::PUID_SETTINGS,
            'Настройки проекта',
            'project-settings',
            ['project-settings'],
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
            $this->addSubPage(self::PUID_SCRUM_BOARD, 'Scrum доска', 'scrum-board', ['scrum-board']);
            $this->addSubPage(self::PUID_SCRUM_BOARD_SNAPSHOT, 'Scrum архив', 'scrum-board-snapshot');
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
                $issueId = $this->getCurentIssueId((float)$this->getAddParam());
                if ($issueId > 0 && ($issue = Issue::load((float)$issueId))) {
                    // Получаем картинку из задачи
                    // TODO: вообще-то это не очень безопасно, т.к. OG возвращается без авторизации
                    // а в картинках теоретически может быть что-то важное.
                    // Но пока есть такоей запрос, сделаем так.
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
                $this->saveIssue();
            } elseif ($_POST['actionType'] == 'editIssue' && isset($_POST['issueId'])) {
                $this->saveIssue(true);
            } elseif ($_POST['actionType'] == 'editIssueLabel') {
                $this->saveLabel();
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
        $openedIssues = Issue::loadListByProject(
            $this->_project->id,
            [Issue::STATUS_IN_WORK, Issue::STATUS_WAIT]
        );
        $this->addTmplVar('issues', $openedIssues);
    }

    private function initIssue()
    {
        $issueId = $this->getCurentIssueId((float)$this->getAddParam());
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
        array_push($this->_js, 'issue', 'create-branch');

        $this->addTmplVar('issue', $issue);
        $this->addTmplVar('comments', $comments);
    }

    private function initCompletedIssues()
    {
        // загружаем  завершенные задачи
        $completedIssues = Issue::loadListByProject(
            $this->_project->id,
            [Issue::STATUS_COMPLETED]
        );
        $this->addTmplVar('issues', $completedIssues);
    }

    private function initComments()
    {
        $page = $this->getProjectedCommentsPage();
        $commentsPerPage = 100;

        $this->_currentPage = $page;

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

    private function initScrumBoard()
    {
        $this->addTmplVar('project', $this->_project);
        $this->addTmplVar('stickers', ScrumSticker::loadList($this->_project->id));
    }

    private function initScrumBoardSnapshot()
    {
        $this->addTmplVar('project', $this->_project);
        $snapshots = ScrumStickerSnapshot::loadList($this->_project->id);
        $this->addTmplVar('snapshots', $snapshots);

        $sidInProject = (int) $this->getParam(3);

        if ($sidInProject > 0) {
            foreach ($snapshots as $snapshot) {
                if ($snapshot->idInProject == $sidInProject) {
                    $this->addTmplVar('snapshot', $snapshot);
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
    
    /**
     * Глобальный номер задания
     * @param mixed $idInProject
     * @return $issueId
     */
    private function getCurentIssueId($idInProject)
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
    
    private function saveIssue($editMode = false)
    {
        $engine = $this->_engine;
        // TODO: вынеси отсюда все сохрание и выделить работу с БД
        $db = LPMGlobals::getInstance()->getDBConnect();
        // Сохраняем весь input, чтобы в случае ошибки восстановить форму
        $this->_issueInput = [
            'data' => $_POST,
        ];

        // если это редактирование, то проверим идентификатор задачи
        // на соответствие её проекту и права пользователя
        if ($editMode) {
            $issueId = (float)$_POST['issueId'];
            
            // проверяем что такая задача есть и она принадлежит текущему проекту
            $sql = "SELECT `id`, `idInProject`, `name` FROM `%s` WHERE `id` = '" . $issueId . "' " .
                                           "AND `projectId` = '" . $this->_project->id . "'";
            if (!$query = $db->queryt($sql, LPMTables::ISSUES)) {
                return $engine->addError('Ошибка записи в базу');
            }
            
            if ($query->num_rows == 0) {
                return $engine->addError('Нет такой задачи для текущего проекта');
            }
            $result = $query->fetch_assoc();
            $idInProject = $result['idInProject'];
            $issueName = $result['name'];
        // TODO проверка прав
        } else {
            $issueId = 'NULL';
            $idInProject = (int)$this->getLastIssueId();
            $issueName = null;
        }
        
        if (empty($_POST['name']) || !isset($_POST['members'])
                || !isset($_POST['type']) || empty($_POST['completeDate'])
                || !isset($_POST['priority'])) {
            $engine->addError('Заполнены не все обязательные поля');
        } elseif (preg_match(
            "/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/",
            $_POST['completeDate'],
            $completeDateArr
        ) == 0) {
            $engine->addError('Недопустимый формат даты. Требуется формат ДД/ММ/ГГГГ');
        } elseif ($_POST['type'] != Issue::TYPE_BUG && $_POST['type'] != Issue::TYPE_DEVELOP) {
            $engine->addError('Недопустимый тип');
        } elseif (!is_array($_POST['members']) || empty($_POST['members'])) {
            $engine->addError('Необходимо указать хотя бы одного исполнителя проекта');
        } elseif ($_POST['priority'] < 0 || $_POST['priority'] > 99) {
            $engine->addError('Недопустимое значение приоритета');
        } else {
            // TODO наверное нужен "белый список" тегов
            $_POST['desc'] = str_replace('%', '%%', $_POST['desc']);
            $_POST['hours']= str_replace('%', '%%', $_POST['hours']);
            $_POST['name'] = trim(str_replace('%', '%%', $_POST['name']));

            foreach ($_POST as $key => $value) {
                if ($key != 'members' && $key != 'clipboardImg' && $key != 'imgUrls' && $key != 'testers' && $key != 'membersSp') {
                    $_POST[$key] = $db->real_escape_string($value);
                }
            }

            $_POST['type'] = (int)$_POST['type'];
            
            $completeDate = $completeDateArr[3] . '-' .
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
                $allLabels = Issue::getLabels();
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
            $userId = $engine->getAuth()->getUserId();
            $sql = "INSERT INTO `%s` (`id`, `projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
                                      "`authorId`, `createDate`, `completeDate`, `priority` ) " .
                                "VALUES (". $issueId . ", '" . $this->_project->id . "', '" . $idInProject . "', " .
                                         "'" . $_POST['name'] . "', '" . $hours . "', '" . $_POST['desc'] . "', " .
                                         "'" . (int)$_POST['type'] . "', " .
                                         "'" . $userId . "', " .
                                      "'" . DateTimeUtils::mysqlDate() . "', " .
                                      "'" . $completeDate . "', " .
                                      "'" . $priority . "' ) " .
            "ON DUPLICATE KEY UPDATE `name` = VALUES( `name` ), " .
                                    "`hours` = VALUES( `hours` ), " .
                                    "`desc` = VALUES( `desc` ), " .
                                    "`type` = VALUES( `type` ), " .
                                    "`completeDate` = VALUES( `completeDate` ), " .
                                    "`priority` = VALUES( `priority` )";

            if (!$db->queryt($sql, LPMTables::ISSUES)) {
                $engine->addError('Ошибка записи в базу');
            } else {
                if (!$editMode) {
                    $issueId = $db->insert_id;

                    $baseId = (int)$_POST['baseIdInProject'];
                    if ($baseId > 0) {
                        $baseIssue = Issue::loadByIdInProject($this->_project->id, $baseId);
                        if ($baseIssue != null) {
                            $textComment = "Задача по доделкам: " .
                                Issue::getConstURLBy($this->_project->uid, $idInProject);
                            Comment::add(
                                LPMInstanceTypes::ISSUE,
                                $baseIssue->id,
                                $engine->getAuth()->getUserId(),
                                $textComment
                            );
                        }
                    }
                }

                // Валидируем заданное количество SP по участникам

                // Сохраняем участников
                if (!$this->saveMembers($db, $issueId, $_POST['members'], $editMode, $membersSp)) {
                    return;
                }

                // Сохраняем тестеров
                $testers = isset($_POST['testers']) ? $_POST['testers'] : [];
                if (!$this->saveTesters($db, $issueId, $testers, $editMode)) {
                    return;
                }

                //удаление старых изображений
                if (!empty($_POST["removedImages"])) {
                    $delImg = $_POST["removedImages"];
                    $delImg = explode(',', $delImg);
                    $imgIds = [];
                    foreach ($delImg as $imgIt) {
                        $imgIt = (int)$imgIt;
                        if ($imgIt > 0) {
                            $imgIds[] = $imgIt;
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

                // загружаем изображения
                if ($editMode) {
                    // если задача редактируется
                    // считаем из базы кол-во картинок, имеющихся для задачи
                    $sql = "SELECT COUNT(*) AS `cnt` FROM `%s` " .
                        "WHERE `itemId` = '" . $issueId. "'".
                        "AND `itemType` = '" . LPMInstanceTypes::ISSUE . "' " .
                        "AND `deleted` = '0'";
                    
                    if ($query = $db->queryt($sql, LPMTables::IMAGES)) {
                        $row = $query->fetch_assoc();
                        $loadedImgs = (int)$row['cnt'];
                    } else {
                        $engine->addError('Ошибка доступа к БД. Не удалось загрузить количество изображений');
                        return;
                    }
                } else {
                    // если добавляется
                    $loadedImgs = 0;
                }

                $uploader = $this->saveImages4Issue($issueId, $loadedImgs);

                if ($uploader === false) {
                    return $engine->addError('Не удалось загрузить изображение');
                }

                $issue = Issue::load($issueId);
                if (!$issue) {
                    $engine->addError('Не удалось загрузить данные задачи');
                    return;
                }

                // Если это SCRUM проект
                if ($this->_project->scrum) {
                    $putOnBoard = !empty($_POST['putToBoard']);
                    if ($issue->isOnBoard() != $putOnBoard) {
                        if ($putOnBoard) {
                            if (!ScrumSticker::putStickerOnBoard($issue)) {
                                return $engine->addError('Не удалось поместить стикер на доску');
                            }
                        } else {
                            if (!ScrumSticker::updateStickerState(
                                $issue->id,
                                ScrumStickerState::BACKLOG
                            )) {
                                return $engine->addError('Не удалось снять стикер с доски');
                            }
                        }
                    }
                }

                // обновляем счетчики изображений
                if ($uploader->getLoadedCount() > 0 || $editMode) {
                    Issue::updateImgsCounter($issueId, $uploader->getLoadedCount());
                }
                
                $issueURL = $this->getBaseUrl(ProjectPage::PUID_ISSUE, $idInProject);
                
                // отсылаем оповещения
                $this->notifyAboutIssueChange($issue, $issueURL, $editMode);

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
            
                LightningEngine::go2URL($issueURL);
            }
        }
    }

    private function getProjectedCommentsPage()
    {
        // $page = $this->getParam($this->_baseParamsCount + 1);
        // return empty($page) ? 1 : (int)$page;
        return $this->getPageArg();
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
            if ($spByMembers == null || !is_array($spByMembers)) {
                return $engine->addError('Требуется количество SP по участникам');
            }
            if (count($spByMembers) != count($memberIds)) {
                return $engine->addError('Количество SP по участникам не соответствует количеству участников');
            }

            // Записываем информацию об участниках
            $sql = "REPLACE INTO `%s` (`userId`, `instanceId`, `sp`) VALUES (?, '" . $issueId . "', ?)";
            if (!$prepare = $db->preparet($sql, LPMTables::ISSUE_MEMBER_INFO)) {
                return $engine->addError('Ошибка при сохранении информации об участниках');
            }

            $spTotal = 0;
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

    private function notifyAboutIssueChange(Issue $issue, $issueURL, $editMode)
    {
        $engine = $this->_engine;
        $members = $issue->getMemberIds();
        if ($editMode) {
            $members[] = $issue->authorId; // TODO фильтр, чтобы не добавлялся дважды
            EmailNotifier::getInstance()->sendMail2Allowed(
                'Изменена задача "' . $issue->name . '"',
                $engine->getUser()->getName() . ' изменил задачу "' .
                $issue->name .  '", в которой Вы принимаете участие' . "\n" .
                'Просмотреть задачу можно по ссылке ' .	$issueURL,
                $members,
                EmailNotifier::PREF_EDIT_ISSUE
            );
        } else {
            EmailNotifier::getInstance()->sendMail2Allowed(
                'Добавлена задача "' . $issue->name . '"',
                $engine->getUser()->getName() . ' добавил задачу "' .
                $issue->name .  '", в которой Вы назначены исполнителем' . "\n" .
                'Просмотреть задачу можно по ссылке ' .	$issueURL,
                $members,
                EmailNotifier::PREF_ADD_ISSUE
            );
        }
    }

    private function getTitleByIssue(Issue $issue)
    {
        return $issue->name . ' - ' . $this->_project->name;
    }

    private function parseSP($value, $allowFloat = false)
    {
        // из дробных разрешаем только 1/2
        return ($value == "0.5" || $value == "0,5" || $value == "1/2") ? 0.5 :
            ($allowFloat ? floatval(str_replace(',', '.', (string)$value)) : (int)$value);
    }
}
