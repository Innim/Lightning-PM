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
    /**
     * Разбирает список идентификаторов файлов, переданный формой.
     * @param  string $fileIdsStr Идентификаторы, разделённые запятой.
     * @return array Массив идентификаторов.
     */
    private static function parseFileIds($fileIdsStr)
    {
        return array_filter(array_map('intval', explode(',', (string)$fileIdsStr)));
    }

    /**
     * Возвращает значение параметра строки запроса.
     *
     * $_GET к этому моменту уже разобран и удалён LPMParams - движок оставляет
     * только заранее известные ему аргументы, а поиск задаётся параметрами
     * одной страницы. Поэтому строка запроса читается напрямую, как это
     * делает и внешнее API (ApiKey::getQueryArgs()).
     * @param  string $name Имя параметра.
     * @return string Значение параметра; пустая строка, если он не задан
     *         или задан не строкой.
     */
    private static function getQueryParam($name)
    {
        $query = [];
        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str((string)$_SERVER['QUERY_STRING'], $query);
        }

        return isset($query[$name]) && is_string($query[$name]) ? $query[$name] : '';
    }

    /**
     * Возвращает изображения, приложенные к форме до сохранения задачи.
     * @param  string $field Имя поля формы.
     * @return array Изображения строками base64.
     */
    private static function getPostedImages($field)
    {
        if (empty($_POST[$field]) || !is_array($_POST[$field])) {
            return [];
        }

        // Форма присылает изображения целыми data URI,
        // а LPMImgUpload::prepareImages() принимает только base64.
        return array_map(function ($value) {
            return preg_replace('~^data:[^,]*,~', '', (string)$value);
        }, $_POST[$field]);
    }

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

    /** Параметр строки запроса с поисковым запросом по списку задач. */
    const QUERY_ARG_SEARCH = 'search';
    /** Параметр строки запроса с областью поиска по статусу задачи. */
    const QUERY_ARG_SCOPE = 'scope';

    const SEARCH_SCOPE_OPENED = 'opened';
    const SEARCH_SCOPE_COMPLETED = 'completed';
    const SEARCH_SCOPE_ALL = 'all';

    /**
     * Области поиска по статусу задачи: подпись для интерфейса
     * и статусы задач, которые попадают в выборку (пусто - любые).
     */
    const SEARCH_SCOPES = [
        self::SEARCH_SCOPE_OPENED => [
            'label' => 'Открытые',
            'statuses' => [Issue::STATUS_IN_WORK, Issue::STATUS_WAIT],
        ],
        self::SEARCH_SCOPE_COMPLETED => [
            'label' => 'Завершённые',
            'statuses' => [Issue::STATUS_COMPLETED],
        ],
        self::SEARCH_SCOPE_ALL => [
            'label' => 'Все',
            'statuses' => [],
        ],
    ];

    /**
     *
     * @var Project
     */
    private $_project;

    private $_issueInput;

    /**
     * Изображения из буфера обмена и по URL, полученные до сохранения задачи.
     * @var array
     */
    private $_preparedImages;

    public function __construct()
    {
        parent::__construct(self::UID, '', true, true);

        // Просмотр проекта (и задачи — его подстраница) относится к разделу «Проекты»
        $this->_menuSectionUid = ProjectsPage::UID;

        $this->_js[] = 'project';

        $this->_pattern = 'project';
        
        $this->_baseParamsCount = 2;
        $this->_defaultPUID     = self::PUID_ISSUES;

        $this->addSubPage(
            self::PUID_ISSUES,
            'Список задач',
            '',
            array_merge(['project-issues', 'goto-issue'], $this->getIssuesListJs(), $this->getIssueJs())
        );
        $this->addSubPage(
            self::PUID_COMPLETED_ISSUES,
            'Завершенные',
            '',
            array_merge(['project-completed', 'goto-issue'], $this->getIssuesListJs(), $this->getIssueJs())
        );
        $this->addSubPage(
            self::PUID_COMMENTS,
            'Комментарии',
            'project-comments',
            array_merge(['goto-issue'], $this->getCommentJs())
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
                array_merge(['scrum-board', 'filters/scrum-board-filter', 'goto-issue'], $this->getIssueJs())
            );
            $this->addSubPage(
                self::PUID_SCRUM_BOARD_SNAPSHOT,
                'Scrum архив',
                'scrum-board-snapshot',
                array_merge(['goto-issue'], $this->getIssueJs())
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
                    $this->setOpenGraph($this->getTitleByIssue($issue), null, $imageUrl);
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

        $this->registerVisit($user);
        
        $this->_header = 'Проект "' . $this->_project->name . '"';
        $this->_title  = $this->_project->name;
        
        // проверяем, не добавили ли задачу или может отредактировали
        if (isset($_POST['actionType'])) {
            if (!CsrfToken::check()) {
                // Токен живёт вместе с сессией, а задачу пишут и дольше, чем
                // она держится без запросов. Если форма пришла с нашей же
                // страницы - возвращаем введённое, чтобы отправить его заново
                // (в форме уже новый токен), иначе текст задачи просто пропал бы.
                if (CsrfToken::isSameOrigin()) {
                    $this->_issueInput = ['data' => $_POST];
                    $this->_engine->addError(
                        'Страница устарела, задача не сохранена. Проверьте данные и отправьте форму ещё раз'
                    );
                } else {
                    $this->_engine->addError('Страница устарела. Обновите её и повторите действие');
                }
            } elseif ($_POST['actionType'] == 'addIssue') {
                $this->handleFormAction();
            } elseif ($_POST['actionType'] == 'editIssue' && isset($_POST['issueId'])) {
                $this->handleFormAction(true);
            }
        }

        if (!empty($this->_issueInput)) {
            // Ошибка могла возникнуть до снятия блокировки - тогда восстановленная
            // форма всё ещё владеет ей и не должна захватывать её повторно.
            $this->_issueInput['hasLock'] = $this->isIssueLockedByCurrentUser();
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

    /**
     * Отмечает, что пользователь открыл страницу проекта.
     *
     * Вызывается только при отрисовке страницы: ajax-вызовы идут мимо
     * страниц, отдельным входом, и посещением не считаются.
     * @param User $user Текущий пользователь.
     */
    private function registerVisit(User $user)
    {
        try {
            ProjectVisit::registerVisit($user->userId, $this->_project->id);
        } catch (\Exception $e) {
            // Неудачная отметка не должна мешать открыть проект
            LPMLog::exception($e, LPMLog::CH_APP, ['projectId' => (int)$this->_project->id]);
        }
    }

    /**
     * Готовит список задач проекта и форму поиска по нему.
     *
     * Показываются задачи выбранной области поиска, а если задан поисковый
     * запрос - только найденные среди них.
     */
    private function initIssues()
    {
        $search = trim(self::getQueryParam(self::QUERY_ARG_SEARCH));
        $scope = self::getQueryParam(self::QUERY_ARG_SCOPE);
        if (!isset(self::SEARCH_SCOPES[$scope])) {
            $scope = self::SEARCH_SCOPE_OPENED;
        }

        $scopes = [];
        foreach (self::SEARCH_SCOPES as $value => $data) {
            $scopes[$value] = $data['label'];
        }

        // Общая статистика проекта считает открытые задачи, поэтому к отобранному
        // списку она не относится - вместо неё показываем размер самой выборки
        $countLabel = null;
        if ($search !== '') {
            $countLabel = 'Найдено';
        } elseif ($scope !== self::SEARCH_SCOPE_OPENED) {
            $countLabel = 'Показано';
        }

        $this->addTmplVar('issues', $this->loadIssues(self::SEARCH_SCOPES[$scope]['statuses'], $search));
        $this->addTmplVar('search', $search);
        $this->addTmplVar('issuesCountLabel', $countLabel);
        $this->addTmplVar('searchForm', [
            'url' => $this->getUrl(),
            'scope' => $scope,
            'scopes' => $scopes,
        ]);
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
        // Обновлённый вид страницы задачи пока под экспериментальным флагом
        $this->_pattern = LPMOptions::getInstance()->newIssueView ? 'issue' : 'issue-legacy';
        ArrayUtils::remove($this->_js, 'project');
        $this->_js = array_merge(
            ['issue', 'popups/create-branch', 'popups/pass-test', 'popups/create-from-issue', 'popups/add-issue-link', 'goto-issue'],
            $this->getIssueJs(),
            $this->getCommentJs()
        );

        $this->initIssueSummary($issue, $comments);
        $this->initIssueTestChecklist($issue, $comments);

        $this->addTmplVar('issue', $issue);
        $this->addTmplVar('comments', $comments);
    }

    /**
     * Готовит данные блока ИИ-сводки задачи: доступность блока и уже
     * составленную сводку, если она есть. К модели при этом не обращается —
     * сводка составляется только по запросу пользователя.
     */
    private function initIssueSummary(Issue $issue, array $comments)
    {
        $available = IssueSummaryBuilder::isAvailableFor(
            $issue,
            $comments,
            $this->_engine->getUserId()
        );

        $this->addTmplVar('aiSummaryAvailable', $available);
        $this->addTmplVar('aiSummary', $available ? IssueSummary::loadByIssue($issue->getID()) : null);
        $this->addTmplVar('aiSummarySourceHash', $available
            ? IssueSummaryBuilder::sourceHash($issue, $comments) : '');

        if ($available) {
            $this->_js[] = 'issue-summary';
        }
    }

    /**
     * Готовит данные чек-листа тестирования: доступен ли он для задачи
     * и публиковался ли он раньше. К модели при этом не обращается —
     * чек-лист составляется только по запросу пользователя.
     */
    private function initIssueTestChecklist(Issue $issue, array $comments)
    {
        $available = IssueTestChecklistBuilder::isAvailableFor(
            $issue,
            $this->_engine->getUserId()
        );

        $this->addTmplVar('aiTestChecklistAvailable', $available);
        $this->addTmplVar(
            'aiTestChecklistPublished',
            $available && IssueTestChecklistBuilder::isPublished($comments)
        );

        if ($available) {
            $this->_js[] = 'popups/ai-test-checklist';
        }
    }

    private function initCompletedIssues()
    {
        // Своего поиска у подраздела нет: в основном списке задач можно искать
        // и среди завершённых
        $this->addTmplVar('issues', $this->loadIssues(self::SEARCH_SCOPES[self::SEARCH_SCOPE_COMPLETED]['statuses']));
        $this->addTmplVar('search', '');
        $this->addTmplVar('issuesCountLabel', null);
        $this->addTmplVar('searchForm', null);
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
        $this->addTmplVar('aiIntegrationAvailable', AiIntegration::getInstance()->isAvailable());
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
            'libs/highlight.pack',
            'formatting',
            'libs/tribute',
        ];
    }

    private function getCommentJs()
    {
        return [
            'comments',
            'attachments',
            'libs/highlight.pack',
            'formatting',
            'video-compress-status',
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
     * Загружает задачи проекта вместе с их исполнителями и тестировщиками.
     * @param  array<int> $statuses Статусы задач (пустой список - любые).
     * @param  string     $search   Поисковый запрос; пустой - без поиска.
     * @return array<Issue> Массив задач.
     */
    private function loadIssues($statuses, $search = '')
    {
        $projectId = $this->_project->id;

        if ($search !== '') {
            // Участников грузим только для найденных задач, а не для всех
            // задач проекта, как это делает выборка без поиска
            return Issue::preloadParticipants(Issue::loadListByProjectFiltered(
                $projectId,
                ['statuses' => $statuses, 'search' => $search]
            ));
        }

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
        try {
            $this->handleIssueForm($editMode);
        } finally {
            // Если сохранение не дошло до загрузки изображений - подготовленные
            // файлы уже не нужны.
            $this->clearPreparedImages();
        }
    }

    private function handleIssueForm($editMode)
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

            $issueName = $curIssue->name;
        } else {
            $issueId = null;
            $issueName = null;
        }

        if (!$this->checkRequiredFields($_POST)) {
            $this->addError('Заполнены не все обязательные поля');
            return;
        }

        if (!$this->validateInputData($_POST, $completeDateArr)) {
            return;
        }

        // Вложения проверяем до любых изменений: иначе задача будет сохранена,
        // а пользователь увидит только ошибку загрузки.
        if (!$this->validateAttachments($issueId, $editMode)) {
            return;
        }

        $revision = isset($_POST['revision']) ? trim($_POST['revision']) : null;

        // Убеждаемся, что сохранение вообще разрешено, прежде чем тратить время
        // на скачивание изображений по ссылкам. Блокировку при этом не снимаем.
        if (isset($curIssue) && !$this->checkIssueEditable($curIssue, $userId, $revision)) {
            return;
        }

        // Изображения из буфера обмена и по URL получаем во временные файлы
        // тоже до сохранения - по той же причине, что и проверку вложений.
        // Загрузка по URL может быть долгой, поэтому делаем её, пока задача
        // ещё заблокирована за нами - иначе за это время её может изменить
        // другой пользователь, и его правки будут затёрты.
        if (!$this->prepareImages()) {
            return;
        }

        if (isset($curIssue)) {
            // Пока обрабатывался запрос (в том числе скачивались изображения по URL),
            // задачу мог изменить кто-то другой, поэтому сверяем ревизию
            // с актуальными данными, а не с загруженными в начале запроса.
            $curIssue = Issue::load($issueId);
            if (empty($curIssue)) {
                return $engine->addError('Нет такой задачи для текущего проекта');
            }
            $issueName = $curIssue->name;

            if (!$this->unlockIssue($curIssue, $userId, $revision)) {
                return;
            }
        }

        // «Сырые» значения без экранирования — для создания задачи через Issue::createNew().
        $rawName = trim((string)$_POST['name']);
        $rawDesc = (string)$_POST['desc'];

        // TODO наверное нужен "белый список" тегов
        $_POST['desc'] = str_replace('%', '%%', $_POST['desc']);
        $_POST['hours']= str_replace('%', '%%', $_POST['hours']);
        $_POST['name'] = trim(str_replace('%', '%%', $_POST['name']));

        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['members', 'clipboardImg', 'draftImg', 'imgUrls', 'testers', 'membersSp', 'masters'])) {
                $_POST[$key] = $db->real_escape_string($value);
            }
        }

        $type = (int)$_POST['type'];
        $completeDate = empty($completeDateArr) ? null : $completeDateArr[1] . '-' .
                        $completeDateArr[2] . '-' .
                        $completeDateArr[3] . ' ' .
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
                // Создаём новые метки без использований, затем через addLabelsUsing
                // начисляем использование и в общий счётчик, и в счётчик по проекту.
                foreach ($labels as $newLabel) {
                    Issue::saveLabel($newLabel, $this->_project->id, 0, 0);
                }
                Issue::addLabelsUsing($labels, $this->_project->id);
            }
        }

        // Считаем SP
        $hours = $this->parseSP($_POST['hours']);
        $membersSp = null;
        $spTotal = 0;
        $spMembersCount = 0;
        if (isset($_POST['membersSp']) && is_array($_POST['membersSp'])) {
            $membersSp = [];
            foreach ($_POST['membersSp'] as $sp) {
                $sp = $this->parseSP($sp);
                if (!Issue::isValidStoryPoints($sp, true)) {
                    return $engine->addError('Оценка исполнителя в SP должна быть кратна 0.5');
                }
                $membersSp[] = $sp;
                $spTotal += $sp;
                if ($sp > 0) {
                    $spMembersCount++;
                }
            }

            // Дробные (кроме 0.5) оценки исполнителей допускаются только когда
            // SP распределяются между несколькими исполнителями.
            if ($spMembersCount < 2) {
                foreach ($membersSp as $sp) {
                    if (!Issue::isValidStoryPoints($sp)) {
                        return $engine->addError('Оценка исполнителя в SP должна быть целой или 0.5');
                    }
                }
            }

            if ($spTotal > 0 && $spTotal != $hours) {
                return $engine->addError('Количество SP по исполнителям не совпадает с общим');
            }
        }

        // Дробная общая оценка (кроме 0.5) допускается только как сумма
        // оценок нескольких исполнителей.
        if (!Issue::isValidStoryPoints($hours) && !($spMembersCount > 1 && $spTotal == $hours)) {
            return $engine->addError(
                'Дробная оценка (кроме 0.5) допускается только когда исполнителей несколько ' .
                'и она равна сумме их оценок'
            );
        }

        // сохраняем задачу
        if ($editMode) {
            $issueId = $this->saveIssue($db, $issueId, $_POST['name'], $_POST['desc'], $hours, $type, $completeDate, $priority, $userId);
        } else {
            $issueId = Issue::createNew($this->_project, $rawName, $rawDesc, $type, $priority, $hours, $completeDate, $userId);
            if (!$issueId) {
                return $this->addError('Ошибка записи в базу');
            }
        }
        if (!$issueId) return;

        if (!$editMode) {
            $this->saveLinkedIssues($userId, $issueId, $_POST['baseIds'], false);
            $this->saveLinkedIssues($userId, $issueId, $_POST['linkedIds'], true);
        }

        // Снимок состава участников до сохранения — для детализации оповещения об изменениях.
        // Снять нужно до записи в БД: списки грузятся лениво и кешируются в объекте.
        $oldMemberIds = $oldTesterIds = $oldMasterIds = [];
        if ($editMode && isset($curIssue)) {
            $oldMemberIds = $curIssue->getMemberIds();
            $oldTesterIds = $curIssue->getTesterIds();
            $oldMasterIds = $curIssue->getMasterIds();
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

        if (!$this->handleIssueFiles($issueId, $userId, $editMode)) {
            return;
        }

        // удаление старых изображений
        if (!empty($_POST["removedImages"])) {
            $this->removeImagesFromIssue($issueId, $_POST["removedImages"]);
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
            // Причина уже добавлена к ошибкам в saveImages4Issue
            return;
        }

        // перезагружаем данные задачи
        $issue = Issue::load($issueId);
        if (!$issue) {
            $engine->addError('Не удалось загрузить данные задачи');
            return;
        }

        // Отметку о взятии в тестирование держит тестировщик задачи,
        // поэтому исключение его из тестировщиков снимает и отметку
        $issue->releaseFromTestingIfNotTester($userId);

        // автоматически связываем задачу с упомянутыми в описании задачами
        IssueLinked::syncFromText($issue, $rawDesc, $userId);

        // Если это SCRUM проект
        if ($this->_project->scrum) {
            $putOnBoard = !empty($_POST['putToBoard']);
            if (!$this->updateScrumBoard($issue, $putOnBoard)) return;
        }

        // отсылаем оповещения
        $changes = null;
        if ($editMode && isset($curIssue)) {
            $changes = IssueChangeSet::build(
                $curIssue,
                $oldMemberIds,
                $oldTesterIds,
                $oldMasterIds,
                $issue
            );
        }
        $this->notifyAboutIssueChange($issue, $editMode, $changes);

        Project::updateIssuesCount($issue->projectId);

        // Записываем лог
        $logComment = '';
        if ($editMode) {
            $logComment = 'Full edit';
            if ($changes !== null && !$changes->isEmpty()) {
                $logComment .= ":\n" . $changes->asText();
            }
        }
        UserLogEntry::create(
            $userId,
            DateTimeUtils::$currentDate,
            $editMode ? UserLogEntryType::EDIT_ISSUE : UserLogEntryType::ADD_ISSUE,
            $issue->id,
            $logComment
        );

        // Очищаем сохраненные данные
        $this->_issueInput = null;
    
        // Номер в проекте выдаёт сама вставка задачи (Issue::createNew()),
        // поэтому адрес строим по фактическому, а не по посчитанному заранее.
        $issueURL = $this->getBaseUrl(ProjectPage::PUID_ISSUE, $issue->idInProject);
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
                "/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/",
                $inputCompleteDate,
                $completeDateMatches
            );
            if (!$res || !checkdate((int)$completeDateMatches[2], (int)$completeDateMatches[3], (int)$completeDateMatches[1]))
            {
                 return $this->addError('Недопустимый формат даты. Требуется формат ГГГГ-ММ-ДД');
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

        $name = trim((string)$input['name']);
        if (!Issue::hasTitle($name)) {
            $this->addError('У задачи должен быть заголовок, а не только теги');
        }

        if ($this->_project->requireLabels && !Issue::hasLabels($name)) {
            $this->addError('У задачи должен быть указан хотя бы один тег');
        }

        return !$this->hasErrors();
    }

    /**
     * Проверяет выбранные в форме изображения и файлы задачи.
     * Все обнаруженные ошибки добавляются к ошибкам страницы.
     * @param  float $issueId  Идентификатор задачи (при редактировании).
     * @param  bool  $editMode Задача редактируется, а не создаётся.
     * @return bool false, если хотя бы одно вложение не может быть загружено.
     */
    private function validateAttachments($issueId, $editMode)
    {
        $errors = LPMImgUpload::validateUploadedFiles('images');

        if (isset($_FILES['issueFiles']) && is_array($_FILES['issueFiles'])) {
            $errors = array_merge($errors, FileUploadManager::validateUploads(
                $_FILES['issueFiles'],
                $this->getAvailableFileSlots($issueId, $editMode),
                Issue::MAX_FILES_COUNT
            ));
        }

        foreach ($errors as $error) {
            $this->addError($error);
        }

        return empty($errors);
    }

    /**
     * Определяет, сколько файлов ещё можно прикрепить к задаче.
     * Файлы, удаляемые этим же запросом, освобождают места.
     * @param  float $issueId  Идентификатор задачи (при редактировании).
     * @param  bool  $editMode Задача редактируется, а не создаётся.
     * @return int
     */
    private function getAvailableFileSlots($issueId, $editMode)
    {
        $availableSlots = Issue::MAX_FILES_COUNT;

        if ($editMode) {
            $filesCount = LPMFile::countByInstance(LPMInstanceTypes::ISSUE, $issueId);
            $availableSlots -= max(0, $filesCount - $this->countRemovedFiles($issueId));
        }

        return max(0, $availableSlots);
    }

    /**
     * Считает файлы задачи, которые удаляются текущим запросом.
     * @param  float $issueId Идентификатор задачи.
     * @return int
     */
    private function countRemovedFiles($issueId)
    {
        if (empty($_POST['removedFiles'])) {
            return 0;
        }

        $fileIds = self::parseFileIds($_POST['removedFiles']);
        if (empty($fileIds)) {
            return 0;
        }

        return count(LPMFile::loadListByInstance(LPMInstanceTypes::ISSUE, $issueId, $fileIds));
    }

    /**
     * Определяет, принадлежит ли текущему пользователю блокировка задачи,
     * ввод по которой сохранён для восстановления формы.
     * @return bool
     */
    private function isIssueLockedByCurrentUser()
    {
        $issueId = empty($this->_issueInput['data']['issueId'])
            ? 0 : (float)$this->_issueInput['data']['issueId'];
        if ($issueId <= 0) {
            return false;
        }

        $lock = UserLock::getIssueLock($issueId);

        return !empty($lock) && $lock->userId == $this->_engine->getAuth()->getUserId();
    }

    /**
     * Получает во временные файлы изображения, приложенные к форме до
     * сохранения (вставленные из буфера обмена или перенесённые из черновика
     * задачи) и добавленные по URL, и проверяет их.
     * Все обнаруженные ошибки добавляются к ошибкам страницы.
     * @return bool false, если хотя бы одно изображение не может быть загружено.
     */
    private function prepareImages()
    {
        $errors = [];

        $images = array_merge(
            self::getPostedImages('clipboardImg'),
            self::getPostedImages('draftImg')
        );
        $urls = isset($_POST['imgUrls']) && is_array($_POST['imgUrls'])
            ? $_POST['imgUrls'] : [];

        $this->_preparedImages = LPMImgUpload::prepareImages($images, $urls, $errors);

        foreach ($errors as $error) {
            $this->addError($error);
        }

        return empty($errors);
    }

    /**
     * Удаляет временные файлы подготовленных изображений.
     * Уже загруженные (перенесённые из временной директории) изображения
     * при этом не затрагиваются.
     */
    private function clearPreparedImages()
    {
        if (!empty($this->_preparedImages)) {
            LPMImgUpload::removeTempFiles($this->_preparedImages);
            $this->_preparedImages = null;
        }
    }

    /**
     * Проверяет, что изменения задачи можно сохранить: указана актуальная ревизия
     * и задача не заблокирована другим пользователем.
     * Все обнаруженные ошибки добавляются к ошибкам страницы.
     * @param  Issue  $issue    Задача с актуальными данными.
     * @param  int    $userId   Идентификатор сохраняющего пользователя.
     * @param  String $revision Ревизия задачи, полученная от формы.
     * @return bool
     */
    private function checkIssueEditable(Issue $issue, $userId, $revision)
    {
        if ($revision === null) {
            return $this->addError('Требуется указать ревизию задачи для разблокировки');
        }

        if ($issue->revision != $revision) {
            // TODO: опцию переписать изменения
            return $this->addError('Задача была изменена кем-то другим. Невозможно сохранить изменения');
        }

        $lock = UserLock::getIssueLock($issue->getId());

        if (!empty($lock) && $userId != $lock->userId) {
            // TODO: опцию перехватить блокировку
            // TODO: данные о блокировке для отображения
            return $this->addError('Задача заблокирована другим пользователем. Невозможно сохранить изменения');
        }

        return true;
    }

    /**
     * Проверяет возможность сохранения изменений задачи и снимает её блокировку.
     * @param  Issue  $issue    Задача с актуальными данными.
     * @param  int    $userId   Идентификатор сохраняющего пользователя.
     * @param  String $revision Ревизия задачи, полученная от формы.
     * @return bool
     */
    private function unlockIssue(Issue $issue, $userId, $revision)
    {
        if (!$this->checkIssueEditable($issue, $userId, $revision)) {
            return false;
        }

        UserLock::removeIssueLocks($issue->getId());

        return true;
    }

    /**
     * Сохраняет изменения задачи и присваивает ей новую ревизию.
     * Прежнее содержимое задачи остаётся в истории слепков.
     * @param  DBConnect   $db           Соединение с БД.
     * @param  float       $issueId      Идентификатор задачи.
     * @param  String      $name         Название задачи, экранированное для запроса.
     * @param  String      $desc         Описание задачи, экранированное для запроса.
     * @param  float       $hours        Оценка: нормочасы или story points.
     * @param  int         $type         Тип задачи, см. Issue::TYPE_*.
     * @param  String|null $completeDate Плановая дата завершения в формате
     *                                   `ГГГГ-ММ-ДД ЧЧ:ММ:СС`; null — без даты.
     * @param  int         $priority     Приоритет задачи.
     * @param  int         $userId       Идентификатор сохраняющего пользователя.
     * @return float|bool Идентификатор задачи или false, если сохранить не удалось.
     */
    private function saveIssue(DBConnect $db, $issueId, $name, $desc, $hours, $type, $completeDate, $priority, $userId)
    {
        // Для задачи, созданной до появления истории, фиксируем её нынешнее
        // содержимое — иначе эта правка затрёт его, не оставив ни одного слепка.
        IssueContentSnapshot::recordBaseline($issueId);

        $revision = Issue::getNewRevision();
        $sql = "UPDATE `%s` SET " .
                    "`name` = '" . $name . "', " .
                    "`hours` = '" . $hours . "', " .
                    "`desc` = '" . $desc . "', " .
                    "`type` = '" . $type . "', " .
                    "`completeDate` = " . (empty($completeDate) ? 'NULL' : "'" . $completeDate . "'") . ", " .
                    "`priority` = '" . $priority . "', " .
                    "`revision` = '" . $revision . "' " .
                "WHERE `id` = '" . $issueId . "'";

        if (!$db->queryt($sql, LPMTables::ISSUES)) {
            return $this->addError('Ошибка записи в базу');
        }

        IssueContentSnapshot::record($issueId, $userId);

        // Если дальше возникнет ошибка, форма будет восстановлена из введённых данных.
        // Ревизия в ней должна быть актуальной, иначе повторное сохранение
        // будет отклонено как изменение задачи другим пользователем.
        if (isset($this->_issueInput['data'])) {
            $this->_issueInput['data']['revision'] = $revision;
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
        // и подготовленных заранее (вставленных из буфера и добавленных по URL)
        if (!$uploader->uploadViaFiles('images') || !$uploader->uploadPrepared($this->_preparedImages)) {
            $errors = $uploader->getErrors();
            $this->addError(empty($errors) ? 'Не удалось загрузить изображение' : $errors[0]);
            return false;
        }
        return $uploader;
    }

    private function handleIssueFiles($issueId, $userId, $editMode)
    {
        if (!empty($_POST['removedFiles'])) {
            $this->removeFilesFromIssue($issueId, $_POST['removedFiles']);
        }

        if (!isset($_FILES['issueFiles']) || !is_array($_FILES['issueFiles'])) {
            return true;
        }

        $filesData = $_FILES['issueFiles'];
        if (!isset($filesData['name']) || !is_array($filesData['name'])) {
            return true;
        }

        // Удаляемые файлы уже сняты выше, поэтому места они больше не занимают
        $availableSlots = $this->getAvailableFileSlots($issueId, $editMode);

        if ($availableSlots === 0 && !FileUploadManager::hasUploads($filesData)) {
            return true;
        }

        $result = FileUploadManager::upload(
            LPMInstanceTypes::ISSUE,
            $issueId,
            $userId,
            $filesData,
            $availableSlots,
            Issue::MAX_FILES_COUNT
        );

        if (!empty($result['errors'])) {
            $this->_engine->addError($result['errors'][0]);
            return false;
        }

        return true;
    }

    private function removeImagesFromIssue($issueId, $imagesIdsStr)
    {
        $delImg = explode(',', $imagesIdsStr);
        $imgIds = [];
        foreach ($delImg as $imgId) {
            $imgId = (int)$imgId;
            if ($imgId > 0) {
                $imgIds[] = $imgId;
            }
        }

        LPMImg::removeByIds(LPMInstanceTypes::ISSUE, $issueId, $imgIds);
    }

    private function removeFilesFromIssue($issueId, $filesIdsStr)
    {
        $fileIds = self::parseFileIds($filesIdsStr);
        if (empty($fileIds)) {
            return;
        }

        LPMFile::delete(LPMInstanceTypes::ISSUE, $issueId, $fileIds);
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

    /**
     * Добавляет к ошибкам страницы сообщение о неудачном изменении
     * положения задачи на доске.
     * @param  bool $putOnBoard Задачу пытались поместить на доску, а не снять.
     * @return bool Всегда false, как и addError().
     */
    private function addBoardError($putOnBoard)
    {
        return $this->addError($putOnBoard
            ? 'Не удалось поместить стикер на доску'
            : 'Не удалось снять стикер с доски');
    }

    /**
     * Приводит положение задачи на скрам-доске к состоянию флажка формы.
     *
     * Статус задачи при этом не меняется: колонка на доске выводится из статуса,
     * а не наоборот.
     * @param  Issue $issue      Сохранённая задача.
     * @param  bool  $putOnBoard Задача должна оказаться на доске.
     * @return bool false, если изменение не удалось; причина добавлена
     *              к ошибкам страницы.
     */
    private function updateScrumBoard(Issue $issue, $putOnBoard)
    {
        if ($issue->isOnBoard() == $putOnBoard) {
            return true;
        }

        $user = $this->_engine->getUser();

        try {
            if ($putOnBoard) {
                ScrumBoardManager::putOnBoard($issue);
            } else {
                ScrumBoardManager::removeFromBoard($issue, $user);
            }
        } catch (ScrumBoardException $e) {
            return $this->addBoardError($putOnBoard);
        } catch (\GMFramework\ProviderSaveException $e) {
            return $this->addBoardError($putOnBoard);
        }

        return true;
    }

    private function notifyAboutIssueChange(Issue $issue, $editMode, IssueChangeSet $changes = null)
    {
        $engine = $this->_engine;
        $user = $engine->getUser();
        if ($editMode) {
            Issue::notifyByEmail(
                $issue,
                IssueEmailFormatter::issueChangedSubject($issue),
                IssueEmailFormatter::issueChangedText($issue, $user, $changes),
                EmailNotifier::PREF_EDIT_ISSUE
            );
        } else {
            Issue::notifyAdded($issue, $user);
        }
    }

    private function getTitleByIssue(Issue $issue)
    {
        return '#' . $issue->idInProject . '. ' . $issue->name . ' - ' . $this->_project->name;
    }

    private function parseSP($value)
    {
        return Issue::parseStoryPoints($value);
    }
}
