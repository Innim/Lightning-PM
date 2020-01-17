<?php
/**
 * Архив scrum досок.
 */
class ScrumStickerSnapshot extends LPMBaseObject {
    /**
     * Загружает список снепшотов по идентификатору проекта (вначале новые).
     * @param int $projectId
     * @return ScrumStickerSnapshot[]
     * @throws DBException
     * @throws Exception
     */
	public static function loadList($projectId) {
		$db = self::getDB();
        $projectId = (int) $projectId;

        // TODO: нужно ли ограничить как-то?
        // Выбираем (пока все) записи по переданному проекту
        $sql = <<<SQL
        SELECT * FROM `%1\$s` WHERE `%1\$s`.`pid` = '${projectId}'
        ORDER BY `%1\$s`.`created` DESC
SQL;

		return StreamObject::loadObjList($db, [$sql, LPMTables::SCRUM_SNAPSHOT_LIST], __CLASS__);
	}

    /**
     * Загружает список снепшотов по идентификатору проекта (вначале новые).
     * @param int $createFrom
     * @param int $createdTo
     * @return ScrumStickerSnapshot[]
     * @throws DBException
     * @throws Exception
     */
    public static function loadListByDate($createFrom, $createdTo) {
        $db = self::getDB();

        $dateWhere = self::whereBetween('created', $createFrom, $createdTo);
        $sql = <<<SQL
        SELECT * FROM `%1\$s` WHERE ${dateWhere}
        ORDER BY `%1\$s`.`created` DESC, `%1\$s`.`pid` DESC
SQL;

// echo $db->sprintft([$sql, LPMTables::SCRUM_SNAPSHOT_LIST]);
        return StreamObject::loadObjList($db, [$sql, LPMTables::SCRUM_SNAPSHOT_LIST], __CLASS__);
    }

    private static function whereBetween($field, $dateFrom, $dateTo) {
        $from = DateTimeUtils::mysqlDate($dateFrom, false) . ' 00:00:00';
        $to = DateTimeUtils::mysqlDate($dateTo, false) . ' 23:59:59';
        return '`' . $field . '` BETWEEN STR_TO_DATE(\'' . $from . '\', \'%%Y-%%m-%%d %%H:%%i:%%s\') ' .
            'AND STR_TO_DATE(\'' . $to . '\', \'%%Y-%%m-%%d %%H:%%i:%%s\')';
    }

    /**
     * Загружает снепшот по идентификатору проекта и идентификатору снепшота в проекте.
     * @param int $projectId
     * @param int $idInProject
     * @return ScrumStickerSnapshot|null
     * @throws DBException
     * @throws Exception
     */
    public static function loadSnapshot($projectId, $idInProject) {
        $db = self::getDB();
        $projectId = (int) $projectId;
        $idInProject = (int) $idInProject;

        $sql = <<<SQL
        SELECT * FROM `%1\$s` WHERE `%1\$s`.`pid` = '${projectId}'
           AND `%1\$s`.`idInProject` = '${idInProject}'
SQL;

        $list = StreamObject::loadObjList($db, [$sql, LPMTables::SCRUM_SNAPSHOT_LIST], __CLASS__);
        return empty($list) ? null : $list[0];
    }

