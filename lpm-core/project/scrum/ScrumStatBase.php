<?php
/**
 * Базовый объект для статистики по scrum проекту.
 *
 * Эта статистика по спринтам может быть привязана к любой сущности: 
 * пользователь, проект и тп.
 */
abstract class ScrumStatBase {
	protected $_snapshots = [];

	/**
	 * Количество SP, которое должно отображаться в статистике.
	 *
	 * @return int
	 */
	public abstract function getSP();

	/**
	 * Количество спринтов в статстике.
	 * @return int
	 */
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

	/**
	 * Возвращает количество сделанных SP по всем учтенным снимкам.
	 * @return int
	 */
	protected function getTotalDoneSP() {
		$sum = 0;
		foreach ($this->_snapshots as $snapshot) {
			$sum += $snapshot->getDoneSp();
		}
		
		return $sum;
	}
}