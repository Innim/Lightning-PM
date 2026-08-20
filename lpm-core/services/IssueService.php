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
        // восстанавливать задачу может создатель задачи,
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
        
        if (!$issue->checkEditPermit($this->getUserId())) {
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
     * @param bool $loadLinked Определяет, нужно ли загружать связанные задачи.
     */
    public function load($issueId, $loadLinked = false)
    {
        $loadLinked = (bool)$loadLinked;
        
        if (!$issue = Issue::load((float)$issueId)) {
            return $this->error('Нет такой задачи');
        }
        
        if (!$issue->checkViewPermit($this->getUserId())) {
            return $this->error('У Вас нет прав на просмотр этой задачи');
        }
        
        $this->add2Answer('issue', $this->getIssue4Client($issue, true, $loadLinked));
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
        
        if (!$issue->checkViewPermit($this->getUserId())) {
            return $this->error('У Вас нет прав на просмотр этой задачи');
        }

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
        
        if (!$issue->checkEditPermit($this->getUserId())) {
            return $this->error('У Вас нет прав на редактирование этой задачи');
        }
        
        try {
            Issue::remove($this->getUser(), $issue);
        } catch (Exception $e) {
            return $this->exception($e);
        }
    
        
        return $this->answer();
    }
    
    public function comment($issueId, $text, $requestChanges = false)
    {
        $issueId = (int)$issueId;
        $requestChanges = (bool)$requestChanges;

        try {
            $issue = Issue::load($issueId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            $comment = $this->postComment(
                $issue,
                $text,
                false,
                $requestChanges ? IssueCommentType::REQUEST_CHANGES : null
            );

            $addedLinks = IssueLinked::syncFromText($issue, $text, $this->getUserId());

            $this->setupCommentAnswer($comment);

            // если из комментария добавились связи — вернём обновлённый блок связанных задач
            if ($addedLinks > 0) {
                $linkedHtml = $this->getHtml(function () use ($issue) {
                    PagePrinter::issueLinked(Issue::load($issue->getID()));
                });
                $this->add2Answer('linkedHtml', $linkedHtml);
            }
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Связывает текущую задачу с другой задачей, выбранной по идентификатору.
     *
     * @param int $issueId       Идентификатор текущей задачи.
     * @param int $linkedIssueId Идентификатор связываемой задачи.
     * @return {
     *     string html HTML блока связанных задач.
     * }
     */
    public function addLink($issueId, $linkedIssueId)
    {
        try {
            $issue = $this->getIssueForEdit((int)$issueId);
            return $this->addIssueLink($issue, Issue::load((int)$linkedIssueId));
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    /**
     * Связывает текущую задачу с задачей, заданной ссылкой на неё.
     *
     * Позволяет связывать задачи из других проектов, доступных пользователю.
     *
     * @param int    $issueId Идентификатор текущей задачи.
     * @param string $url     Ссылка на связываемую задачу.
     * @return {
     *     string html HTML блока связанных задач.
     * }
     */
    public function addLinkByUrl($issueId, $url)
    {
        try {
            $issue = $this->getIssueForEdit((int)$issueId);
            $target = OwnUrlHelper::loadIssueByUrl($url);
            if ($target === null) {
                return $this->error('Не удалось распознать ссылку на задачу');
            }
            return $this->addIssueLink($issue, $target);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    /**
     * Удаляет связь текущей задачи с другой задачей.
     *
     * @param int $issueId       Идентификатор текущей задачи.
     * @param int $linkedIssueId Идентификатор связанной задачи.
     * @return {
     *     string html HTML блока связанных задач.
     * }
     */
    public function removeLink($issueId, $linkedIssueId)
    {
        try {
            $issue = $this->getIssueForEdit((int)$issueId);
            IssueLinked::remove($issue->getID(), (int)$linkedIssueId);
            return $this->answerLinkedIssues($issue->getID());
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    /**
     * Помечает отмеченный в комментарии баг как решённый без внесения правок.
     *
     * Снимает с задачи статус наличия бага, сохраняя текст комментария.
     * @param int $commentId Идентификатор комментария с отметкой о баге.
     * @return {
     *     object comment Обновлённый комментарий.
     *     string html HTML обновлённого комментария.
     * }
     */
    public function resolveComment($commentId)
    {
        $commentId = (int)$commentId;

        try {
            $comment = Comment::load($commentId);
            if (!$comment || $comment->instanceType != LPMInstanceTypes::ISSUE) {
                return $this->error('Комментария не существует');
            }

            if (empty($comment->issueComment) || !$comment->issueComment->isRequestChanges()) {
                return $this->error('Этот комментарий нельзя отметить решённым');
            }

            $issue = Issue::load($comment->instanceId);
            if (!$issue) {
                return $this->error('Нет такой задачи');
            }

            if (!$issue->checkEditPermit($this->getUserId())) {
                return $this->error('У Вас нет прав на редактирование этой задачи');
            }

            $comment->issue = $issue;
            $comment->issueComment = IssueComment::create(
                $comment->id,
                IssueCommentType::REQUEST_CHANGES_RESOLVED,
                $comment->issueComment->data
            );

            UserLogEntry::create(
                $this->getUserId(),
                DateTimeUtils::$currentDate,
                UserLogEntryType::RESOLVE_BUG_COMMENT,
                $comment->id
            );

            $this->setupCommentAnswer($comment);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Возвращает текст комментария для предпросмотра.
     *
     * Комментарий не сохраняется в БД.
     */
    public function previewComment($text)
    {
        try {
            $html = $this->getHtml(function () use ($text) {
                PagePrinter::commentText(HTMLHelper::htmlTextForComment($text));
            });
            
            $this->add2Answer('html', $html);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Возвращает текст описания задачи для предпросмотра.
     *
     * Описание не сохраняется в БД.
     */
    public function previewIssueDesc($text)
    {
        try {
            $html = $this->getHtml(function () use ($text) {
                echo HTMLHelper::htmlTextForIssue($text);
            });

            $this->add2Answer('html', $html);
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

            $comment = $this->postComment($issue, '`-> develop`', true, 
                IssueCommentType::BRANCH_MERGED);

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
                $text = '**Прошла тестирование**';
            }

            $comment = $this->postComment($issue, $text, true, IssueCommentType::PASS_TEST);

            $issue->autoSetMasters();

            // Отправляем оповещение в slack
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

            $gitlabProject = $client->getProject($gitlabProjectId);
            if (!$gitlabProject) {
                return $this->error('Не удалось получить данные проекта с GitLab');
            }

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

            $commentText = '*' . $gitlabProject->name . '*: `' . $commentText . '`';

            $comment = $this->postComment($issue, $commentText, true, 
                IssueCommentType::CREATE_BRANCH, 
                IssueCommentCreateBranchData::serialize($gitlabProjectId, $finalBranchName));

            $user = $this->getUser();
            $userId = $user->userId;

            // Записываем данные о том, что ветка привязана к задаче
            IssueBranch::create($issue->id, $gitlabProjectId, $finalBranchName, $userId, $branch->commit->id);

            if ($issue->status == Issue::STATUS_IN_WORK) {
                // Если пользователя нет в исполнителях - добавим его автоматически
                if (!$issue->isMember($userId)) {
                    if (!Member::saveIssueMembers($issue->id, [$userId])) {
                        return $this->errorDBSave();
                    }
                    
                    $member = Member::loadByIssue($issue->id, $userId);
                    $issue->addMember($member);

                    // Записываем лог
                    UserLogEntry::issueEdit($userId, $issue->id, 'Add member by create branch');

                    // Добавляем в ответ
                    $this->add2Answer('issue', $this->getIssue4Client($issue));
                }

                // Если это стикер на доске и он еще не в работе - перевешиваем в работу
                $sticker = ScrumSticker::load($issue->id);
                if (!empty($sticker) && $sticker->state == ScrumStickerState::TODO) {
                    if (!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::IN_PROGRESS)) {
                        return $this->errorDBSave();
                    }
                }
            }

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
                throw new Exception('Неизвестное состояние');
            }

            $sticker = ScrumSticker::load($issueId);
            if ($sticker === null) {
                throw new Exception('Нет стикера для этой задачи');
            }

            $issue = $sticker->getIssue();

            // Если проект требует теги - задачу без них нельзя взять
            // из бэклога на спринт
            if ($sticker->state == ScrumStickerState::BACKLOG
                    && ScrumStickerState::isActiveState($state)
                    && $issue->getProject()->requireLabels
                    && !Issue::hasLabels($issue->getName())) {
                throw new Exception(
                    'Нельзя добавить на спринт задачу без тегов - ' .
                    'у задачи должен быть указан хотя бы один тег'
                );
            }

            // Менять состояние стикера может любой пользователь
            if (!ScrumSticker::updateStickerState($issueId, $state)) {
                return $this->errorDBSave();
            }

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

            // Задача в работе попадает сразу на спринт, а туда без тегов нельзя,
            // если проект их требует
            if (ScrumStickerState::isActiveState(ScrumSticker::getStateForIssue($issue))
                    && $issue->getProject()->requireLabels
                    && !Issue::hasLabels($issue->getName())) {
                return $this->error(
                    'Нельзя добавить на спринт задачу без тегов - ' .
                    'у задачи должен быть указан хотя бы один тег'
                );
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
     * @param bool $transferOpened Определяет, будут ли перенесены но новый спринт
     *                             открытые задачи. Открытыми считаются задачи в TODO и работе.
     * @return
     */
    public function removeStickersFromBoard($projectId, $transferOpened = false)
    {
        $projectId = (int)$projectId;
        $transferOpened = (bool)$transferOpened;

        try {
            // проверим, что существует такой проект
            if (!Project::loadById($projectId)) {
                return $this->error('Нет такого проекта');
            }
            
            // прежде чем отправлять все задачи в архив, делаем snapshot доски
            ScrumStickerSnapshot::createSnapshot($projectId, $this->getUser()->userId);

            $notRemoveStates = $transferOpened
                ? [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS]
                : null;
            if (!ScrumSticker::removeStickersForProject($projectId, $notRemoveStates)) {
                return $this->errorDBSave();
            }

            if (!empty($notRemoveStates)) {
                // Если какие-то стикеры остались на доске - надо им обновить время добавления
                ScrumSticker::updateStickerAdded($projectId);
            }
            
            $currentNumSprint = ScrumStickerSnapshot::getLastSnapshotId($projectId) + 1;
        } catch (\Exception $e) {
            return $this->exception($e);
        }
        
        $this->add2Answer('numSprint', $currentNumSprint);
        return $this->answer();
    }

    /**
     * Взять задачу.
     *
     * @param  int $issueId
     * @param bool $replace Если true, то удаляет других исполнителей,
     * оставляя только текущего. Иначе - добавляет исполнителя.
     */
    public function takeIssue($issueId, $replace = true)
    {
        $issueId = (int)$issueId;
        $replace = (bool)$replace;

        try {
            $issue = Issue::load($issueId);
            if ($issue === null) {
                return $this->error('Нет такой задачи');
            }

            if ($replace && !Member::deleteIssueMembers($issueId)) {
                return $this->errorDBSave();
            }

            $user = $this->getUser();
            $userId = $user->userId;
            if (!Member::saveIssueMembers($issueId, [$userId])) {
                return $this->errorDBSave();
            }

            // Записываем лог
            UserLogEntry::issueEdit($userId, $issue->id, 'Take issue');

            $html = $this->getHtml(function () use ($user) {
                PagePrinter::tableScrumBoardIssueMember($user);
            });

            $this->add2Answer('memberName', $user->getShortName());
            $this->add2Answer('memberHtml', $html);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    /**
     * Добавляет текущего пользователя к участникам задачи в указанной роли.
     *
     * Уже назначенные участники сохраняются.
     *
     * @param int $issueId Идентификатор задачи.
     * @param string $role Роль: `member` - исполнитель, `tester` - тестировщик,
     * `master` - мастер.
     */
    public function addMeToIssue($issueId, $role)
    {
        $issueId = (int)$issueId;

        try {
            $issue = $this->getIssueForEdit($issueId);

            $user = $this->getUser();
            $userId = $user->userId;

            switch ($role) {
                case 'member':
                    if ($issue->isMember($userId)) {
                        return $this->error('Вы уже являетесь исполнителем этой задачи');
                    }
                    $saved = Member::saveIssueMembers($issueId, [$userId]);
                    break;
                case 'tester':
                    if ($issue->isTester($userId)) {
                        return $this->error('Вы уже являетесь тестировщиком этой задачи');
                    }
                    $saved = Member::saveIssueTesters($issueId, [$userId]);
                    break;
                case 'master':
                    if ($issue->isMaster($userId)) {
                        return $this->error('Вы уже являетесь мастером этой задачи');
                    }
                    $saved = Member::saveIssueMasters($issueId, [$userId]);
                    break;
                default:
                    return $this->error('Неизвестная роль');
            }

            if (!$saved) {
                return $this->errorDBSave();
            }

            // Записываем лог
            UserLogEntry::issueEdit($userId, $issue->id, 'Add self as ' . $role);

            // Отдаём только ссылку на пользователя и аватар: как их показать,
            // решает клиент — вид страницы задачи выбирается настройкой
            $this->add2Answer('userId', $userId);
            $this->add2Answer('memberHtml', $user->getLinkedName());
            $this->add2Answer('avatarUrl', $user->getAvatarUrl());
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Блокирует задачу на момент редактирования.
     * @param int $issueId Идентификатор задачи.
     * @param String $revision Ревизия задачи, которая будет заблокирована.
     * @param bool $forced Если true, то блокировка будет принудительной, даже
     * @return
     */
    public function lockIssue($issueId, $revision, $forced = false) 
    {
        $issueId = (int)$issueId;
        $forced = (bool)$forced;

         try {
            $issue = $this->getIssueForEdit($issueId);

            if ($issue->revision != $revision) {
                return $this->error('Задача была изменена. Пожалуйста, обновите страницу.');
            }

            $userId = $this->getUserId();

            if (!$forced) {
                $lock = UserLock::getIssueLock($issueId);

                if (!empty($lock)) {
                    $this->add2ErrorData('lock', $lock);
                    if ($userId == $lock->userId) {
                        return $this->error('Задача уже заблокирована вами.', 201);
                    } else {
                        $lockOwner = User::load($lock->userId);
                        $html = $this->getHtml(function () use ($lockOwner, $lock) {
                            PagePrinter::dialogContentIssueBlocked($lockOwner, $lock);
                        });

                        $this->add2ErrorData('dialogHtml', $html);
                        return $this->error('Задача уже заблокирована другим пользователем', 202);
                    }
                }
            }

            UserLock::removeIssueLocks($issueId);
            UserLock::createIssueLock($userId, $issueId);

            $this->add2Answer('revision', $issue->revision);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }

    // TODO: update lock expired

    /**
     * Удаляет блокировку задачи.
     * @param int $issueId Идентификатор задачи.
     * @param String $revision Ревизия задачи, которая была заблокирована.
     * @return
     */
    public function unlockIssue($issueId, $revision) 
    {
        $issueId = (int)$issueId;

        try {
            $issue = $this->getIssueForEdit($issueId);

            $userId = $this->getUserId();

            $lock = UserLock::getIssueLock($issueId);

            if (empty($lock)) {
                return $this->error('Задача не заблокирована');
            }

            if ($userId != $lock->userId) {
                return $this->error('Задача заблокирована другим пользователем', 101);
            }

            if ($issue->revision != $revision) {
                return $this->error('Задача была изменена', 102);
            }

            UserLock::removeIssueLocks($issueId);
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
        // Id проектных меток, использования которых нужно перенести на целевую (общую) метку.
        $mergeFromIds = [];
        if (!empty($labels)) {
            $count = count($labels);
            while ($count-- > 0) {
                $labelData = $labels[$count];
                if ($projectId == 0) {
                    if ($labelData['projectId'] != 0 && $labelData['deleted'] == LabelState::ACTIVE) {
                        $uses += $labelData['countUses'];
                        $mergeFromIds[] = $labelData['id'];
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

        // Была ли общая метка переиспользована (существовала ранее, но была отключена).
        $reuseId = (int) $id;
        $id = Issue::saveLabel($label, $projectId, $id, $uses, LabelState::ACTIVE);
        if ($id == null) {
            return $this->error($db->error);
        } else {
            // Переносим накопленную статистику проектных меток только при создании НОВОЙ
            // общей метки — чтобы её ранжирование по частоте в проектах не начиналось с нуля.
            // Если общая метка переиспользуется, её строки использований уже поддерживаются
            // актуальными в Issue::addLabelsUsing() (счётчик обновляется и для отключённых
            // меток), поэтому повторный перенос привёл бы к двойному учёту.
            if ($reuseId == 0) {
                foreach ($mergeFromIds as $fromId) {
                    Issue::mergeLabelUses($fromId, (int) $id);
                }
            }
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
            $exporter = new IssuesExporterToExcel($list, $filename, $project->scrum);
            $fileUrl = $exporter->export();

            $this->add2Answer('fileUrl', $fileUrl);
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    
        return $this->answer();
    }
    
    protected function getIssue4Client(Issue $issue, $loadMembers = true, $loadLinked = false)
    {
        $obj = $issue->getClientObject();

        if ($loadMembers) {
            $members = $issue->getMembers();
            $testers = $issue->getTesters();
            $masters = $issue->getMasters();
            $obj->members = [];
            $obj->testers = [];
            $obj->masters = [];

            foreach ($members as $member) {
                $obj->members[] = $member->getClientObject();
            }

            foreach ($testers as $tester) {
                $obj->testers[] = $tester->getClientObject();
            }

            foreach ($masters as $master) {
                $obj->masters[] = $master->getClientObject();
            }
        }

        $images = $issue->getImages();
        $obj->images = [];

        foreach ($images as $image) {
            array_push($obj->images, array( 'imgId' => $image->imgId,
                'source' => $image->getSource(),
                'preview' => $image->getPreview()));
        }

        $files = $issue->getFiles();
        $obj->files = [];

        foreach ($files as $file) {
            $obj->files[] = $file->getClientObject();
        }

        $obj->isOnBoard = $issue->isOnBoard();

        if ($loadLinked) {
            $linked = $issue->getLinkedIssues();
            $obj->linked = [];
            foreach ($linked as $issue) {
                $obj->linked[] = $issue->getClientObject();
            }
        }

        return $obj;
    }

    public function deleteComment($id, $deleteBranch = false)
    {
        $id = (int)$id;
        $deleteBranch = (bool)$deleteBranch;

        $comment = Comment::load($id);
        if (!$comment) {
            return $this->error('Комментария не существует');
        }

        $user = $this->getUser();

        if (!$this->checkRole(User::ROLE_MODERATOR)) {
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
            LPMFile::delete(
                LPMInstanceTypes::COMMENT,
                $comment->id,
                array_map(function (LPMFile $file) {
                    return $file->fileId;
                }, $comment->getFiles())
            );

            if ($comment->instanceType == LPMInstanceTypes::ISSUE) {
                // обновляем счетчик комментариев для задачи
                Issue::updateCommentsCounter($comment->instanceId);

                // Если это коммент о создании ветки — удаляем связь и опционально саму ветку
                if (!empty($comment->issueComment) && $comment->issueComment->isCreateBranch()) {
                    $data = $comment->issueComment->getCreateBranchData();
                    if ($data) {
                        // Удаляем связь ветки с задачей
                        IssueBranch::remove($comment->instanceId, $data->repositoryId, $data->branchName);

                        // По доп. подтверждению — удаляем ветку на GitLab
                        if ($deleteBranch) {
                            $issue = Issue::load($comment->instanceId);
                            if ($issue) {
                                $project = $issue->getProject();
                                $client = $this->requireGitlabIntegration($project);
                                $client->deleteBranch($data->repositoryId, $data->branchName);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * @return Comment
     */
    private function postComment(
        Issue $issue,
        $text,
        $ignoreSlackNotification = false,
        string $type = null,
        string $data = null
    ) {
        return $this->_engine->comments()->postComment(
            $this->getUser(),
            $issue,
            $text,
            $ignoreSlackNotification,
            false,
            $type,
            $data,
            isset($_FILES['commentFiles']) && is_array($_FILES['commentFiles'])
                ? $_FILES['commentFiles']
                : null
        );
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

    /**
     * Создаёт связь задачи с целевой задачей после проверки прав и дубликатов.
     *
     * @param Issue      $issue  Текущая задача.
     * @param Issue|null $target Связываемая задача.
     */
    private function addIssueLink(Issue $issue, $target)
    {
        if (empty($target)) {
            return $this->error('Нет такой задачи');
        }
        if ($target->getID() == $issue->getID()) {
            return $this->error('Нельзя связать задачу с самой собой');
        }
        if (!$target->checkViewPermit($this->getUserId())) {
            return $this->error('У Вас нет прав на просмотр связываемой задачи');
        }
        foreach ($issue->getLinkedIssues() as $linked) {
            if ($linked->getID() == $target->getID()) {
                return $this->error('Задачи уже связаны');
            }
        }

        IssueLinked::create($issue->getID(), $target->getID(), DateTimeUtils::$currentDate);

        return $this->answerLinkedIssues($issue->getID());
    }

    /**
     * Формирует ответ с обновлённым HTML блока связанных задач.
     *
     * @param int $issueId Идентификатор задачи.
     */
    private function answerLinkedIssues($issueId)
    {
        $issue = Issue::load((int)$issueId);

        $html = $this->getHtml(function () use ($issue) {
            PagePrinter::issueLinked($issue);
        });

        $this->add2Answer('html', $html);

        return $this->answer();
    }

    private function getIssueForEdit($issueId)
    {
        $issue = Issue::load($issueId);
        if (!$issue) {
            throw new Exception('Нет такой задачи');
        }

        if (!$issue->checkEditPermit($this->getUserId())) {
            throw new Exception('У Вас нет прав на редактирование этой задачи');
        }

        return $issue;
    }
}
