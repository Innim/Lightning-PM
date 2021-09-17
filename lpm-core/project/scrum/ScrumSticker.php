<?php
/**
 * Стикер на Scrum доске.
 * Связан с задачей, каждому стикеру соответствует 1 задача
 */
class ScrumSticker extends LPMBaseObject
{
    public static function loadList($projectId)
    {
        $db = self::getDB();

        $states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS,
            ScrumStickerState::TESTING, ScrumStickerState::DONE]);

        $sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`added` `s_added`, `s`.`state` `s_state`, 
			   'with_issue', `i`.*, `p`.`uid` as `projectUID`
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
    INNER JOIN `%3\$s` `p` ON `i`.`projectId` = `p`.`id`
     	 WHERE `i`.`projectId` = ${projectId} AND `i`.`deleted` = 0 
     	   AND `s`.`state` IN (${states})
 	  ORDER BY `i`.`priority` DESC
SQL;

        return StreamObject::loadObjList(
            $db,
            [$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES, LPMTables::PROJECTS],
            __CLASS__
        );
    }
    
    public static function loadAllStickersList($userId)
    {
        $db = self::getDB();
        $states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS,
            ScrumStickerState::TESTING, ScrumStickerState::DONE]);
        $instanceType = implode(',', [LPMInstanceTypes::ISSUE, LPMInstanceTypes::ISSUE_FOR_TEST]);
        
        $sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`added` `s_added`, `s`.`state` `s_state`,
			   'with_issue', `i`.*, `p`.`name` `projectName`, `p`.`uid` `projectUID`
		FROM `%1\$s` `s`
            INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
            INNER JOIN `%3\$s` `m` ON `s`.`issueId` = `m`.`instanceId`
            INNER JOIN `%4\$s` `p` ON `i`.`projectId` = `p`.`id`
     	WHERE `i`.`deleted` = 0
     	    AND `s`.`state` IN (${states})
     	    AND `m`.`userId` = ${userId}
     	    AND `m`.`instanceType` IN (${instanceType})
     	    AND `p`.`isArchive` = 0
 	    ORDER BY `i`.`priority` DESC