	/**
	 * Создает snapshot по текущему состоянию доски для переданного проекта.
     * @param int $projectId
     * @param $userId
     * @throws Exception
     */
	public static function createSnapshot($projectId, $userId) {
		// получаем список всех стикеров на текущей доске
		$stickers = ScrumSticker::loadList($projectId);

        // Проверяем, что для всех задач в тесте/готово указаны все SP по участникам
        $membersSpByIssueId = [];
        foreach ($stickers as $sticker) {
            if ($sticker->isDone() || $sticker->isTesting()) {
                $issue = $sticker->getIssue();
                $members = $issue->getMembers();
                if (count($members) > 1) {
                    $membersSp = [];
                    $totalSp = 0;
                    foreach ($members as $member) {
                        $totalSp += $member->sp;
                        $membersSp[] = (object)[
                            'userId' => $member->userId,
                            'sp' => $member->sp
                        ];
                    }
                    if ($totalSp != $issue->hours) {
                        throw new Exception("Не заполнены SP по исполнителям для задачи \"" .
                            $issue->name . "\"");
                    }
                    $membersSpByIssueId[$issue->id] = $membersSp;
                }
            }
        }

        // Готовимся делать снимок
		$pid = $projectId;
		$created = DateTimeUtils::mysqlDate();
		$creatorId = $userId;
        $db = self::getDB();

        try {
            // получаем идентификатор снепшота в проекте
            $idInProject = self::getLastSnapshotId($projectId) + 1;

            // начинаем транзакцию
            $db->begin_transaction();

            // запись о новом снепшоте
            $sql = <<<SQL
                INSERT INTO `%s` (`idInProject`, `pid`, `creatorId`, `created`)
                VALUES ('${idInProject}', '${pid}', '${creatorId}', '${created}')
SQL;

            // если что-то пошло не так
            if (!$db->queryt($sql, LPMTables::SCRUM_SNAPSHOT_LIST))
                throw new Exception("Ошибка при сохранении нового снепшота");

            $sid = $db->insert_id;

            // добавляем всю необходимую информацию по снепшоте
            $sql = <<<SQL
                INSERT INTO `%s` (`sid`, `issue_uid`, `issue_pid`, `issue_name`,
                    `issue_state`, `issue_sp`, `issue_members_sp`, `issue_priority`)
                VALUES ('${sid}', ?, ?, ?, ?, ?, ?, ?)
SQL;

            // подготавливаем запрос для вставки данных о стикерах снепшота
            if (!$prepare = $db->preparet($sql, LPMTables::SCRUM_SNAPSHOT))
                throw new Exception("Ошибка при подготовке запроса.");

            $added = false;

            foreach ($stickers as $sticker) {
                /* @var $sticker ScrumSticker */

                $issue = $sticker->getIssue();

                $issueUid = $sticker->issueId;
                $issuePid = $issue->idInProject;
                $issueName = $sticker->getName();
                $issueState = $sticker->state;
                $issueSP = $issue->hours;
                $issueMembersSP = json_encode($membersSpByIssueId[$sticker->issueId]);
                $issuePriority = $issue->priority;

                $prepare->bind_param('ddsissi', $issueUid, $issuePid, $issueName, $issueState,
                    $issueSP, $issueMembersSP, $issuePriority);

                if (!$prepare->execute())
                    throw new Exception("Ошибка при вставке данных стикера.");

                $members = $issue->getMemberIds();

                $insertId = $prepare->insert_id;

                if (count($members) > 0) {
                    if (!Member::saveMembers(LPMInstanceTypes::SNAPSHOT_ISSUE_MEMBERS, $insertId, $issue->getMemberIds()))
                        throw new Exception("Ошибка при сохранении участников.");
                }

                $testers = $issue->getTesterIds();

                if (count($testers) > 0) {
                    if (!Member::saveMembers(LPMInstanceTypes::SNAPSHOT_ISSUE_FOR_TEST, $insertId, $testers))
                        throw new Exception("Ошибка при сохранении тестеров.");
                }

                $added = true;
            }

            // запрос больше не нужен
            $prepare->close();

            // вроде бы все ок -> завершае транзакцию
            if ($added) {
                $db->commit();
            } else {
                // отменяем, т.к. на доске нет стикеров
                $db->rollback();
            }
        } catch (Exception $ex) {
            // что-то пошло не так -> отменяем все изменения
            $db->rollback();

            throw $ex;
        }
	}

    /**
     * Возвращает номер последнего снепшота в архиве.
     * @param int $projectId идентификатор проекта, для которого создается снепшот.
     * @return int Номер последнего снепшота в проекте или 0 - если еще не было снимков.
     * @throws Exception В случае, если произошла ошибка при запросе к БД.
     */
    public static function getLastSnapshotId($projectId) {
        $db = self::getDB();
        $sql = "SELECT MAX(`idInProject`) AS maxID FROM `%s` " .
            "WHERE `pid` = '" . $projectId . "'";
        if (!$query = $db->queryt($sql, LPMTables::SCRUM_SNAPSHOT_LIST)){
            throw new Exception("Ошибка доступа к базе при получении идентификатора снепшота в проекте!");
        }

        if ($query->num_rows == 0) {
            return 0;
        } else {
            $result = $query->fetch_assoc();
            return (int) $result['maxID'];
        }
    }

