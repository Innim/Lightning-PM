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

    protected function loadMembers() {
        $this->_members = Member::loadListByInstance(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $this->sid);

        if ($this->_members === false)
            throw new Exception( 'Ошибка при загрузке снепшота списка исполнителей задачи' );

        return true;
    }

	public function loadStream($raw) {
	    parent::loadStream($raw);
	}

    public function isMember($userId) {
        if ($this->_members === null) {
            return Member::hasMember(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $this->issue_uid, $userId);
        } else {
            $found = false;
            foreach ($this->_members as $member) {
                if ($userId === $member->userId) {
                    $found = true;
                    break;
                }
            }
            return $found;
        }
    }

    /**
     * Путь до оригинальной задачи.
     */
    public function getURL4View() {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_ISSUE, $this->issue_pid);
    }
}