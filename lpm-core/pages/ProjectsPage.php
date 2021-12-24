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
            if (!$this->addProject($_POST)) {
                $engine = LightningEngine::getInstance();
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

        return $this;
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
        $name = mb_substr($input['name'], 0, 255);
        $desc = mb_substr($input['desc'], 0, 65535);

        if (!$this->validateProjectUid($uid)) {
            return $this->error(
                'Введён недопустимый идентификатор - используйте латинские буквы, цифры и тире'
            );
        }

        if (Project::load($uid)) {
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

    private function validateProjectUid($value)
    {
        return \GMFramework\Validation::checkStr($value, 255, 1, false, false, true);
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
        $userId = LightningEngine::getInstance()->getUserId();
        $this->addTmplVar('stickers', ScrumSticker::loadAllStickersList($userId));
        return $this;
    }

    private function getMonthLink($month, $year)
    {
        return new Link(
            sprintf('%02d.%04d', $month, $year),
            $this->getUrl(StatHelper::getMonthForUrl($month, $year))
        );
    }
}
