<?php
require_once(dirname(__FILE__) . '/../init.inc.php');
use \GMFramework\DateTimeUtils as DTU;

class IssueService extends LPMBaseService
{

    /**
     * Завершаем задачу
     * @param  int $issueId
     */
    public function complete($issueId)
    {
        // завершать задачу может создатель задачи,
        // исполнитель задачи или модератор
        $issue = Issue::load((float)$issueId);
        if (!$issue) {
            return $this->error('Нет такой задачи');
        }

        try {
            $this->completeIssue($issue);
        } catch (Exception $e) {
            return $this->exception($e);
        }
        
        return $this->answer();
    }
    
    /**
     * Восстанавливаем задачу
     * @param float $issueId
     */
    public function restore($issueId)
    {
        // востанавливать задачу может создатель задачи,
        // исполнитель задачи или модератор
        $issue = Issue::load((float)$issueId);
        if (!$issue) {
            return $this->error('Нет такой задачи');
        }
        
        if (!$issue->checkEditPermit($this->_auth->getUserId())) {
            return $this->error('У Вас нет прав на редактирование этой задачи');
        }

        try {
            Issue::setStatus($issue, Issue::STATUS_IN_WORK, $this->getUser());
        } catch (Exception $e) {
            return $this->exception($e);
        }

        $this->add2Answer('issue', $this->getIssue4Client($issue));
    
        return $this->answer();
    }

    /**
     * Ставим задачу на проверку
     * @param float $issueId
     */
    public function verify($issueId)
    {
        // ставить задачу на проверку может исполнитель задачи
        $issue = Issue::load((float)$issueId);
        if (!$issue) {
            return $this->error('Нет такой задачи');
        }
        
        if (!$issue->checkEditPermit($this->_auth->getUserId())) {
            return $this->error('У Вас нет прав на редактирование этой задачи');
        }

        try {
            Issue::setStatus($issue, Issue::STATUS_WAIT, $this->getUser());
        } catch (Exception $e) {
            return $this->exception($e);
        }

        $this->add2Answer('issue', $this->getIssue4Client($issue));
    
        return $this->answer();
    }
    
    /**
     * Загружает информацию о задаче
     * @param float $issueId
     */
    public function load($issueId)
    {
        if (!$issue = Issue::load((float)$issueId)) {
            return $this->error('Нет такой задачи');
        }
        
        // TODO проверка на возможность просмотра
        
        /*$obj = $issue->getClientObject();
        $members = $issue->getMembers();
        $obj['members'] = array();

        foreach ($members as $member) {
            array_push( $obj['members'], $member->getClientObject() );
        }*/
        
        $this->add2Answer('issue', $this->getIssue4Client($issue));
        return $this->answer();
    }

    /**
     * Загружает информацию о задаче
     * @param float $idInProject
     * @param int $projectId
     * @return array
     */
    public function loadByIdInProject($idInProject, $projectId)
    {
        $projectId = (int) $projectId;

        if (!$issue = Issue::loadByIdInProject($projectId, (float) $idInProject)) {
            return $this->error('Нет такой задачи');
        }

        // TODO проверка на возможность просмотра

        $this->add2Answer('issue', $this->getIssue4Client($issue));
        return $this->answer();
    }
    
    /**
     * Удаляет задачу
     * @param float $issueId
     */
    public function remove($issueId)
    {
        $issueId = (float)$issueId;
        // удалять задачу может создатель задачи или модератор
        if (!$issue = Issue::load((float)$issueId)) {
            return $this->error('Нет такой задачи');
        }
        
        // TODO проверка прав
        //if (!$issue->checkEditPermit( $this->_auth->getUserId() ))
        //return $this->error( 'У Вас нет прав на редактирование этой задачи' );
        
        try {
            Issue::remove($this->getUser(), $issue);
        } catch (Exception $e) {
            return $this->exception($e);
        }
    
        
        return $this->answer();
    }
    
