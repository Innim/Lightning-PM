<?php
/**
 * Статистика пользователя по скрам спринтам.
 */
class UserScrumStat {
	public $user;
	private $_snapshots = [];

	function __construct(User $user) {
		$this->user = $user;
	}

	public function getSP() {
		$sum = 0;
		$userId = $this->user->userId;
		foreach ($this->_snapshots as $snapshot) {
			$sum += $snapshot->getMemberDoneSP($userId);
		}
		
		return $sum;
	}

	public function getSnapshotsCount() {
		return count($this->_snapshots);
	}

	/**
	 * Добавляет в список снимок спринта, в котором принимал участие пользователь.
	 * @param ScrumStickerSnapshot $snapshot [description]
	 */
	public function addSnapshot(ScrumStickerSnapshot $snapshot) {
		$this->_snapshots[] = $snapshot;
	}
}