	/**
	 * Идентификатор snapshot-а.
	 * @var int
	 */
	public $id;
    /**
     * Порядковый номер снепшота по проекту.
     * @var int
     */
    public $idInProject = 0;
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

	private $_creator;
	private $_stickers;
    private $_members;

	function __construct($id = 0) {
		parent::__construct();

		$this->id = $id;

		$this->_typeConverter->addFloatVars('id', 'idInProject', 'pid', 'creatorId');
		$this->addDateTimeFields('created');
	}

    /**
     * Возвращает порядковый номер снепшота по проекту.
     * @return int
     */
    public function getIdInProject(){
        return $this->idInProject;
    }

    /**
     * Путь до просмотра снепшота
     * @return string
     */
	public function getSnapshotUrl() {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_SCRUM_BOARD_SNAPSHOT, $this->idInProject);
    }

    /**
     * Путь до списка снепшотов
     */
    public function getUrl() {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_SCRUM_BOARD_SNAPSHOT);
    }

    /**
     * URL статистики спринта.
     */
    public function getStatUrl() {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_SPRINT_STAT, $this->idInProject);
    }

    public function getCreateDate() {
        return self::getDateStr($this->created);
    }

    public function getCreatorShortName() {
        if ($this->_creator === null)
            $this->_creator = User::load($this->creatorId);

        return $this->_creator->getShortName();
    }

    public function getStickers() {
        if ($this->_stickers === null)
            $this->_stickers = ScrumStickerSnapshotItem::loadList($this->id);
        return $this->_stickers;
    }

    public function getNumStickers() {
        return count($this->getStickers());
    }

    /**
     * Возвращает список участников спринта.
     * Участниками считаются все, кто назначен в качестве исполнителя хотя бы одной задаче.
     * @return Member[]
     */
    public function getMembers() {
        if ($this->_members === null) {
            $this->_members = [];
            $membersMap = [];
            $stickers = $this->getStickers();
            foreach ($stickers as $sticker) {
                $issueMembers = $sticker->getMembers();
                foreach ($issueMembers as $member) {
                    if (!isset($membersMap[$member->userId])) {
                        $membersMap[$member->userId] = $member;
                        $this->_members[] = $member;
                    }
                }
            }
        }
        return $this->_members;
    }

    /**
     * Проверяет, участвовал ли указанный пользователь в спринте
     * (считается что участвовал, если на него была поставлена хотья бы одна задача)
     * @return bool
     */
    public function hasMembers($userId) {
        $list = $this->getMembers();
        foreach ($list as $member) {
            if ($member->userId == $userId)
                return true;
        }

        return false;
    }

    public function getNumSp() {
        return $this->countSp();
    }

    /**
     * Возвращает количество сделанны SP спринта.
     * Сделанными считаются задачи в Готово и Тестировании.
     * @return int
     */
    public function getDoneSp() {
        return $this->countSp(function ($sticker) {
            return ($sticker->issue_state == ScrumStickerState::TESTING || 
                    $sticker->issue_state == ScrumStickerState::DONE);
        });
    }

    public function getMemberSP($userId, $stickerState) {
        return $this->countMemberSp($userId, function ($sticker) use ($stickerState) {
            return $sticker->issue_state == $stickerState;
        });
    }

    public function getMemberDoneSP($userId) {
        return $this->countMemberSp($userId, function ($sticker) {
            return ($sticker->issue_state == ScrumStickerState::TESTING || 
                    $sticker->issue_state == ScrumStickerState::DONE);
        });
    }

    private function countSp($filterCallback = null, $getSpCallback = null) {
        $count = 0;
        $stickers = $this->getStickers();
        foreach ($stickers as $sticker) {
            if ($filterCallback == null || $filterCallback($sticker))
                $count += $getSpCallback == null ? $sticker->issue_sp : $getSpCallback($sticker);
        }

        return $count;
    }

    private function countMemberSp($userId, $filterCallback = null) {
        return $this->countSp(function ($sticker) use ($userId, $filterCallback) {
            return $sticker->isMember($userId) &&
                ($filterCallback == null || $filterCallback($sticker));
        }, function ($sticker) use ($userId) {
            return $sticker->getSpByMember($userId);
        });
    }
}