    public function comment($issueId, $text)
    {
        $issueId = (int)$issueId;

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            $comment = $this->postComment($issue, $text);

            $this->setupCommentAnswer($comment);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Отмечает что задача влита в develop.
     * @param  int $issueId Идентификатор задачи.
     * @param  bool $complete true если надо также завершить задачу.
     * @return {
     *     string comment Добавленный комментарий.
     * }
     */
    public function merged($issueId, $complete = false)
    {
        $issueId = (int)$issueId;
        $complete = (bool)$complete;

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            $comment = $this->postComment($issue, '`-> develop`', true);

            if ($complete) {
                try {
                    $this->completeIssue($issue);
                } catch (Exception $e) {
                    return $this->exception($e);
                }
            }

            $this->setupCommentAnswer($comment);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Отмечает что задача прошла тестирование.
     * @param   int     $issueId Идентификатор задачи
     * @param   String  $text Текст комментария
     * @return {
     *     string comment Добавленный комментарий.
     * }
     */
    public function passTest($issueId, $text)
    {
        $issueId = (int)$issueId;

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            if (empty($text)) {
                $text = 'Прошла тестирование';
            }

            $comment = $this->postComment($issue, $text, true);

            // Отправляем оповещенив в slack
            $slack = SlackIntegration::getInstance();
            $slack->notifyIssuePassTest($issue);

            $this->setupCommentAnswer($comment);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Создает ветку задачи на репозитории и добавляет комментарий с именем ветки.
     *
     * @param  int $issueId Идентификатор задачи.
     * @param  string $branchName Имя ветки.
     * @param  int $gitlabProjectId Идентификатор проекта на GitLab.
     * @param  string $parentBranch Имя родительской ветки.
     * @return {
     *     Comment comment Добавленный комментарий.
     *     String  html    HTML код комментария.
     * }
     */
    public function createBranch($issueId, $branchName, $gitlabProjectId, $parentBranch)
    {
        $issueId = (int)$issueId;
        $gitlabProjectId = (int)$gitlabProjectId;
        if (!$this->validateBranchName($branchName)) {
            return $this->errorValidation('branchName');
        }
        if (!$this->validateBranchName($parentBranch)) {
            return $this->errorValidation('parentBranch');
        }

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            $project = $issue->getProject();
            $client = $this->requireGitlabIntegration($project);

            $finalBranchName = 'feature/' . $branchName;

            // Создаем ветку на репозитории
            $branch = $client->createBranch($gitlabProjectId, $parentBranch, $finalBranchName);
            if (!$branch) {
                return $this->error('Не удалось создать ветку ' . $finalBranchName);
            }

            // Добавляем коммент
            $commentText = $branch->name;
            if ($parentBranch != 'develop') {
                $commentText = $parentBranch . ' -> ' . $commentText;
            }
            $commentText = '`' . $commentText . '`';

            $comment = $this->postComment($issue, $commentText, true);

            // Записываем данные о том, что ветка привязана к задаче
            IssueBranch::create($issue->id, $gitlabProjectId, $finalBranchName);

            $this->setupCommentAnswer($comment);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Меняет приоритет задачи.
     * @param  int $issueId Идентификатор задачи
     * @param  int $delta Изменение приоритета.
     * @return {
     *     int priority Новое значение приоритета.
     * }
     */
    public function changePriority($issueId, $delta)
    {
        $issueId = (int)$issueId;
        $delta   = (int)$delta;

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }
            Issue::changePriority($this->getUser(), $issue, $delta);

            $this->add2Answer('priority', $issue->priority);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    /**
     * Изменяет состояние стикера
     * @param  int $issueId Идентификатор задачи
     * @param  int $state   Новое состояние стикера
     * @return
     */
    public function changeScrumState($issueId, $state)
    {
        $issueId = (int)$issueId;
        $state   = (int)$state;

        try {
            // Проверяем состояние
            if (!ScrumStickerState::validateValue($state)) {
                throw new Exception('Неизвестный стейт');
            }

            $sticker = ScrumSticker::load($issueId);
            if ($sticker === null) {
                throw new Exception('Нет стикера для этой задачи');
            }

            // Менять состояние стикера может любой пользователь
            if (!ScrumSticker::updateStickerState($issueId, $state)) {
                return $this->errorDBSave();
            }

            $issue = $sticker->getIssue();
            $newState = null;
            if ($state === ScrumStickerState::TESTING) {
                // Если состояние "Тестируется" - ставим задачу на проверку
                $newState = Issue::STATUS_WAIT;
            } elseif ($state === ScrumStickerState::DONE) {
                // Если "Готово" - закрываем задачу
                $newState = Issue::STATUS_COMPLETED;
            } elseif ($issue->status == Issue::STATUS_WAIT &&
                    ($state === ScrumStickerState::TODO || $state === ScrumStickerState::IN_PROGRESS)) {
                // Если она в режиме ожидания - переоткрываем задачу
                $newState = Issue::STATUS_IN_WORK;
            }
            
            if ($newState !== null) {
                Issue::setStatus($issue, $newState, $this->getUser(), true, false);
            }
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Проверяем есть ли тестер у задачи, если нет - добавляем тестера из проекта
     */
    public static function checkTester(Issue $issue)
    {
        $testers = $issue->getTesters();
        $issueId = $issue->getID();
        $type = LPMInstanceTypes::ISSUE_FOR_TEST;

        if (empty($testers)) {
            $projectId = $issue->getProject()->getID();
            $projectTesters = Member::loadTesterForProject($projectId);
            if (empty($projectTesters)) {
                return null;
            }

            $testerId = (int) $projectTesters[0]->getID();
            Member::saveMembers($type, $issueId, [$testerId]);
        }
    }

    /**
     * Помещает стикер задачи на скрам доску
     * @param  int $issueId Идентификатор задачи
     * @return
     */
    public function putStickerOnBoard($issueId)
    {
        $issueId = (int)$issueId;

        try {
            $issue = Issue::load($issueId);
            if ($issue === null) {
                return $this->error('Нет такой задачи');
            }

            if (!ScrumSticker::putStickerOnBoard($issue)) {
                return $this->errorDBSave();
            }
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    /**
     * Убирает в архив стикеры с доски
     * @param int $projectId Идентификатор проекта
     * @return
     */
    public function removeStickersFromBoard($projectId)
    {
        $projectId = (int)$projectId;

        try {
            // прежде чем отправлять все задачи в архив, делаем snapshot доски
            ScrumStickerSnapshot::createSnapshot($projectId, $this->getUser()->userId);

            if (!ScrumSticker::removeStickersForProject($projectId)) {
                return $this->errorDBSave();
            }
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    /**
     * Забрать задачу себе. Удаляет других исполнителей,
     * оставляя только текущего
     * @param  int $issueId
     */
    public function takeIssue($issueId)
    {
        $issueId = (int)$issueId;

        try {
            $issue = Issue::load($issueId);
            if ($issue === null) {
                return $this->error('Нет такой задачи');
            }

            if (!Member::deleteIssueMembers($issueId)) {
                return $this->errorDBSave();
            }

            $user = $this->getUser();
            $userId = $user->userId;
            if (!Member::saveIssueMembers($issueId, [$userId])) {
                return $this->errorDBSave();
            }

            // Записываем лог
            UserLogEntry::issueEdit($userId, $issue->id, 'Take issue');

            $this->add2Answer('memberName', $user->getShortName());
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    /**
     * Добавляет новую метку.
     * @param $label Текст метки.
     * @param $isForAllProjects Для всех ли проектов.
     * @param $projectId Идентификатор проекта (используется в случае, если не для всех проектов).
     * @return mixed
     */
    public function addLabel($label, $isForAllProjects, $projectId)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $projectId = $isForAllProjects ? 0 : $projectId;

        $labels = Issue::getLabelsByLabelText($label);
        $uses = 0;
        $id = 0;
        if (!empty($labels)) {
            $count = count($labels);
            while ($count-- > 0) {
                $labelData = $labels[$count];
                if ($projectId == 0) {
                    if ($labelData['projectId'] != 0 && $labelData['deleted'] == LabelState::ACTIVE) {
                        $uses += $labelData['countUses'];
                        Issue::changeLabelDeleted($labelData['id'], LabelState::DISABLED);
                    } elseif ($labelData['projectId'] == 0) {
                        if ($labelData['deleted'] == LabelState::ACTIVE) {
                            return $this->error("Метка уже существует");
                        } else {
                            $uses += $labelData['countUses'];
                            $id = $labelData['id'];
                        }
                    }
                } elseif ($labelData['projectId'] == 0 && $labelData['deleted'] == LabelState::ACTIVE) {
                    return $this->error("Метка уже существует");
                } elseif ($labelData['projectId'] == $projectId) {
                    if ($labelData['deleted'] == LabelState::ACTIVE) {
                        return $this->error("Метка уже существует");
                    } else {
                        $id = $labelData['id'];
                    }
                }
            }
        }

        $id = Issue::saveLabel($label, $projectId, $id, $uses, LabelState::ACTIVE);
        if ($id == null) {
            return $this->error($db->error);
        } else {
            $this->add2Answer('id', $id);
            return $this->answer();
        }
    }

    /**
     * Удаляет метку.
     * @param $id
     * @param $projectId
     */
    public function removeLabel($id, $projectId)
    {
        $label = Issue::getLabel($id);
        $projectId = (int) $projectId;

        if ($label == null) {
            return $this->error("Метка не найдена.");
        }

        $state = ($label['projectId'] == 0) ? LabelState::DISABLED : LabelState::DELETED;
        if ($label['projectId'] == 0) {
            $labels = Issue::getLabelsByLabelText($label['label']);
            if (!empty($labels)) {
                $count = count($labels);
                while ($count-- > 0) {
                    $labelData = $labels[$count];
                    if ($labelData['projectId'] == 0 && $labelData['id'] != $label['id']) {
                        Issue::changeLabelDeleted($labelData['id'], LabelState::DISABLED);
                    } elseif ($labelData['projectId'] != 0 && $labelData['deleted'] == LabelState::DISABLED) {
                        if ($labelData['projectId'] != $projectId) {
                            Issue::changeLabelDeleted($labelData['id'], LabelState::ACTIVE);
                        } else {
                            Issue::changeLabelDeleted($labelData['id'], LabelState::DELETED);
                        }
                    }
                }
            }
        }

        if (Issue::changeLabelDeleted($label['id'], $state)) {
            return $this->answer();
        } else {
            $db = LPMGlobals::getInstance()->getDBConnect();
            return $this->error($db->error);
        }
    }

    /**
     * Экспорт завершенных задач в Excel.
     * @param  int $projectId Идентификатор проекта.
     * @param  string $fromDate Минимальная дата завершения задачи.
     * @param  string $toDate Максимальная дата завершения задачи.
     * @return {
     *    string fileUrl URL сформированного файла.
     * }
     */
    public function exportCompletedIssuesToExcel($projectId, $fromDate, $toDate)
    {
        $projectId = (int) $projectId;

        try {
            $user = $this->getUser();
            $project = Project::loadById($projectId);

            if ($project == null) {
                return $this->error("Не найден проект с идентификатором " . $projectId);
            }
            if (!$project->hasReadPermission($user)) {
                return $this->error("Нет прав на просмотр задач проекта");
            }

            $fromDateU = strtotime($fromDate);
            $toDateU = strtotime($toDate);

            if ($fromDateU > $toDateU) {
                $tmpDate = $fromDateU;
                $fromDateU = $toDateU;
                $toDateU = $tmpDate;
            }

            $fromCompletedDate = DTU::mysqlDate($fromDateU);
            $toCompletedDate = DTU::mysqlDate($toDateU);
            $list = Issue::loadListByProject(
                $projectId,
                array(Issue::STATUS_COMPLETED),
                $fromCompletedDate,
                $toCompletedDate
            );

            $filename = $project->uid . '_completed_issues_' .
                DTU::date('ymd', $fromDateU) . '-' . DTU::date('ymd', $toDateU) . '_' .
                DTU::date('YmdHis');
            $exporter = new IssuesExporterToExcel($list, $filename);
            $fileUrl = $exporter->export();

            $this->add2Answer('fileUrl', $fileUrl);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }
    
    protected function getIssue4Client(Issue $issue, $loadMembers = true)
    {
        $obj = $issue->getClientObject();
        $members = $issue->getMembers();
        $testers = $issue->getTesters();
        $images = $issue->getImages();
        $obj->members = array();
        $obj->testers = array();
        $obj->images = array();
        $obj->isOnBoard = $issue->isOnBoard();

        foreach ($members as $member) {
            array_push($obj->members, $member->getClientObject());
        }

        foreach ($testers as $tester) {
            array_push($obj->testers, $tester->getClientObject());
        }

        foreach ($images as $image) {
            array_push($obj->images, array( 'imgId' => $image->imgId,
                'source' => $image->getSource(),
                'preview' => $image->getPreview()));
        }

        return $obj;
    }

    public function deleteComment($id)
    {
        $id = (int)$id;

        $comment = Comment::load($id);
        if (!$comment) {
            return $this->error('Комментария не существует');
        }

        $user = $this->getUser();

        if (!$this->checkRole(User::ROLE_ADMIN)) {
            if (!Comment::checkDeleteCommentById($id)) {
                return $this->error('Время удаления истекло.');
            }
            
            $authorId = $comment->authorId;
            if ($authorId != $user->getID()) {
                return $this->error('Вы не можете удалять комментарий');
            }
        }

        try {
            Comment::remove($user, $comment);

            if ($comment->instanceType == LPMInstanceTypes::ISSUE) {
                // обновляем счетчик коментариев для задачи
                Issue::updateCommentsCounter($comment->instanceId);
            }
        } catch (Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * @return Comment
     */
    private function postComment(Issue $issue, $text, $ignoreSlackNotification = false)
    {
        return $this->_engine->comments()->postComment($this->getUser(), $issue, $text, $ignoreSlackNotification);
    }

    private function setupCommentAnswer(Comment $comment)
    {
        $html = $this->getHtml(function () use ($comment) {
            PagePrinter::comment($comment);
        });
        
        $this->add2Answer('comment', $comment->getClientObject());
        $this->add2Answer('html', $html);
    }

    private function completeIssue(Issue $issue)
    {
        if (!$issue->checkEditPermit($this->_auth->getUserId())) {
            throw new Exception('У Вас нет прав на редактирование этой задачи');
        }

        Issue::setStatus($issue, Issue::STATUS_COMPLETED, $this->getUser());
        
        $this->add2Answer('issue', $this->getIssue4Client($issue));
    }

    private function validateBranchName($value)
    {
        return \GMFramework\Validation::checkStr($value, 255, 1, false, false, true, '\/\._');
    }
}
