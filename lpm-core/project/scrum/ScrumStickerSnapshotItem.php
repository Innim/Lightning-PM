<?php
/**
 * Данные снепшота стикера на Scrum доске.
 */
class ScrumStickerSnapshotItem extends MembersInstance
{
    /**
     * Загружает список элементов снепшота по идентификатору снепшота.
     * @param int $snapshotId
     * @return ScrumStickerSnapshotItem[]
     * @throws DBException
     * @throws Exception
     */
	public static function loadList($snapshotId) {
        $snapshotId = (int) $snapshotId;

		$db = self::getDB();

        $sql = <<<SQL
            SELECT * FROM `%1\$s` WHERE `%1\$s`.`sid` = '${snapshotId}'
SQL;

        /* @var $items ScrumStickerSnapshotItem[] */
		$items = StreamObject::loadObjList($db, [$sql, LPMTables::SCRUM_SNAPSHOT], __CLASS__);

        // TODO: можно ли и нужно ли объединить с запросом выше?
        // TODO: нужно ли грузить одним запросом?
        // грузим всех пользователей по задачам
        foreach ($items as $item) {
            $item->loadMembers();
        }

        return $items;
	}

    /**
     * Идентификатор элемента.
     * @var int
     */
	public $id;
    /**
     * Идентификатор снепшота.
     * @var int
     */
	public $sid;
    /**
     * Идентификатор задачи.
     * @var int
     */
	public $issue_uid;
    /**
     * Идентификатор задачи в проекте.
     * @var
     */
	public $issue_pid;
    /**
     * Название задачи.
     * @var string
     */
	public $issue_name;
    /**
     * Текущее состояние задачи.
     * @var int
     */
	public $issue_state;
    /**
     * Количество SP
     * @var string
     */
	public $issue_sp;
    /**
     * Приоритет задачи
     * @var int
     */
	public $issue_priority;

	function __construct($id = 0) {
		parent::__construct();

		$this->id = $id;

		$this->_typeConverter->addFloatVars('id', 'sid', 'issue_uid', 'issue_pid');
		$this->_typeConverter->addIntVars('issue_state', 'issue_priority');
	}

    public function toString() {
        return $this->id . " " . $this->sid . " " . $this->issue_uid . " " . $this->issue_pid . " " . $this->issue_name .
            " " . $this->issue_state . " " . $this->issue_sp . " " . $this->issue_priority . " (" .
        $this->getMemberIdsStr() . ")" . "\n";
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

    protected function loadMembers() {
        $this->_members = Member::loadListByInstance(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $this->issue_uid);

        if ($this->_members === false)
            throw new Exception( 'Ошибка при загрузке снепшота списка исполнителей задачи' );

        return true;
    }

	public function loadStream($raw) {
	    parent::loadStream($raw);
	}
}