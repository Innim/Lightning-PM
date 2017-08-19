<?php
/**
 * Стикер на Scrum доске.
 * Связан с задачей, каждому стикеру соответствует 1 задача
 */
class ScrumSticker extends LPMBaseObject
{
	public static function loadList($projectId) {
		$db = self::getDB();

		$states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS, 
			ScrumStickerState::TESTING, ScrumStickerState::DONE]);

		$sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`state` `s_state`, 'with_issue', `i`.*
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
     	 WHERE `i`.`projectId` = ${projectId} AND `i`.`deleted` = 0 
     	   AND `s`.`state` IN (${states})
 	  ORDER BY `i`.`priority` DESC
SQL;

		return StreamObject::loadObjList($db, 
			[$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES], __CLASS__);
	}

	/**
	 * Загружает стикер по идентификатору задачи
	 * @param  int $issueId 
	 * @return ScrumSticker
	 */
	public static function load($issueId) {
		$db = self::getDB();

		$sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`state` `s_state`, 'with_issue', `i`.*
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id` 
     	 WHERE `s`.`issueId` = ${issueId} AND `i`.`deleted` = 0 
SQL;

		$list = StreamObject::loadObjList($db, 
			[$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES], __CLASS__);

		return empty($list) ? null : $list[0];
	}

	public static function putStickerOnBoard(Issue $issue) {
		switch ($issue->status) {
			case Issue::STATUS_IN_WORK : $state = ScrumStickerState::TODO; break;
			case Issue::STATUS_WAIT : $state = ScrumStickerState::TESTING; break;
			case Issue::STATUS_COMPLETED : $state = ScrumStickerState::DONE; break;
			default : $state = ScrumStickerState::BACKLOG;
		}
		$issueId = $issue->id;

		$db = self::getDB();
		$sql = <<<SQL
        REPLACE `%s` SET `issueId` = ${issueId}, `state` = ${state} 
SQL;
		return $db->queryt($sql, LPMTables::SCRUM_STICKER);
	}

	public static function updateStickerState($issueId, $state) {
        $sql = <<<SQL
	        UPDATE `%s` SET `state` = ${state} 
	         WHERE `issueId` = ${issueId}
SQL;
		
		$db = self::getDB();
		return $db->queryt($sql, LPMTables::SCRUM_STICKER);
	}

	/**
	 * Идентифкиатор задачи
	 * @var int
	 */
	public $issueId;
	/**
	 * Текущее состояние - в какой колонке доски находится стикер
	 * @var int
	 * @see ScrumStickerState
	 */
	public $state;

	// Issue
	private $_issue;

	function __construct($id = 0) {
		parent::__construct();

		$this->_typeConverter->addFloatVars('issueId');
		$this->_typeConverter->addIntVars('state');
	}

	/**
	 * Возвщает отображаемое имя стикера
	 * @return String
	 */
	public function getName() {
	    return $this->_issue === null ? 
	    	'Задача #' . $this->issueId : $this->_issue->getName();
	}

	/**
	 * Возвращает объект задачи. Если не выставлен - будет загружен
	 * @return Issue
	 */
	public function getIssue() {
	    if ($this->_issue === null)
	    	$this->_issue = Issue::load($this->issueId);
	    return $this->_issue;
	}

	public function isOnBoard() {
	    // return $this->state !== ScrumStickerState::BACKLOG;
	    return ScrumStickerState::isActiveState($this->state);
	}

	/**
	 * Стикер находится в колонке TO DO
	 * @return boolean 
	 */
	public function isTodo() {
	    return $this->state === ScrumStickerState::TODO;
	}

	/**
	 * Стикер находится в колонке В работе
	 * @return boolean 
	 */
	public function isInProgress() {
	    return $this->state === ScrumStickerState::IN_PROGRESS;
	}

	/**
	 * Стикер находится в колонке Тестируется
	 * @return boolean 
	 */
	public function isTesting() {
	    return $this->state === ScrumStickerState::TESTING;
	}

	/**
	 * Стикер находится в колонке Выполнено
	 * @return boolean 
	 */
	public function isDone() {
	    return $this->state === ScrumStickerState::DONE;
	}

	/*public function nextState() {
	    return ScrumStickerState::getNextState($this->state);
	}

	public function prevState() {
	    return ScrumStickerState::getPrevState($this->state);
	}*/

	public function loadStream($raw) {
	    $data = [];
	    foreach ($raw as $key => $value) {
	    	if (strpos($key, 's_') === 0)
	    		$data[mb_substr($key, 2)] = $value;
	    }

	    parent::loadStream($data);

	    if (isset($raw['with_issue'])) {
	    	if ($this->_issue === null)
	    		$this->_issue = new Issue($this->issueId);
	    	$this->_issue->loadStream($raw);
	    }
	}
}