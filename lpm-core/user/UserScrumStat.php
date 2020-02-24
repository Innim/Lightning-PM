<?php
/**
 * Статистика пользователя по скрам спринтам.
 */
class UserScrumStat extends ScrumStatBase  {
	public $user;

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
}