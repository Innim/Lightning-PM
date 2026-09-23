<?php

class ApiIssueController extends ApiControllerBase
{
    /**
     * Считает файлы, действительно переданные в поле запроса.
     *
     * Позиции поля без файла в счёт не идут: поле формы, где файл не выбран,
     * PHP всё равно описывает.
     * @param  array $filesData Данные поля запроса, {@see ApiRequest::getFiles()}.
     * @return int
     */
    private static function countUploads(array $filesData)
    {
        $count = 0;
        foreach ($filesData['tmp_name'] as $index => $tmpName) {
            $errorCode = isset($filesData['error'][$index]) ? $filesData['error'][$index] : UPLOAD_ERR_OK;
            if ($errorCode === UPLOAD_ERR_NO_FILE || (empty($tmpName) && $errorCode === UPLOAD_ERR_OK)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /** Роль участника задачи: исполнитель. */
    const ROLE_MEMBER = 'members';

    /** Роль участника задачи: тестировщик. */
    const ROLE_TESTER = 'testers';

    /** Роль участника задачи: мастер. */
    const ROLE_MASTER = 'masters';

    /**
     * Роли участников задачи: имя роли в адресе => тип участия в задаче.
     *
     * Имена ролей - единственные, которые понимает API.
     * @see LPMInstanceTypes
     */
    const MEMBER_ROLES = [
        self::ROLE_MEMBER => LPMInstanceTypes::ISSUE,
        self::ROLE_TESTER => LPMInstanceTypes::ISSUE_FOR_TEST,
        self::ROLE_MASTER => LPMInstanceTypes::ISSUE_FOR_MASTER,
    ];

    /** Имя поля multipart-запроса, в котором передаются файлы создаваемой задачи или комментария. */
    const FILES_FIELD = 'files';

    /**
     * Имя поля multipart-запроса, в котором передаются изображения
     * создаваемой задачи.
     *
     * Вложения задачи разделены так же, как в форме интерфейса: изображение
     * попадает в галерею задачи и показывается превью, файл - в список файлов
     * и отдаётся на скачивание оригиналом. Хранятся они в разных местах,
     * поэтому выбор поля определяет, чем вложение станет.
     */
    const IMAGES_FIELD = 'images';

    /**
     * Имя поля multipart-запроса, в котором передаются файлы, добавляемые
     * к существующей задаче.
     *
     * Названо иначе, чем поле при создании: в частичном изменении задачи
     * скалярное поле замещает прежнее значение, а файлы добавляются
     * к уже приложенным, и одно имя на два поведения путало бы.
     */
    const ADD_FILES_FIELD = 'addFiles';

    /**
     * Имя поля multipart-запроса, в котором передаются изображения,
     * добавляемые к существующей задаче.
     *
     * Отличается от поля при создании по той же причине, что и у файлов,
     * а от {@see ApiIssueController::ADD_FILES_FIELD} - тем, куда вложение
     * попадёт ({@see ApiIssueController::IMAGES_FIELD}).
     */
    const ADD_IMAGES_FIELD = 'addImages';

    public function dispatch(array $path)
    {
        $method = $this->request()->getMethod();

        if ($method === 'GET' && $path === ['resolve']) {
            $issue = $this->loadIssueByUrl($this->request()->getQuery('url'));
            if (!$issue) {
                return ApiResponse::error('Issue not found', 404);
            }

            return ApiResponse::success([
                'issue' => $this->serializer()->issue($issue),
            ]);
        }

        if ($method === 'POST' && count($path) === 0) {
            return $this->createIssue();
        }

        if (count($path) < 1) {
            return ApiResponse::error('Route not found', 404);
        }

        $issue = $this->loadIssueById($path[0]);
        if (!$issue) {
            return ApiResponse::error('Issue not found', 404);
        }

        if ($method === 'GET' && count($path) === 1) {
            return ApiResponse::success([
                'issue' => $this->serializer()->issue($issue),
            ]);
        }

        if ($method === 'POST' && count($path) === 1) {
            return $this->updateIssue($issue);
        }

        if ($method === 'POST' && count($path) === 2 && $path[1] === 'comments') {
            return $this->createComment($issue);
        }

        if ($method === 'POST' && count($path) === 2 && $path[1] === 'branches') {
            return $this->createBranch($issue);
        }

        if ($method === 'PUT' && count($path) === 2 && $path[1] === 'board') {
            return $this->putIssueOnBoard($issue);
        }

        if ($method === 'DELETE' && count($path) === 2 && $path[1] === 'board') {
            return $this->removeIssueFromBoard($issue);
        }

        if (count($path) >= 2 && isset(self::MEMBER_ROLES[$path[1]])) {
            if ($method === 'PUT' && count($path) === 2) {
                return $this->replaceIssueParticipants($issue, $path[1]);
            }

            if ($method === 'POST' && count($path) === 2) {
                return $this->addIssueParticipants($issue, $path[1]);
            }

            if ($method === 'DELETE' && count($path) === 3) {
                return $this->removeIssueParticipant($issue, $path[1], $path[2]);
            }
        }

        return ApiResponse::error('Route not found', 404);
    }

    /**
     * Ставит задачу на доску или переводит её в другую колонку.
     *
     * Колонка задаётся необязательным параметром `column`; без него она
     * выводится из статуса задачи, как по кнопке «На доску» в интерфейсе.
     * @return ApiResponse Обновлённая задача.
     */
    private function putIssueOnBoard(Issue $issue)
    {
        if (!$issue->getProject()->scrum) {
            return ApiResponse::error('Project has no scrum board', 400);
        }

        $column = $this->request()->getBody('column');
        $state = null;
        if ($column !== null && $column !== '') {
            $state = is_string($column) ? ApiPayloadSerializer::boardColumnState($column) : null;
            if ($state === null) {
                return ApiResponse::error(
                    'Unknown board column, expected one of: ' .
                    implode(', ', ApiPayloadSerializer::boardColumnKeys()),
                    400
                );
            }
        }

        try {
            if ($state === null) {
                ScrumBoardManager::putOnBoard($issue);
            } else {
                ScrumBoardManager::changeState($issue, $state, $this->user(), true);
            }
        } catch (ScrumBoardException $e) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode());
        }

        return $this->reloadedIssueResponse($issue);
    }

    /**
     * Снимает задачу с доски - она возвращается в бэклог.
     * @return ApiResponse Обновлённая задача.
     */
    private function removeIssueFromBoard(Issue $issue)
    {
        if (!$issue->getProject()->scrum) {
            return ApiResponse::error('Project has no scrum board', 400);
        }

        try {
            ScrumBoardManager::removeFromBoard($issue, $this->user());
        } catch (ScrumBoardException $e) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode());
        }

