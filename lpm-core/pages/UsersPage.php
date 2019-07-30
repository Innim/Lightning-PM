<?php
class UsersPage extends BasePage {
	const UID = 'users';
	const PUID_STAT = 'stat';

	function __construct() {
		parent::__construct(self::UID, 'Пользователи', true, false, 'users');
        array_push($this->_js, 'users');

		$this->_defaultPUID = self::UID;

		$this->addSubPage(self::PUID_STAT, 'Статистика пользователей', 'users-stat', array('users-stat'));
	}

	public function init() {
		if (!parent::init())
			return false;

		if ($this->_curSubpage) {
			switch ($this->_curSubpage->uid) {
				case self::PUID_STAT:
					$this->statByUsers();
					break;
			}
		}

		return $this;
	}

	private function statByUsers() {
		$nowYear = (int)date('Y');
		$nowMonth = (int)date('m');

		$monthStr = $this->getParam(2);
		if (!empty($monthStr)) {
			$monthArr = explode('-', $monthStr);
			$month = intval($monthArr[0]);
			$year = intval($monthArr[1]);
		} else {
			$month = $nowMonth;
			$year = $nowYear;
		}

		$usersStat = [];
		// День в месяце, законченный до которого спринт относим к предыдущему
		// TODO: вынести в поции? или просто константу?
		$dayInMonthForSprint = 5;
		$nextMonth = $month == 12 ? 1 : $month + 1;
		$nextYear = $month == 12 ? $year + 1 : $year;

		$prevMonth = $month == 1 ? 12 : $month - 1;
		$prevYear = $month == 1 ? $year - 1 : $year;

		$startDate = strtotime(sprintf('%02d.%02d.%04d', $dayInMonthForSprint + 1, $month, $year));
		$endDate = strtotime(sprintf('%02d.%02d.%04d', $dayInMonthForSprint, $nextMonth, $nextYear));

		$users = User::loadList('');
		$snapshots = ScrumStickerSnapshot::loadListByDate($startDate, $endDate);

		foreach ($users as $user) {
			$userStat = new UserScrumStat($user);			
			foreach ($snapshots as $snapshot) {
				if ($snapshot->hasMembers($user->userId))
					$userStat->addSnapshot($snapshot);
			}
			
			if ($userStat->getSnapshotsCount() > 0)
				$usersStat[] = $userStat;
		}

		$this->addTmplVar('month', $month);
		$this->addTmplVar('year', $year);
		$this->addTmplVar('usersStat', $usersStat);
		$this->addTmplVar('prevLink', $this->getMonthLink($prevMonth, $prevYear));
		if ($nextYear < $nowYear || $nextMonth <= $nowMonth)
			$this->addTmplVar('nextLink', $this->getMonthLink($nextMonth, $nextYear));
	}

	private function getMonthLink($month, $year) {
		return new Link(sprintf('%02d.%04d', $month, $year),
			$this->getUrl(sprintf('%02d-%04d', $month, $year)));
	}
}
?>