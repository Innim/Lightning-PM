<?php
/**
 * Стикер на Scrum доске.
 * Связан с задачей, каждому стикеру соответствует 1 задача
 */
class ScrumSticker extends LPMBaseObject
{
    /**
     * Загружаем список стикеров на доске для указанного проекта.
     */
    public static function loadBoard($projectId)
    {
        $states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS,
            ScrumStickerState::TESTING, ScrumStickerState::DONE]);

        $where = <<<SQL
`i`.`projectId` = ${projectId} AND `s`.`state` IN (${states})
SQL;

        return self::loadList($where);
    }

    protected static function loadList($where, $extraSelect = '', $extraTables = null, $extraTablesOn = null, $orderBy = null)
    {
        $db = self::getDB();

        $selectSql = empty($extraSelect) ? '' : ', ' . $extraSelect;
        $whereSql = empty($where) ? '' : ' AND (' . $where . ')';
        $orderBySql = empty($orderBy) ? '`i`.`priority` DESC' : $orderBy;


        $tables = [
            LPMTables::SCRUM_STICKER,
            LPMTables::ISSUES,
            LPMTables::PROJECTS
        ];
        $innerJoinSql = '';

        if (!empty($extraTables)) {
            if (empty($extraTablesOn)) {
                throw new \GMFramework\ProviderLoadException('Необходимо задать условие присоединения таблиц');
            }

            if (count($extraTables) != count($extraTablesOn)) {
                throw new \GMFramework\ProviderLoadException('Необходимо задать условие присоединения для всех таблиц');
            }

            $t = count($tables);
            $i = 0;
            foreach ($extraTables as $alias => $table) {
                $t++;

                $onSql = $extraTablesOn[$i++];
                $innerJoinSql .= <<<SQL
     INNER JOIN `%{$t}\$s` `$alias` ON $onSql
SQL;
                $tables[] = $table;
            }
        }

        $sql = <<<SQL
SELECT DISTINCT `s`.`issueId` `s_issueId`, `s`.`added` `s_added`, `s`.`state` `s_state`
                $selectSql
		   FROM `%1\$s` `s` 
     INNER JOIN `%2\$s` `i` ON `s`.`issueId` = `i`.`id`
     INNER JOIN `%3\$s` `p` ON `i`.`projectId` = `p`.`id`
                $innerJoinSql
     	  WHERE `i`.`deleted` = 0 
                $whereSql
 	   ORDER BY $orderBySql
SQL;


        $list = StreamObject::loadObjList(
            $db,
            array_merge([$sql], $tables),
            __CLASS__
        );

        // Чтобы получить полные данные задачи - лучше загрузим их отдельным запросом
        $issueIds = [];
        $stickerById = [];

        foreach ($list as $sticker) {
            $issueIds[] = $sticker->issueId;
            $stickerById[$sticker->issueId] = $sticker;
        }
        
        $issues = Issue::loadListByIds($issueIds);

        foreach ($issues as $issue) {
            $stickerById[$issue->id]->_issue = $issue;
        }
        
        return $list;
    }
    
    public static function loadAllStickersList($userId)
    {
        $states = implode(',', [ScrumStickerState::TODO, ScrumStickerState::IN_PROGRESS,
            ScrumStickerState::TESTING, ScrumStickerState::DONE]);
        $instanceType = implode(',', [LPMInstanceTypes::ISSUE, LPMInstanceTypes::ISSUE_FOR_TEST]);
        
        $where = <<<SQL
`s`.`state` IN (${states}) AND `m`.`userId` = ${userId} AND `p`.`isArchive` = 0
SQL;
        return self::loadList(
            $where,
            '',
            ['m' => LPMTables::MEMBERS],
            ["`s`.`issueId` = `m`.`instanceId` AND `m`.`instanceType` IN (${instanceType})"]
        );
    }
    
    /**
     * Загружает стикер по идентификатору задачи
     * @param  int $issueId
     * @return ScrumSticker
     */
    public static function load($issueId)
    {
        return StreamObject::singleLoad($issueId, __CLASS__, '', 's`.`issueId');
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

    public static function splitByStates($list)
    {
        $stickersByState = [];
        foreach ($list as $sticker) {
            if (!isset($stickersByState[$sticker->state])) {
                $stickersByState[$sticker->state] = [$sticker];
            } else {
                $stickersByState[$sticker->state][] = $sticker;
            }
        }

        return $stickersByState;
    }

    public static function sortStickersForBoard($state, &$list)
    {
        switch ($state) {
            case ScrumStickerState::TESTING: {
                // В тесте мы показываем вверху те, что уже прошли тест
                // А за ними те, что требуют правок
                usort($list, function (ScrumSticker $a, ScrumSticker $b) {
                    $aIssue = $a->getIssue();
                    $bIssue = $b->getIssue();
                    if ($aIssue->isPassTest != $bIssue->isPassTest) {
                        return $aIssue->isPassTest ? -1 : 1;
                    }

                    if ($aIssue->isChangesRequested != $bIssue->isChangesRequested) {
                        return $aIssue->isChangesRequested ? -1 : 1;
                    }

                    return $bIssue->priority - $aIssue->priority;
                });
                break;
            }
        }
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

        return parent::loadStream(empty($data) ? $raw : $data);
    }
}