        return $this->reloadedIssueResponse($issue);
    }

    /**
     * Заменяет состав участников задачи в одной из ролей.
     *
     * Пустой список снимает с задачи всех участников этой роли.
     * @param  Issue  $issue Задача.
     * @param  string $role  Роль участников, см. {@see ApiIssueController::MEMBER_ROLES}.
     * @return ApiResponse Обновлённая задача или ошибка разбора списка.
     */
    private function replaceIssueParticipants(Issue $issue, $role)
    {
        $userIds = $this->parseParticipants($issue, true);
        if ($userIds instanceof ApiResponse) {
            return $userIds;
        }

        return $this->saveIssueParticipants($issue, $role, $userIds);
    }

    /**
     * Назначает участников задачи в одной из ролей, сохраняя назначенных ранее.
     * @param  Issue  $issue Задача.
     * @param  string $role  Роль участников, см. {@see ApiIssueController::MEMBER_ROLES}.
     * @return ApiResponse Обновлённая задача или ошибка разбора списка.
     */
    private function addIssueParticipants(Issue $issue, $role)
    {
        $userIds = $this->parseParticipants($issue, false);
        if ($userIds instanceof ApiResponse) {
            return $userIds;
        }

        return $this->saveIssueParticipants(
            $issue,
            $role,
            array_merge($this->participantIds($issue, $role), $userIds)
        );
    }

    /**
     * Снимает одного участника задачи с роли.
     *
     * Пользователь, в этой роли не назначенный, - не ошибка: состав участников
     * и так тот, которого добивался запрос. Условия, при которых участника
     * нельзя назначить (блокировка, потеря доступа к проекту), снятию
     * не мешают - иначе такого участника было бы не убрать.
     * @param  Issue  $issue  Задача.
     * @param  string $role   Роль участников, см. {@see ApiIssueController::MEMBER_ROLES}.
     * @param  string $userId Идентификатор снимаемого пользователя.
     * @return ApiResponse Обновлённая задача или ошибка разбора идентификатора.
     */
    private function removeIssueParticipant(Issue $issue, $role, $userId)
    {
        if (!ctype_digit((string)$userId) || (int)$userId <= 0) {
            return ApiResponse::error('Invalid user id, expected a positive integer', 400);
        }

        $userId = (int)$userId;
        if (!User::load($userId)) {
            return ApiResponse::error('User not found: ' . $userId, 404);
        }

        $userIds = array_diff($this->participantIds($issue, $role), [$userId]);

        return $this->saveIssueParticipants($issue, $role, $userIds);
    }

    /**
     * Разбирает список пользователей из параметра `users` тела запроса.
     *
     * Назначить участником можно только того, кому задача и так доступна:
     * то же правило действует в интерфейсе, где пользователь добавляет себя
     * в участники ({@see IssueService::addMeToIssue()}). Пользователь вне
     * проекта и заблокированный пользователь - ошибка запроса, а не молча
     * выброшенное из списка значение.
     * @param  Issue $issue      Задача, к которой назначаются участники.
     * @param  bool  $allowEmpty Допустим ли пустой список.
     * @return array<int>|ApiResponse Идентификаторы пользователей без повторов
     *         или ответ с ошибкой, если список назначить нельзя.
     */
    private function parseParticipants(Issue $issue, $allowEmpty)
    {
        $users = $this->request()->getBody('users');
        if (!is_array($users)) {
            return ApiResponse::error('users is required and must be an array of user ids', 400);
        }

        if (empty($users) && !$allowEmpty) {
            return ApiResponse::error('users must not be empty', 400);
        }

        $userIds = [];
        foreach ($users as $value) {
            if (!is_numeric($value) || (int)$value != $value || (int)$value <= 0) {
                return ApiResponse::error('Invalid user id, expected a positive integer', 400);
            }

            $userId = (int)$value;
            $user = User::load($userId);
            if (empty($user)) {
                return ApiResponse::error('User not found: ' . $userId, 404);
            }

            if ($user->locked) {
                return ApiResponse::error('User ' . $userId . ' is locked', 400);
            }

            if (!Project::checkUserReadPermit($issue->projectId, $userId)) {
                return ApiResponse::error('User ' . $userId . ' has no access to the project', 400);
            }

            $userIds[] = $userId;
        }

        return array_values(array_unique($userIds));
    }

    /**
     * Идентификаторы участников задачи в одной из ролей.
     * @param  Issue  $issue Задача.
     * @param  string $role  Роль участников, см. {@see ApiIssueController::MEMBER_ROLES}.
     * @return array<int> Идентификаторы пользователей.
     */
    private function participantIds(Issue $issue, $role)
    {
        switch ($role) {
            case self::ROLE_TESTER:
                $ids = $issue->getTesterIds();
                break;
            case self::ROLE_MASTER:
                $ids = $issue->getMasterIds();
                break;
            default:
                $ids = $issue->getMemberIds();
                break;
        }

        return array_map('intval', $ids);
    }

    /**
     * Приводит состав участников задачи в одной из ролей к заданному.
     *
     * Записывается только разница, поэтому повторное назначение того же
     * участника ничего не меняет и не порождает ни записи в журнале,
     * ни оповещения.
     * @param  Issue      $issue   Задача.
     * @param  string     $role    Роль участников, см. {@see ApiIssueController::MEMBER_ROLES}.
     * @param  array<int> $userIds Итоговый состав участников этой роли.
     * @return ApiResponse Обновлённая задача.
     * @throws Exception Если состав не удалось сохранить или перечитать задачу.
     */
    private function saveIssueParticipants(Issue $issue, $role, array $userIds)
    {
        $instanceType = self::MEMBER_ROLES[$role];
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        // Снимок состава - до записи в базу: списки участников грузятся
        // лениво и кешируются в объекте задачи
        $oldMemberIds = array_map('intval', $issue->getMemberIds());
        $oldTesterIds = array_map('intval', $issue->getTesterIds());
        $oldMasterIds = array_map('intval', $issue->getMasterIds());

        $current = $this->participantIds($issue, $role);
        $removed = array_values(array_diff($current, $userIds));
        $added = array_values(array_diff($userIds, $current));

        if (!empty($removed)) {
            if (!Member::deleteMembers($instanceType, $issue->id, $removed)) {
                throw new Exception('Failed to remove issue participants');
            }

            // Доля оценки задачи хранится отдельно от самого участия,
            // поэтому снятый исполнитель уносит с собой и её
            if ($role === self::ROLE_MEMBER && !IssueMember::deleteInfo($issue->id, $removed)) {
                throw new Exception('Failed to remove issue members estimate');
            }
        }

        if (!empty($added) && !Member::saveMembers($instanceType, $issue->id, $added)) {
            throw new Exception('Failed to assign issue participants');
        }

        $updated = Issue::load($issue->id);
        if (!$updated) {
            throw new Exception('Failed to load issue');
        }

        if (!empty($removed) || !empty($added)) {
            $user = $this->user();

            // Отметку о взятии в тестирование держит тестировщик задачи,
            // поэтому исключение его из тестировщиков снимает и отметку
            if ($role === self::ROLE_TESTER) {
                $updated->releaseFromTestingIfNotTester($user->getID());
            }

            $changes = IssueChangeSet::build(
                $issue,
                $oldMemberIds,
                $oldTesterIds,
                $oldMasterIds,
                $updated
            );

            UserLogEntry::issueEdit(
                $user->getID(),
                $issue->id,
                "Edit participants via api:\n" . $changes->asText()
            );

            Issue::notifyByEmail(
                $updated,
                IssueEmailFormatter::issueChangedSubject($updated),
                IssueEmailFormatter::issueChangedText($updated, $user, $changes),
                EmailNotifier::PREF_EDIT_ISSUE
            );
        }

        return ApiResponse::success([
            'issue' => $this->serializer()->issue($updated),
        ]);
    }

    /**
     * Ответ с задачей, перечитанной из базы: статус и стикер задачи могли
     * измениться, а загруженный объект их кэширует.
     * @return ApiResponse
     * @throws Exception Если задачу не удалось перечитать.
     */
    private function reloadedIssueResponse(Issue $issue, $statusCode = 200)
    {
        $reloaded = Issue::load($issue->id);
        if (!$reloaded) {
            throw new Exception('Failed to load issue');
        }

        return ApiResponse::success([
            'issue' => $this->serializer()->issue($reloaded),
        ], $statusCode);
    }

    /**
     * Приводит значение параметра `board` к одному из трёх его состояний.
     *
     * У параметра их три: не задан, `true` (колонка по статусу задачи) и имя
     * колонки. В multipart-запросе значение поля - всегда строка, поэтому
     * логическое значение приходит как `"true"` или `"false"`, и без разбора
     * строки `"false"` означала бы постановку на доску, а не отказ от неё.
     * Имена колонок с этими словами не пересекаются, так что разбор
     * их не затрагивает.
     * @param  mixed $board Значение параметра из тела запроса.
     * @return mixed Значение в том виде, в каком его разбирает
     *         {@see ApiIssueController::parseCreateBoard()}.
     */
    private function normalizeBoardValue($board)
    {
        if (!is_string($board)) {
            return $board;
        }

        $value = trim(strtolower($board));
        if ($value === 'true' || $value === '1') {
            return true;
        }

        if ($value === 'false' || $value === '0') {
            return false;
        }

        return $board;
    }

    /**
     * Разбирает параметр `board` запроса на создание задачи.
     * @param  Project $project Проект создаваемой задачи.
     * @return bool|int|ApiResponse `false` - задача создаётся без стикера,
     *         `true` - ставится на доску в колонку по своему статусу,
     *         число - состояние стикера заданной колонки. Ответ с ошибкой,
     *         если значение параметра не распознано.
     */
    private function parseCreateBoard(Project $project)
    {
        $board = $this->normalizeBoardValue($this->request()->getBody('board'));
        if ($board === null || $board === false || $board === '' || $board === 0) {
            return false;
        }

        if (!$project->scrum) {
            return ApiResponse::error('Project has no scrum board', 400);
        }

        if ($board === true) {
            return true;
        }

        $state = is_string($board) ? ApiPayloadSerializer::boardColumnState($board) : null;
        if ($state === null) {
            return ApiResponse::error(
                'Invalid board value, expected true or one of: ' .
                implode(', ', ApiPayloadSerializer::boardColumnKeys()),
                400
            );
        }

        return $state;
    }

    private function createIssue()
    {
        $projectId = $this->request()->getBody('projectId');
        if ($projectId === null || $projectId === '') {
            return ApiResponse::error('projectId is required', 400);
        }

        $project = $this->loadProject($projectId);
        if (!$project) {
            return ApiResponse::error('Project not found', 404);
        }

        $name = trim((string)$this->request()->getBody('name'));
        if ($name === '') {
            return ApiResponse::error('Issue name is required', 400);
        }

        if (!Issue::hasTitle($name)) {
            return ApiResponse::error('Issue name must contain a title, not only labels', 400);
        }

        if ($project->requireLabels && !Issue::hasLabels($name)) {
            return ApiResponse::error(
                'Project requires labels: issue name must start with a label, e.g. "[label] Title"',
                400
            );
        }

        $desc = (string)$this->request()->getBody('desc', '');
        if (mb_strlen($desc) > Issue::DESC_MAX_LEN) {
            return ApiResponse::error('Description is too long, max ' . Issue::DESC_MAX_LEN . ' characters', 400);
        }

        $type = (int)$this->request()->getBody('type', Issue::TYPE_DEVELOP);
        if (!in_array($type, [Issue::TYPE_BUG, Issue::TYPE_DEVELOP, Issue::TYPE_SUPPORT], true)) {
            return ApiResponse::error('Invalid issue type', 400);
        }

        // Приоритет в API — в той же шкале, что видит пользователь в интерфейсе.
        $minPriority = Issue::getPriorityDisplayValueBy(0);
        $maxPriority = Issue::getPriorityDisplayValueBy(Issue::MAX_PRIORITY);
        $priorityInput = $this->request()->getBody('priority');
        if ($priorityInput === null || $priorityInput === '') {
            $priorityInput = Issue::getPriorityDisplayValueBy(Issue::DEFAULT_PRIORITY);
        }

        if (!is_numeric($priorityInput) || (int)$priorityInput != $priorityInput
                || $priorityInput < $minPriority || $priorityInput > $maxPriority) {
            return ApiResponse::error(
                'Invalid priority, expected an integer ' . $minPriority . '..' . $maxPriority,
                400
            );
        }

        $priority = Issue::priorityFromDisplayValue($priorityInput);
        $hours = Issue::parseStoryPoints($this->request()->getBody('hours', 0));
        if (!Issue::isValidStoryPoints($hours)) {
            return ApiResponse::error('Invalid hours, expected a non-negative integer or 0.5', 400);
        }

        $completeDate = Issue::parseCompleteDate($this->request()->getBody('completeDate'));
        if ($completeDate === false) {
            return ApiResponse::error('Invalid completeDate, expected format YYYY-MM-DD', 400);
        }

        // Разбираем до создания задачи, чтобы некорректная колонка
        // не оставляла после себя созданную задачу
        $board = $this->parseCreateBoard($project);
        if ($board instanceof ApiResponse) {
            return $board;
        }

        // По той же причине проверяем вложения: непригодное вложение не должно
        // оставлять после себя созданную задачу
        $imagesData = $this->request()->getFiles(self::IMAGES_FIELD);
        $error = $this->validateImages($imagesData, self::IMAGES_FIELD, Issue::MAX_IMAGES_COUNT);
        if ($error !== null) {
            return ApiResponse::error($error, 400);
        }

        $filesData = $this->request()->getFiles(self::FILES_FIELD);
        $error = $this->validateAttachments($filesData, Issue::MAX_FILES_COUNT, Issue::MAX_FILES_COUNT);
        if ($error !== null) {
            return ApiResponse::error($error, 400);
        }

        $user = $this->user();
        $issueId = Issue::createNew($project, $name, $desc, $type, $priority, $hours, $completeDate, $user->getID());
        if (!$issueId) {
            throw new Exception('Failed to create issue');
        }

        $issue = Issue::load($issueId);
        if (!$issue) {
            throw new Exception('Failed to load created issue');
        }

        // Регистрация меток в справочнике: общего места сохранения имени задачи
        // нет, поэтому каждый способ создать или отредактировать задачу делает
        // это сам (веб-форма — в ProjectPage::saveIssue()).
        IssueLabel::registerLabelsUsage($name, $project->id);

        // Связи по упоминаниям в описании: общего места сохранения описания нет,
        // поэтому каждый способ создать или отредактировать задачу делает это сам
        // (веб-форма — в ProjectPage::saveIssue())
        IssueLinked::syncFromText($issue, $desc, $user->getID());

        if ($board !== false) {
            if ($board === true) {
                ScrumBoardManager::putOnBoard($issue);
            } else {
                ScrumBoardManager::changeState($issue, $board, $user, true);
            }
        }

        Project::updateIssuesCount($project->id);

        UserLogEntry::create(
            $user->getID(),
            DateTimeUtils::$currentDate,
            UserLogEntryType::ADD_ISSUE,
            $issue->id
        );

        // Вложения сохраняем до оповещения, чтобы открывший письмо о новой задаче
        // увидел её уже с ними. Запрос проверен выше, поэтому дойти сюда может
        // только сбой сохранения - задачу он не отменяет, и клиенту остаётся
        // добавить вложения изменением задачи
        // ({@see ApiIssueController::updateIssue()})
        if ($imagesData !== null) {
            $error = $this->uploadImages($issue, self::IMAGES_FIELD, Issue::MAX_IMAGES_COUNT);
            if ($error !== null) {
                throw new ApiException(
                    'Issue ' . $issue->id . ' is created, but its images are not saved: ' . $error,
                    500
                );
            }
        }

        if ($filesData !== null) {
            $result = FileUploadManager::upload(
                LPMInstanceTypes::ISSUE,
                $issue->id,
                $user->getID(),
                $filesData,
                Issue::MAX_FILES_COUNT,
                Issue::MAX_FILES_COUNT
            );

            if (!empty($result['errors'])) {
                throw new ApiException(
                    'Issue ' . $issue->id . ' is created, but its files are not saved: ' . $result['errors'][0],
                    500
                );
            }
        }

        Issue::notifyAdded($issue, $user);

        if ($board !== false) {
            return $this->reloadedIssueResponse($issue, 201);
        }

        return ApiResponse::success([
            'issue' => $this->serializer()->issue($issue),
        ], 201);
    }

    /**
     * Добавляет комментарий к задаче.
     *
     * Файлы передаются multipart-запросом в поле {@see ApiIssueController::FILES_FIELD};
     * комментарий, к которому приложен файл, может быть и без текста - как
     * и в интерфейсе.
     * @return ApiResponse Добавленный комментарий или ошибка проверки запроса.
     */
    private function createComment(Issue $issue)
    {
        $filesData = $this->request()->getFiles(self::FILES_FIELD);
        $text = trim((string)$this->request()->getBody('text'));
        if ($text === '' && $filesData === null) {
            return ApiResponse::error('Comment text is required', 400);
        }

        // Проверяем до публикации: иначе непригодный файл придётся откатывать
        // вместе с уже созданным комментарием
        $error = $this->validateAttachments($filesData, Comment::MAX_FILES_COUNT, Comment::MAX_FILES_COUNT);
        if ($error !== null) {
            return ApiResponse::error($error, 400);
        }

        $type = $this->request()->getBodyFlag('requestChanges') ? IssueCommentType::REQUEST_CHANGES : null;
        $comment = $this->engine()->comments()->postComment(
            $this->user(),
            $issue,
            $text,
            false,
            false,
            $type,
            null,
            $filesData
        );

        return ApiResponse::success([
            'comment' => $this->serializer()->comment($comment),
        ], 201);
    }

    /**
     * Частичное изменение задачи: меняется только то, что передано в запросе.
     *
     * Пока поддерживаются два поля - добавляемые изображения и файлы, которые
     * передаются multipart-запросом в полях {@see ApiIssueController::ADD_IMAGES_FIELD}
     * и {@see ApiIssueController::ADD_FILES_FIELD}; остальные поля задачи
     * появятся в этом же маршруте. Уже приложенные вложения занимают места
     * из лимитов задачи, но сохранению новых не мешают, пока места остаются.
     *
     * Изменение задачи оповещает её участников - так же, как правка
     * из интерфейса ({@see ProjectPage::notifyAboutIssueChange()}).
     * @return ApiResponse Обновлённая задача или ошибка запроса.
     * @throws ApiException Если вложения не удалось сохранить.
     * @throws Exception Если задачу не удалось перечитать.
     */
    private function updateIssue(Issue $issue)
    {
        $imagesData = $this->request()->getFiles(self::ADD_IMAGES_FIELD);
        $filesData = $this->request()->getFiles(self::ADD_FILES_FIELD);
        if ($imagesData === null && $filesData === null) {
            return ApiResponse::error(
                'Nothing to change: send the images to add as multipart/form-data in the "'
                . self::ADD_IMAGES_FIELD . '" field, the files to add in the "'
                . self::ADD_FILES_FIELD . '" field',
                400
            );
        }

        $imageSlots = Issue::MAX_IMAGES_COUNT - LPMImg::loadCountByInstance(LPMInstanceTypes::ISSUE, $issue->id);
        $error = $this->validateImages($imagesData, self::ADD_IMAGES_FIELD, $imageSlots);
        if ($error !== null) {
            return ApiResponse::error($error, 400);
        }

        $fileSlots = Issue::MAX_FILES_COUNT - LPMFile::countByInstance(LPMInstanceTypes::ISSUE, $issue->id);
        $error = $this->validateAttachments($filesData, $fileSlots, Issue::MAX_FILES_COUNT);
        if ($error !== null) {
            return ApiResponse::error($error, 400);
        }

        $user = $this->user();

        // Запрос проверен выше, поэтому дальше доходит только сбой сохранения,
        // а он на стороне сервера
        if ($imagesData !== null) {
            $error = $this->uploadImages($issue, self::ADD_IMAGES_FIELD, $imageSlots);
            if ($error !== null) {
                throw new ApiException('Images are not saved: ' . $error, 500);
            }
        }

        if ($filesData !== null) {
            $result = FileUploadManager::upload(
                LPMInstanceTypes::ISSUE,
                $issue->id,
                $user->getID(),
                $filesData,
                $fileSlots,
                Issue::MAX_FILES_COUNT
            );

            if (!empty($result['errors'])) {
                throw new ApiException('Files are not saved: ' . $result['errors'][0], 500);
            }
        }

        UserLogEntry::issueEdit($user->getID(), $issue->id, 'Add attachments via api');

        $updated = Issue::load($issue->id);
        if (!$updated) {
            throw new Exception('Failed to load issue');
        }

        Issue::notifyByEmail(
            $updated,
            IssueEmailFormatter::issueChangedSubject($updated),
            IssueEmailFormatter::issueChangedText($updated, $user),
            EmailNotifier::PREF_EDIT_ISSUE
        );

        return ApiResponse::success([
            'issue' => $this->serializer()->issue($updated),
        ]);
    }

    /**
     * Проверяет приложенные к запросу файлы - по тем же правилам, что
     * и форма в интерфейсе, и до того, как хоть что-то будет сохранено.
     * @param  array|null $filesData      Данные поля запроса с файлами.
     * @param  int        $availableSlots Сколько файлов ещё можно приложить.
     * @param  int        $totalLimit     Максимальное количество файлов.
     * @return string|null Текст первой ошибки или null, если файлы можно загружать.
     */
    private function validateAttachments($filesData, $availableSlots, $totalLimit)
    {
        if ($filesData === null) {
            return null;
        }

        $errors = FileUploadManager::validateUploads($filesData, $availableSlots, $totalLimit);

        return empty($errors) ? null : $errors[0];
    }

    /**
     * Проверяет приложенные к запросу изображения - по тем же правилам, что
     * и форма в интерфейсе (свои размер и форматы, {@see LPMImgUpload}),
     * и до того, как хоть что-то будет сохранено.
     * @param  array|null $imagesData     Данные поля запроса с изображениями.
     * @param  string     $field          Имя поля запроса.
     * @param  int        $availableSlots Сколько изображений ещё можно приложить.
     * @return string|null Текст первой ошибки или null, если изображения можно загружать.
     */
    private function validateImages($imagesData, $field, $availableSlots)
    {
        if ($imagesData === null) {
            return null;
        }

        // Лимит количества проверяем сами: загрузчик изображений молча
        // отбрасывает лишние, а для API это потеря без объяснения
        if (self::countUploads($imagesData) > max(0, (int)$availableSlots)) {
            return sprintf('Вы не можете прикрепить больше %d изображений', Issue::MAX_IMAGES_COUNT);
        }

        $errors = LPMImgUpload::validateUploadedFiles($field);

        return empty($errors) ? null : $errors[0];
    }

    /**
     * Сохраняет приложенные к запросу изображения задачи.
     *
     * Загружает их тем же способом, что и форма в интерфейсе
     * ({@see ProjectPage::saveImages4Issue()}): изображение попадает
     * в галерею задачи вместе с превью.
     * @param  Issue  $issue          Задача, к которой прикладываются изображения.
     * @param  string $field          Имя поля запроса.
     * @param  int    $availableSlots Сколько изображений ещё можно приложить.
     * @return string|null Текст ошибки или null, если всё сохранено.
     */
    private function uploadImages(Issue $issue, $field, $availableSlots)
    {
        $uploader = new LPMImgUpload(
            max(0, (int)$availableSlots),
            true,
            [LPMImg::PREVIEW_WIDTH, LPMImg::PREVIEW_HEIGHT],
            'issues',
            'scr_',
            LPMInstanceTypes::ISSUE,
            $issue->id,
            false
        );

        if ($uploader->uploadViaFiles($field)) {
            return null;
        }

        $errors = $uploader->getErrors();

        return empty($errors) ? 'Не удалось загрузить изображение' : $errors[0];
    }

    private function createBranch(Issue $issue)
    {
        $branchName = trim((string)$this->request()->getBody('name'));
        $repositoryId = (int)$this->request()->getBody('repositoryId');
        $parentBranch = trim((string)$this->request()->getBody('parentBranch', 'develop'));

        if ($repositoryId <= 0 || !$this->validateBranchName($branchName) || !$this->validateBranchName($parentBranch)) {
            return ApiResponse::error('Invalid branch creation arguments', 400);
        }

        return ApiResponse::success($this->createBranchPayload($issue, $branchName, $repositoryId, $parentBranch), 201);
    }

    private function createBranchPayload(Issue $issue, $branchName, $gitlabProjectId, $parentBranch)
    {
        $project = $issue->getProject();
        $client = $this->requireGitlab($project);
        $user = $this->user();
        $userId = $user->getID();

        $finalBranchName = 'feature/' . $branchName;
        $gitlabProject = $client->getProject($gitlabProjectId);
        if (!$gitlabProject) {
            throw new Exception('Repository not found');
        }

        $branch = $client->createBranch($gitlabProjectId, $parentBranch, $finalBranchName);
        if (!$branch) {
            throw new Exception('Branch creation failed');
        }

        $commentText = $branch->name;
        if ($parentBranch !== 'develop') {
            $commentText = $parentBranch . ' -> ' . $commentText;
        }
        $commentText = '*' . $gitlabProject->name . '*: `' . $commentText . '`';

        $comment = $this->engine()->comments()->postComment(
            $user,
            $issue,
            $commentText,
            true,
            false,
            IssueCommentType::CREATE_BRANCH,
            IssueCommentCreateBranchData::serialize($gitlabProjectId, $finalBranchName)
        );

        IssueBranch::create($issue->id, $gitlabProjectId, $finalBranchName, $userId, $branch->commit->id);

        GitlabWebhookManager::autoSetupForRepository($client, $gitlabProjectId);

        if ($issue->status == Issue::STATUS_IN_WORK) {
            if (!$issue->isMember($userId)) {
                if (!Member::saveIssueMembers($issue->id, [$userId])) {
                    throw new Exception('Failed to assign issue member');
                }

                $member = Member::loadByIssue($issue->id, $userId);
                if ($member) {
                    $issue->addMember($member);
                }

                UserLogEntry::issueEdit($userId, $issue->id, 'Add member by create branch via api');
            }

            $sticker = ScrumSticker::load($issue->id);
            if (!empty($sticker) && $sticker->state == ScrumStickerState::TODO) {
                if (!ScrumSticker::updateStickerState($issue->id, ScrumStickerState::IN_PROGRESS)) {
                    throw new Exception('Failed to move scrum sticker');
                }
            }

            // Стикер задачи мог переехать в другую колонку, а загруженная
            // задача его кэширует - иначе в ответе будут прежние
            // подстатус и колонка доски
            $issue->reloadSubstatusSources();
        }

        return [
            'branch' => [
                'name' => $branch->name,
                'url' => $branch->url,
                'parentBranch' => $parentBranch,
                'repository' => $this->serializer()->repository($gitlabProject),
            ],
            'comment' => $this->serializer()->comment($comment),
            'issue' => $this->serializer()->issue($issue),
        ];
    }
}
