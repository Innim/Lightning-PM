<?php
/**
 * Базовый объект для статистики по scrum проекту.
 *
 * Эта статистика по спринтам может быть привязана к любой сущности: 
 * пользователь, проект и тп.
 */
abstract class ScrumStatBase extends LPMBaseObject {
	protected $_snapshots = [];

	private $_cachedTotalSp;
	private $_periodStart;
	private $_periodEnd;

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
		if (empty($this->_periodEnd) || $snapshot->created > $this->_periodEnd)
			$this->_periodEnd = $snapshot->created;

		if (!empty($snapshot->started) &&
				(empty($this->_periodStart) || $snapshot->started < $this->_periodStart))
			$this->_periodStart = $snapshot->started;

		$this->_snapshots[] = $snapshot;

		$this->_cachedTotalSp = null;
	}

	/**
	 * Возвращает форматированную строку для вывода периода, за который записана статистика.
	 * @return string
	 */
	public function getPeriod() {
		return empty($this->_periodStart) || empty($this->_periodEnd)
			? '-'
			: self::getDateStr($this->_periodStart) . ' - ' . self::getDateStr($this->_periodEnd);
	}

	/**
	 * Возвращает количество недель, укладывающихся в период.
	 * @return int
	 */
	public function getWeeksCount() {
		if (empty($this->_periodStart) || empty($this->_periodEnd))
			return 0;

		$start = new DateTime();
		$start->setTimestamp($this->_periodStart);

		$end = new DateTime();
		$end->setTimestamp($this->_periodEnd);

		return round($start->diff($end)->days / 7);
	}

	/**
	 * Возвращает количество сделанных SP по всем учтенным снимкам.
	 * @return int
	 */
	protected function getTotalDoneSP() {
		if ($this->_cachedTotalSp !== null)
			return $this->_cachedTotalSp;

		$sum = 0;
		foreach ($this->_snapshots as $snapshot) {
			$sum += $snapshot->getDoneSp();
		}

		$this->_cachedTotalSp = $sum;
		
		return $sum;
	}
}