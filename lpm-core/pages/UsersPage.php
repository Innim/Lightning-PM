<?php
class UsersPage extends BasePage
{
    const UID = 'users';
    const PUID_STAT = 'stat';

    public function __construct()
    {
        parent::__construct(self::UID, 'Пользователи', true, false, 'users');
        array_push($this->_js, 'users');

        $this->_defaultPUID = self::UID;

        $this->addSubPage(self::PUID_STAT, 'Статистика пользователей', 'users-stat', array('users-stat'));
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        if ($this->_curSubpage) {
            switch ($this->_curSubpage->uid) {
                case self::PUID_STAT:
                    $this->statByUsers();
                    break;
            }
        }

        return $this;
    }

    private function statByUsers()
    {
        list($month, $year) = StatHelper::parseMonthYearFromArg($this->getParam(2));

        $usersStat = [];
        list($prevMonth, $prevYear) = StatHelper::getPrevMonthYear($month, $year);
        list($nextMonth, $nextYear) = StatHelper::getNextMonthYear($month, $year);

        list($startDate, $endDate) = StatHelper::getStatDaysRange($month, $year);

        $users = User::loadList('');
        $snapshots = ScrumStickerSnapshot::loadListByDate($startDate, $endDate);

        foreach ($users as $user) {
            $userStat = new UserScrumStat($user);
            foreach ($snapshots as $snapshot) {
                if ($snapshot->hasMembers($user->userId)) {
                    $userStat->addSnapshot($snapshot);
                }
            }
            
            if ($userStat->getSnapshotsCount() > 0) {
                $usersStat[] = $userStat;
            }
        }

        $this->addTmplVar('month', $month);
        $this->addTmplVar('year', $year);
        $this->addTmplVar('usersStat', $usersStat);
        $this->addTmplVar('prevLink', $this->getMonthLink($prevMonth, $prevYear));
        if (StatHelper::isAvailable($nextMonth, $nextYear)) {
            $this->addTmplVar('nextLink', $this->getMonthLink($nextMonth, $nextYear));
        }
    }

    private function getMonthLink($month, $year)
    {
        return new Link(
            sprintf('%02d.%04d', $month, $year),
            $this->getUrl(StatHelper::getMonthForUrl($month, $year))
        );
    }
}
