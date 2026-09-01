<?php
/**
 * Раздел проектов.
 */
class ProjectsPage extends LPMPage
{
    const UID = 'projects';
    const PUID_DEVELOP = 'develop';
    const PUID_ARCH = 'projects-archive';
    const PUID_USER_ISSUES = 'user-issues';
    const PUID_STAT = 'stat';
    const PUID_MY_SCRUM_BOARD = 'scrum-board-common';

    /**
     * Поле формы, по которому распознаётся отправка настроек личной scrum доски.
     */
    const FIELD_MY_BOARD_PREF = 'myBoardPref';
    /**
     * Поле формы с переключателем показа свободных задач.
     */
    const FIELD_SHOW_FREE_ISSUES = 'showFreeIssues';

    // Количество важных задач, открытых для меня по всем проектам
    private $_myIssuesCount = -1;

    public function __construct()
    {
        parent::__construct(self::UID, 'Проекты', true, false, 'projects', 'Проекты');
        $this->_pattern = 'projects';
        
        $this->_js[] = 'projects';

        $this->_defaultPUID = self::PUID_DEVELOP;

        $this->addSubPage(self::PUID_DEVELOP, 'В разработке');
        $this->addSubPage(self::PUID_ARCH, 'Архив', 'projects-archive');
        $this->addSubPage(self::PUID_USER_ISSUES, 'Мои задачи', 'user-issues', ['issues']);
        $this->addSubPage(
            self::PUID_MY_SCRUM_BOARD,
            'Моя Scrum доска',
            'scrum-board-common',
            ['scrum-board', 'issues', 'libs/tribute']
        );
        $this->addSubPage(
            self::PUID_STAT,
            'Статистика',
            'projects-stat',
            ['projects-stat'],
            'Статистика по проектам',
            User::ROLE_MODERATOR
        );
    }
    
    public function init()
    {
        if (!parent::init()) {
            return false;
        }
        
        if (!empty($_POST)) {
            $engine = LightningEngine::getInstance();
            $isMyBoardPref = isset($_POST[self::FIELD_MY_BOARD_PREF]);
            if (!CsrfToken::check()) {
                $engine->addError('Страница устарела. Обновите её и повторите действие');
                // Форму настроек отправляет сама доска - её и показываем с ошибкой
                if ($isMyBoardPref) {
                    return $this->myScrumBoard();
                }
            } elseif ($isMyBoardPref) {
                return $this->saveMyScrumBoardPref($_POST);
            } elseif (!$this->addProject($_POST)) {
                $engine->addError($this->_error);
            }
        } elseif ($this->_curSubpage) {
            switch ($this->_curSubpage->uid) {
                case self::PUID_DEVELOP:
                    return $this->projectsList(false);
                case self::PUID_ARCH:
                    return $this->projectsList(true);
                case self::PUID_STAT:
                    return $this->statByProjects();
                case self::PUID_MY_SCRUM_BOARD:
                    return $this->myScrumBoard();
            }
            // TODO: загрузка данных для остальных подстраниц
        }

        // TODO: вообще если сюда дошли, то это должна быть ошибка
        // т.к. тут в любом случае должна быть подстраница
        // но надо допилить логику подстраниц и обработки добавления 

        return $this->projectsList(false);
    }

    public function getLabel()
    {
        $label = parent::getLabel();

        if ($this->_myIssuesCount === -1) {
            $userId = LightningEngine::getInstance()->getUserId();
            $this->_myIssuesCount = Issue::getCountImportantIssues($userId);
        }

        if ($this->_myIssuesCount > 0) {
            $label .= ' (' . $this->_myIssuesCount . ')';
        }

        return $label;
    }

    /**
     * Выпадающий список последних проектов пользователя.
     *
     * Открытый сейчас проект из списка не убирается, а помечается текущим:
     * так набор пунктов не меняется при переходах между его страницами.
     * @return MenuDropdown|null `null`, если показывать нечего.
     */
    public function getMenuDropdown()
    {
        $engine = LightningEngine::getInstance();
        if (!$engine->isAuth()) {
            return null;
        }

        try {
            $projects = ProjectVisit::loadRecentProjects(
                $engine->getUser(),
                RECENT_PROJECTS_MENU_COUNT
            );
        } catch (\Exception $e) {
            // Меню рисуется на каждой странице - из-за недоступного списка
            // недавних проектов не должно падать всё приложение
            LPMLog::exception($e, LPMLog::CH_APP);
            return null;
        }

        if (empty($projects)) {
            return null;
        }

        $currentProject = Project::$currentProject;
        $items = [];
        foreach ($projects as $project) {
            $item = new Link($project->name, $project->getUrl());
            $item->setCurrent($currentProject && $currentProject->id == $project->id);
            $items[] = $item;
        }

        return new MenuDropdown(
            'recentProjectsMenu',
            'Последние проекты',
            $items,
            new Link('Все проекты', $this->getBaseUrl())
        );
    }

    private function projectsList($isArchive): ProjectsPage {
        $list = Project::getAvailList($isArchive);
        $this->addTmplVar('list', $list);
        return $this;
    }

