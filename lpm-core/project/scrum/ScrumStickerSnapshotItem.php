<?php
/**
 * Данные снапшота стикера на Scrum доске.
 */
class ScrumStickerSnapshotItem extends MembersInstance
{
    /**
     * Загружает список элементов снапшота по идентификатору снапшота.
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

        // Заранее загружаем исполнителей и тестировщиков всех стикеров снапшота
        // двумя запросами (вместо запроса на каждый стикер при отрисовке).
        $itemIds = [];
        foreach ($items as $item) {
            $itemIds[] = $item->id;
        }
        $membersById = self::groupByInstanceId(
            self::loadSnapshotParticipants(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $itemIds)
        );
        $testersById = self::groupByInstanceId(
            self::loadSnapshotParticipants(LPMInstanceTypes::SNAPSHOT_ISSUE_FOR_TEST, $itemIds)
        );
        foreach ($items as $item) {
            $id = (int) $item->id;
            $item->setParticipants(
                isset($membersById[$id]) ? $membersById[$id] : [],
                isset($testersById[$id]) ? $testersById[$id] : []
            );
        }

        return $items;
    }

    /**
     * Загружает участников снапшота указанного типа сразу для нескольких стикеров одним запросом.
     * @param int $instanceType Тип участия (SNAPSHOT_ISSUE_MEMBERS / SNAPSHOT_ISSUE_FOR_TEST).
     * @param int[] $itemIds Идентификаторы стикеров снапшота.
     * @return Member[]
     */
    private static function loadSnapshotParticipants($instanceType, array $itemIds)
    {
        if (empty($itemIds)) {
            return [];
        }

        $ids = implode(', ', array_map('intval', $itemIds));
        $list = Member::loadListByInstance(
            $instanceType,
            null,
            false,
            null,
            null,
            null,
            "`m`.`instanceId` IN ($ids)"
        );

        return $list === false ? [] : $list;
    }

    /**
     * Группирует список участников по идентификатору стикера (`instanceId`).
     * @param Member[] $members
     * @return array<int, Member[]>
     */
    private static function groupByInstanceId($members)
    {
        $byId = [];
        foreach ($members as $member) {
            $byId[(int) $member->instanceId][] = $member;
        }
        return $byId;
    }

    /**
     * Идентификатор элемента.
     * @var int
     */
    public $id;
    /**
     * Идентификатор снапшота.
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
     * Количество SP по участникам.
     * Может быть не задано, если в задаче 1 участник или
     * если запись старая и была сделана до введения функционала.
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

    /**
     * Кэш тестировщиков стикера (заполняется batch-загрузкой в {@see loadList()}).
     * @var Member[]|null
     */
    private $_testers = null;

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
            throw new Exception('Ошибка при загрузке снапшота списка исполнителей задачи');
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
                            "Некорректные данные SP по участникам для стикера #" . $this->id,
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

    /**
     * Устанавливает предварительно загруженных исполнителей и тестировщиков стикера.
     *
     * Используется batch-загрузкой в {@see loadList()}, чтобы отрисовка снапшота
     * не делала запрос на каждый стикер.
     * @param Member[] $members
     * @param Member[] $testers
     */
    public function setParticipants($members, $testers)
    {
        $this->_members = $members;
        $this->_testers = $testers;
    }

    public function isTester($userId)
    {
        foreach ($this->getTesters() as $tester) {
            if ($userId == $tester->userId) {
                return true;
            }
        }
        return false;
    }

    public function getTesters()
    {
        if ($this->_testers === null) {
            $this->_testers = Member::loadListByInstance(LPMInstanceTypes::SNAPSHOT_ISSUE_FOR_TEST, $this->id);
            if ($this->_testers === false) {
                $this->_testers = array();
            }
        }
        return $this->_testers;
    }

    /**
     * Получает количество SP по участнику.
     * Если такого участника нет - вернет 0. Если участник есть, но для него не заданы
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
                    throw new Exception("SP для участника " . $member->getPlainName() .
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
