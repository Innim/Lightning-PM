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
    public static function loadList($snapshotId)
    {
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
     * Дата добавления стикера на доску.
     * @var int
     */
    public $added;
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
     * Количество SP по учатникам.
     * Может быть не задано, если в задаче 1 участник или
     * если запись старая и была сделана до введения функицонала.
     * @var string
     */
    public $issue_members_sp;
    /**
     * Приоритет задачи
     * @var int
     */
    public $issue_priority;

    // userId => sp
    private $_spByMemberId;

    public function __construct($id = 0)
    {
        parent::__construct();

        $this->id = $id;

        $this->_typeConverter->addFloatVars('id', 'sid', 'issue_uid', 'issue_pid');
        $this->_typeConverter->addIntVars('issue_state', 'issue_priority');
        $this->addDateTimeFields('added');
    }

    public function toString()
    {
        return $this->id . " " . $this->sid . " " . $this->issue_uid . " " . $this->issue_pid . " " . $this->issue_name .
            " " . $this->issue_state . " " . $this->issue_sp . " " . $this->issue_priority . " (" .
        $this->getMemberIdsStr() . ")" . "\n";
    }

    protected function loadMembers()
    {
        $this->_members = Member::loadListByInstance(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $this->id);

        if ($this->_members === false) {
            throw new Exception('Ошибка при загрузке снепшота списка исполнителей задачи');
        }

        return true;
    }

    public function loadStream($raw)
    {
        $res = parent::loadStream($raw);

        $this->_spByMemberId = [];
        if (!empty($this->issue_members_sp)) {
            $membersSp = json_decode($this->issue_members_sp);
            if (is_array($membersSp)) {
                foreach ($membersSp as $item) {
                    if (!is_object($item) || !isset($item->userId, $item->sp)) {
                        throw new Exception(
                            "Некорректные данные SP по учатникам для стикера #" . $this->id,
                            1
                        );
                    }

                    $this->_spByMemberId[$item->userId] = $item->sp;
                }
            }
        }

        return $res;
    }

    public function isMember($userId)
    {
        if ($this->_members === null) {
            return Member::hasMember(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $this->id, $userId);
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

    public function isTester($userId)
    {
        return Member::hasMember(LPMInstanceTypes::SNAPSHOT_ISSUE_FOR_TEST, $this->id, $userId);
    }

    public function getTesters()
    {
        $testers = Member::loadListByInstance(LPMInstanceTypes::SNAPSHOT_ISSUE_FOR_TEST, $this->id);
        if ($testers === false) {
            $testers = array();
        }
        return $testers;
    }

    /**
     * Получает количество SP по участнику.
     * Если такого учатсника нет - вернет 0. Если учатсник есть, но для него не заданы
     * SP (при условии что участников больше одного) - будет порождено исключение.
     * @param  int $userId
     * @return float
     */
    public function getSpByMember($userId)
    {
        if (!$this->isMember($userId)) {
            return 0;
        } else {
            if (count($this->getMembers()) > 1) {
                if (isset($this->_spByMemberId[$userId])) {
                    return $this->_spByMemberId[$userId];
                } else {
                    // TODO: наверное кидать исключение надо только для задача в готово/тесте?
                    $member = $this->getMember($userId);
                    throw new Exception("SP для участника " . $member->getName() .
                        " не заданы. Задача \"" . $this->issue_name . "\"");
                }
            } else {
                return $this->issue_sp;
            }
        }
    }

    /**
     * Путь до оригинальной задачи.
     */
    public function getURL4View()
    {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_ISSUE, $this->issue_pid);
    }
}
