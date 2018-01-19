<?php
/**
 * Архив scrum досок.
 */
class ScrumStickerSnapshot extends LPMBaseObject
{
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
	 * Создает snapshot по текущему состоянию доски для переданного проекта.
     * @param int $projectId
     * @param $userId
     * @throws Exception
     */
	public static function createSnapshot($projectId, $userId) {
		// получаем список всех стикеров на текущей доске
		$stickers = ScrumSticker::loadList($projectId);

		$pid = $projectId;
		$created = DateTimeUtils::mysqlDate();
		$creatorId = $userId;
        $db = self::getDB();

        try {
            // получаем идентификатор снепшота в проекте
            $idInProject = self::getLastIssueId($projectId);

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
                INSERT INTO `%s` (`sid`, `issue_uid`, `issue_pid`, `issue_name`, `issue_state`, `issue_sp`, `issue_priority`)
                VALUES ('${sid}', ?, ?, ?, ?, ?, ?)
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
                $issuePriority = $issue->priority;

                $prepare->bind_param('ddsisi', $issueUid, $issuePid, $issueName, $issueState, $issueSP, $issuePriority);

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
        }
        catch (Exception $ex) {
            // что-то пошло не так -> отменяем все изменения
            $db->rollback();

            throw $ex;
        }
	}

	/**
	 * Идентификатор snapshot-а.
	 * @var int
	 */
	public $id;
    /**
     * Порядковый номер снепшота по проект.
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

    public function getCreateDate() {
        return self::getDateStr($this->created);
    }

    public function getCreatorShortName() {
        if (!isset($this->_creator))
            $this->_creator = User::load($this->creatorId);

        return $this->_creator->getShortName();
    }

    public function getStickers() {
        if (!isset($this->_stickers))
            $this->_stickers = ScrumStickerSnapshotItem::loadList($this->id);
        return $this->_stickers;
    }

    public function getNumStickers() {
        return count($this->getStickers());
    }

    public function getNumSp() {
        $count = 0;
        $stickers = $this->getStickers();
        foreach ($stickers as $sticker) {
            $count += $sticker->issue_sp;
        }

        return $count;
    }

    /**
     * Возвращает номер последнего задания в проекте.
     * @param int $projectId идентификатор проекта, для которого создается снепшот.
     * @return int номер последнего задания в проекте.
     * @throws Exception В случае, если произошла ошибка при запросе к БД.
     */
    private static function getLastIssueId($projectId)
    {
        $db = self::getDB();
        $sql = "SELECT MAX(`idInProject`) AS maxID FROM `%s` " .
            "WHERE `pid` = '" . $projectId . "'";
        if (!$query = $db->queryt($sql, LPMTables::SCRUM_SNAPSHOT_LIST)){
            throw new Exception("Ошибка доступа к базе при получении идентификатора снепшота в проекте!");
        }

        if ($query->num_rows == 0) {
            return 1;
        } else {
            $result = $query->fetch_assoc();
            return $result['maxID'] + 1;
        }
    }
}