    private function addProject($input)
    {
        foreach ($input as $key => $value) {
            $input[$key] = trim($value);
        }

        // добавление нового проекта
        if (empty($input['name']) || empty($input['uid']) || empty($input['desc'])) {
            return $this->error('Заполнены не все поля');
        }

        $uid  = strtolower($input['uid']);
        $name = mb_substr($input['name'], 0, PROJECT_NAME_MAX_LENGTH);
        $desc = mb_substr($input['desc'], 0, PROJECT_DESC_MAX_LENGTH);

        if (!Project::isValidUid($uid)) {
            return $this->error(
                'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире'
            );
        }

        if (!Project::isUidAvailable($uid)) {
            return $this->error('Проект с таким uid уже существует');
        }

        if (!Project::addProject($uid, $name, $desc)) {
            return $this->error('Не удалось создать проект');
        } else {
            // переход на страницу проекта
            LightningEngine::go2URL($this->getUrl());
        }
        return true;
    }

    private function statByProjects(): ProjectsPage
    {
        list($month, $year) = StatHelper::parseMonthYearFromArg($this->getParam(2));

        $projectsStat = [];
        list($prevMonth, $prevYear) = StatHelper::getPrevMonthYear($month, $year);
        list($nextMonth, $nextYear) = StatHelper::getNextMonthYear($month, $year);

        list($startDate, $endDate) = StatHelper::getStatDaysRange($month, $year);

        $projects = Project::loadScrumList();
        $snapshots = ScrumStickerSnapshot::loadListByDate($startDate, $endDate);

        foreach ($projects as $project) {
            $projectStat = new ProjectScrumStat($project);
            foreach ($snapshots as $snapshot) {
                if ($snapshot->pid == $project->id) {
                    $projectStat->addSnapshot($snapshot);
                }
            }
            
            if ($projectStat->getSnapshotsCount() > 0) {
                $projectsStat[] = $projectStat;
            }
        }

        usort($projectsStat, function ($a, $b) {
            return $b->getSP() - $a->getSP();
        });

        $totalSP = 0;
        foreach ($projectsStat as $projectStat) {
            $totalSP += $projectStat->getSP();
        }

        $this->addTmplVar('month', $month);
        $this->addTmplVar('year', $year);
        $this->addTmplVar('projectsStat', $projectsStat);
        $this->addTmplVar('totalSP', $totalSP);
        $this->addTmplVar('prevLink', $this->getMonthLink($prevMonth, $prevYear));
        if (StatHelper::isAvailable($nextMonth, $nextYear)) {
            $this->addTmplVar('nextLink', $this->getMonthLink($nextMonth, $nextYear));
        }

        return $this;
    }

    private function myScrumBoard(): ProjectsPage
    {
        $engine = LightningEngine::getInstance();
        $showFreeIssues = $engine->getUser()->pref->showFreeIssuesOnBoard;

        list($stickers, $freeIssueIds) =
            $this->loadMyScrumBoardStickers($engine->getUserId(), $showFreeIssues);

        $this->addTmplVar('stickers', $stickers);
        $this->addTmplVar('freeIssueIds', $freeIssueIds);
        $this->addTmplVar('showFreeIssues', $showFreeIssues);
        return $this;
    }

    /**
     * Собирает стикеры личной scrum доски.
     *
     * Свободные идут в конец списка, поэтому в своей колонке показываются
     * после задач пользователя. Какие из них свободны - признак этой доски,
     * а не самих стикеров: на доске проекта та же задача свободной не считается.
     * Поэтому список свободных возвращается отдельно.
     * @param  int  $userId         Идентификатор пользователя.
     * @param  bool $withFreeIssues Добавить ли свободные задачи из проектов пользователя.
     * @return array Пара: список стикеров и множество `issueId => true` свободных.
     */
    private function loadMyScrumBoardStickers($userId, $withFreeIssues)
    {
        $stickers = ScrumSticker::loadUserStickersList($userId);
        $freeIssueIds = [];

        if ($withFreeIssues) {
            $ownIssueIds = [];
            foreach ($stickers as $sticker) {
                $ownIssueIds[$sticker->issueId] = true;
            }

            foreach (ScrumSticker::loadFreeStickersList($userId) as $sticker) {
                // Задача без исполнителя, где пользователь тестировщик,
                // попадает в оба списка - свою запись оставляем
                if (isset($ownIssueIds[$sticker->issueId])) {
                    continue;
                }

                $stickers[] = $sticker;
                $freeIssueIds[$sticker->issueId] = true;
            }
        }

        // Один запрос на участников для всего итогового списка
        ScrumSticker::preloadParticipants($stickers);

        return [$stickers, $freeIssueIds];
    }

    /**
     * Сохраняет настройки личной scrum доски и открывает её заново.
     *
     * После сохранения делается редирект, чтобы обновление страницы
     * не отправляло форму повторно.
     * @param  array $input Данные формы.
     * @return ProjectsPage
     */
    private function saveMyScrumBoardPref($input): ProjectsPage
    {
        $userId = LightningEngine::getInstance()->getUserId();

        try {
            UserPref::saveShowFreeIssuesOnBoard(
                $userId,
                !empty($input[self::FIELD_SHOW_FREE_ISSUES])
            );
        } catch (\GMFramework\ProviderSaveException $e) {
            LightningEngine::getInstance()->addError('Не удалось сохранить настройку доски');
            return $this->myScrumBoard();
        }

        LightningEngine::go2URL(Link::getUrlByUid(self::UID, self::PUID_MY_SCRUM_BOARD));
    }

    private function getMonthLink($month, $year)
    {
        return new Link(
            sprintf('%02d.%04d', $month, $year),
            $this->getUrl(StatHelper::getMonthForUrl($month, $year))
        );
    }
}