SQL;
        
        return StreamObject::loadObjList(
            $db,
            [$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES, LPMTables::MEMBERS, LPMTables::PROJECTS],
            __CLASS__
        );
    }
    
    /**
     * Загружает стикер по идентификатору задачи
     * @param  int $issueId
     * @return ScrumSticker
     */
    public static function load($issueId)
    {
        $db = self::getDB();

        $sql = <<<SQL
		SELECT `s`.`issueId` `s_issueId`, `s`.`state` `s_state`, 
			   'with_issue', `i`.*, `p`.`uid` as `projectUID`
		  FROM `%1\$s` `s` 
    INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id` 
    INNER JOIN `%3\$s` `p` ON `i`.`projectId` = `p`.`id`
     	 WHERE `s`.`issueId` = ${issueId} AND `i`.`deleted` = 0 
SQL;

        $list = StreamObject::loadObjList(
            $db,
            [$sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES, LPMTables::PROJECTS],
            __CLASS__
        );

        return empty($list) ? null : $list[0];
    }

    /**
     * Удаляет все стикеры для указанного проекта.
     * @param  int $projectId Идентификатор проекта,
     * @return
     */
    public static function removeStickersForProject($projectId, $excludedStates = null)
    {
        $statesWhere = '';
        if (!empty($excludedStates)) {
            $excludedStatesStr = implode(',', $excludedStates);
            $statesWhere = <<<SQL
AND `s`.`state` NOT IN (${excludedStatesStr})
SQL;
        }

        $db = self::getDB();
        $sql = <<<SQL
    		DELETE `s` FROM `%1\$s` `s`
    		     INNER JOIN `%2\$s` `i` ON `i`.`id` = `s`.`issueId`
    		 WHERE `i`.`projectId` = ${projectId} 
                   ${statesWhere}
SQL;

        return $db->queryt($sql, LPMTables::SCRUM_STICKER, LPMTables::ISSUES);
    }

    public static function putStickerOnBoard(Issue $issue)
    {
        switch ($issue->status) {
            case Issue::STATUS_IN_WORK: $state = ScrumStickerState::TODO; break;
            case Issue::STATUS_WAIT: $state = ScrumStickerState::TESTING; break;
            case Issue::STATUS_COMPLETED: $state = ScrumStickerState::DONE; break;
            default: $state = ScrumStickerState::BACKLOG;
        }
        $issueId = $issue->id;
        $added = DateTimeUtils::mysqlDate();

        $db = self::getDB();
        $isActiveState = ScrumStickerState::isActiveState($state);
        if (!$isActiveState) {
            $sql = "DELETE FROM `%s` WHERE `issueId` = ${issueId}";
        } else {
            $sql = <<<SQL
		INSERT INTO `%s` (`issueId`, `state`, `added`)
				  VALUES (${issueId}, ${state}, '${added}')
  			ON DUPLICATE KEY UPDATE `state` = ${state}
SQL;
        }

        return $db->queryt($sql, LPMTables::SCRUM_STICKER);
    }

    public static function updateStickerState($issueId, $state)
    {
        $isActiveState = ScrumStickerState::isActiveState($state);
        if (!$isActiveState) {
            $sql = "DELETE FROM `%s` WHERE `issueId` = ${issueId}";
        } else {
            $sql = <<<SQL
        		UPDATE `%s` SET `state` = ${state} WHERE `issueId` = ${issueId}
SQL;
        }
        
        $db = self::getDB();
        return $db->queryt($sql, LPMTables::SCRUM_STICKER);
    }

    /**
     * Идентификатор задачи
     * @var int
     */
    public $issueId;
    /**
     * Текущее состояние - в какой колонке доски находится стикер
     * @var int
     * @see ScrumStickerState
     */
    public $state;
    /**
     * Дата создания стикера
     * (т.е. добавления задачи на доску).
     * @var
     */
    public $added;

    // Issue
    private $_issue;

    public function __construct($id = 0)
    {
        parent::__construct();

        $this->_typeConverter->addFloatVars('issueId');
        $this->_typeConverter->addIntVars('state');
        $this->addDateTimeFields('added');
    }

    /**
     * Возвращает отображаемое имя стикера
     * @return String
     */
    public function getName()
    {
        return $this->_issue === null ?
            'Задача #' . $this->issueId : $this->_issue->getName();
    }

    /**
     * Возвращает объект задачи. Если не выставлен - будет загружен
     * @return Issue
     */
    public function getIssue()
    {
        if ($this->_issue === null) {
            $this->_issue = Issue::load($this->issueId);
        }
        return $this->_issue;
    }

    public function isOnBoard()
    {
        // return $this->state !== ScrumStickerState::BACKLOG;
        return ScrumStickerState::isActiveState($this->state);
    }

    /**
     * Стикер находится в колонке TO DO
     * @return boolean
     */
    public function isTodo()
    {
        return $this->state === ScrumStickerState::TODO;
    }

    /**
     * Стикер находится в колонке В работе
     * @return boolean
     */
    public function isInProgress()
    {
        return $this->state === ScrumStickerState::IN_PROGRESS;
    }

    /**
     * Стикер находится в колонке Тестируется
     * @return boolean
     */
    public function isTesting()
    {
        return $this->state === ScrumStickerState::TESTING;
    }

    /**
     * Стикер находится в колонке Выполнено
     * @return boolean
     */
    public function isDone()
    {
        return $this->state === ScrumStickerState::DONE;
    }

    /*public function nextState() {
        return ScrumStickerState::getNextState($this->state);
    }

    public function prevState() {
        return ScrumStickerState::getPrevState($this->state);
    }*/

    public function loadStream($raw)
    {
        $data = [];
        foreach ($raw as $key => $value) {
            if (strpos($key, 's_') === 0) {
                $data[mb_substr($key, 2)] = $value;
            }
        }

        parent::loadStream(empty($data) ? $raw : $data);

        if (isset($raw['with_issue'])) {
            if ($this->_issue === null) {
                $this->_issue = new Issue($this->issueId);
            }
            $this->_issue->loadStream($raw);
        }
    }
}
