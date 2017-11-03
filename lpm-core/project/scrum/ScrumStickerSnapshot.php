<?php
/**
 * Архив scrum досок.
 */
class ScrumStickerSnapshot extends LPMBaseObject
{/*
	public static function loadList($projectId) {
		$db = self::getDB();

		$states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS, 
			ScrumStickerState::TESTING, ScrumStickerState::DONE]);

		$sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`state` `s_state`, 
			   'with_issue', `i`.*, `p`.`uid` as `projectUID`
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
    INNER JOIN `%3\$s` `p` ON `i`.`projectId` = `p`.`id`
     	 WHERE `i`.`projectId` = ${projectId} AND `i`.`deleted` = 0 
     	   AND `s`.`state` IN (${states})
 	  ORDER BY `i`.`priority` DESC
SQL;

		return StreamObject::loadObjList($db, 
			[$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES, LPMTables::PROJECTS], __CLASS__);
	}

	/**
	 * Загружает стикер по идентификатору задачи
	 * @param  int $issueId 
	 * @return ScrumSticker
	 *//*
	public static function load($issueId) {
		$db = self::getDB();

		$sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`state` `s_state`, 
			   'with_issue', `i`.*, `p`.`uid` as `projectUID`
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id` 
    INNER JOIN `%3\$s` `p` ON `i`.`projectId` = `p`.`id`
     	 WHERE `s`.`issueId` = ${issueId} AND `i`.`deleted` = 0 
SQL;

		$list = StreamObject::loadObjList($db, 
			[$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES, LPMTables::PROJECTS], __CLASS__);

		return empty($list) ? null : $list[0];
	*/

	private static function __log($value){
		GMLog::getInstance()->logIt(
			GMLog::getInstance()->logsPath . 'cmx_log', $value);
	}

	/**
	 * Создает snapshot по текущему состоянию доски для переданного проекта.
	 * @param int $projectId
	 * @param float $userId
	 */
	public static function createSnapshot($projectId, $userId){
		// получаем список всех стикеров на текущей доске
		$stickers = ScrumSticker::loadList($projectId);

		$names = "\n";

		$sid = 0;
		$pid = $projectId;
		$created = DateTimeUtils::mysqlDate();
		$creatorId = $userId;

		foreach ($stickers as $sticker) {
			/* @var $sticker ScrumSticker */

			$issue = $sticker->getIssue();

			$issueUid = $sticker->issueId;
			$issuePid = $issue->idInProject;
			$issueName = $sticker->getName();
			$issueState = $sticker->state;
			$issueSP = $issue->hours;
			$issuePriority = $issue->priority;
			$members = $issue->getMemberIdsStr();

			$names .= "$issueUid, $issuePid, $issueName, $issueState, $issueSP, $issuePriority, ($members)" . "\n";



//			$sticker->getIssue()
		}

		ScrumStickerSnapshot::__log("names: $names");
	}

	/**
	 * Идентификатор snapshot-а.
	 * @var int
	 */
	public $id;
	/**
	 * Идентификатор проекта, для которого сделан snapshot.
	 * @var int
	 */
	public $pid;
	/**
	 * Дата создания snapshot-а.
	 * @var
	 */
	public $created;
	/**
	 * Идентификатор пользователя, создавшего snapshot.
	 * @var
	 */
	public $creatorId;

	function __construct($id = 0) {
		parent::__construct();

		$this->id = $id;

		$this->_typeConverter->addFloatVars('id', 'pid', 'creatorId');
		$this->addDateTimeFields('created');

		// TODO:
//		$this->addClientFields(
//			'id', 'parentId', 'idInProject', 'name', 'desc', 'type', 'authorId', 'createDate',
//			'completeDate','completedDate', 'startDate', 'priority', 'status' ,'commentsCount', 'hours'
//		);
	}

	/**
	 * Возвщает отображаемое имя стикера
	 * @return String
	 */
//	public function getName() {
//	    return $this->_issue === null ?
//	    	'Задача #' . $this->issueId : $this->_issue->getName();
//	}

	/**
	 * Возвращает объект задачи. Если не выставлен - будет загружен
	 * @return Issue
	 */
//	public function getIssue() {
//	    if ($this->_issue === null)
//	    	$this->_issue = Issue::load($this->issueId);
//	    return $this->_issue;
//	}

//	public function isOnBoard() {
//	    // return $this->state !== ScrumStickerState::BACKLOG;
//	    return ScrumStickerState::isActiveState($this->state);
//	}

	/**
	 * Стикер находится в колонке TO DO
	 * @return boolean 
	 */
//	public function isTodo() {
//	    return $this->state === ScrumStickerState::TODO;
//	}

	/**
	 * Стикер находится в колонке В работе
	 * @return boolean 
	 */
//	public function isInProgress() {
//	    return $this->state === ScrumStickerState::IN_PROGRESS;
//	}

	/**
	 * Стикер находится в колонке Тестируется
	 * @return boolean 
	 */
//	public function isTesting() {
//	    return $this->state === ScrumStickerState::TESTING;
//	}

	/**
	 * Стикер находится в колонке Выполнено
	 * @return boolean 
	 */
//	public function isDone() {
//	    return $this->state === ScrumStickerState::DONE;
//	}

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

//	    if (isset($raw['with_issue'])) {
//	    	if ($this->_issue === null)
//	    		$this->_issue = new Issue($this->issueId);
//	    	$this->_issue->loadStream($raw);
//	    }
	}
}