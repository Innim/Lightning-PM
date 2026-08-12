<?php
class Issue extends MembersInstance
{
    private static $_listByProjects = array();
    private static $_listByUser = array();
    
    /**
     * Выборка происходит из таблиц:
     * - задач - i
     * - пользователей - u
     * - проектов - p
     * - счетчиков задачи - cnt
     * - стикер на доске - st.
     * @param  string $where       Условие выборки.
     * @param  string $extraSelect Дополнительная строка полей для выборки.
     * @param  array  $extraTables Ассоциативный массив дополнительных таблиц для выборки
     *                             [алиас => таблица].
     * @return array<Issue> Массив загруженных задач.
     */
    protected static function loadList($where, $extraSelect = '', $extraTables = null, $orderBy = null)
    {
        $instanceType = LPMInstanceTypes::ISSUE;

        $passTestType = IssueCommentType::PASS_TEST;
        $requestChangesType = IssueCommentType::REQUEST_CHANGES;
        $mergeRequestType = IssueCommentType::MERGE_REQUEST;

        $statusWait = Issue::STATUS_WAIT;
        $statusCompleted = Issue::STATUS_COMPLETED;
        // Даты последней активности нужны только для сортировки задач в тесте,
        // поэтому считаются лишь для них: в больших проектах задач в тесте единицы,
        // а подзапрос по комментариям выполняется для каждой строки выборки.
        $sql = <<<SQL
SELECT `i`.*, 'with_sticker', `st`.`state` `s_state`, 
    IF(`i`.`status` = $statusCompleted, `i`.`completedDate`, NULL) AS `realCompleted`, 
    `u`.*, `cnt`.*, `p`.`uid` as `projectUID`, `p`.`name` AS `projectName`,
    (SELECT `icm`.`type` 
       FROM `%6\$s` `cm`
 INNER JOIN `%7\$s` `icm` 
         ON `icm`.`commentId` = `cm`.`id`
      WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0 
        AND `icm`.`type` IN ('$passTestType', '$requestChangesType', '$mergeRequestType')
   ORDER BY `date` DESC
      LIMIT 1) AS `t_testState`,
    IF(`i`.`status` = $statusWait,
      (SELECT MAX(`cm`.`date`)
         FROM `%6\$s` `cm`
        WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0
      ), NULL) AS `t_lastCommentDate`,
    IF(`i`.`status` = $statusWait,
      (SELECT MAX(`cm`.`date`)
         FROM `%6\$s` `cm`
   INNER JOIN `%7\$s` `icm`
           ON `icm`.`commentId` = `cm`.`id`
        WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0
          AND `icm`.`type` = '$requestChangesType'
      ), NULL) AS `t_lastBugDate`
SQL;
        if (!empty($extraSelect)) {
            $sql .= ', ' . $extraSelect;
        }

        $sql .= ' FROM `%2$s` AS `u`, `%4$s` AS `p`';
        $args = array(
            LPMTables::ISSUES,
            LPMTables::USERS,
            LPMTables::ISSUE_COUNTERS,
            LPMTables::PROJECTS,
            LPMTables::SCRUM_STICKER,
            LPMTables::COMMENTS,
            LPMTables::ISSUE_COMMENT
        );

        if (!empty($extraTables)) {
            $i = count($args);
            foreach ($extraTables as $alias => $table) {
                $sql .= ', `%' . (++$i) . '$s` AS `' . $alias . '`';
                $args[] = $table;
            }
        }

        $sql .= <<<SQL
		, `%1\$s` AS `i` 
		LEFT JOIN `%3\$s` AS `cnt` ON `i`.`id` = `cnt`.`issueId` 
		LEFT JOIN `%5\$s` AS `st` ON `i`.`id` = `st`.`issueId` 
			WHERE `i`.`projectId` = `p`.`id` 
			  AND `i`.`deleted` = '0'
SQL;

        if ($where != '') {
            $sql  .= " AND " . $where;
        }

        if (empty($orderBy)) {
            $statusesOrder = implode(', ', [Issue::STATUS_WAIT, Issue::STATUS_IN_WORK, Issue::STATUS_COMPLETED]);
            $testStatesOrderDesc = "'" . implode("', '", [$requestChangesType, $passTestType]) . "'";
            // Задачи в тесте внутри своей группы (прошла тест / есть баг / без отметки)
            // сортируются по давности последней активности - наверх те, которыми дольше
            // всего не занимались. Для задач с багом активность - это дата последнего бага.
            // Если комментариев нет вообще - берем дату изменения самой задачи
            // (в том числе перевода в тест), чтобы только что отправленная в тест
            // старая задача не оказалась наверху как самая застоявшаяся.
            $orderBy = <<<SQL
            FIELD(`i`.`status`, $statusesOrder),
            `realCompleted` DESC,
            IF(`i`.`status` = $statusWait, FIELD(`t_testState`, $testStatesOrderDesc), 0) DESC,
            IF(`i`.`status` = $statusWait,
               COALESCE(IF(`t_testState` = '$requestChangesType', `t_lastBugDate`, `t_lastCommentDate`),
                        GREATEST(`i`.`createDate`, `i`.`modifiedDate`)),
               NULL) ASC,
            `i`.`priority` DESC,
            `i`.`completeDate` ASC, `id` ASC
            SQL;
        }

        $sql .= " AND `i`.`authorId` = `u`.`userId` ORDER BY " . $orderBy;

        array_unshift($args, $sql);
        
        return StreamObject::loadObjList(self::getDB(), $args, __CLASS__);
    }

    /**
     * Выборка происходит из таблиц:
     * - задач - i
     * - пользователей - u
     * - проектов - p
     * - счетчиков задачи - cnt
     * - стикер на доске - st.
     * @param  string $where       Условие выборки.
     * @param  string $extraSelect Дополнительная строка полей для выборки.
     * @param  array  $joinTables  Ассоциативный массив дополнительных таблиц для выборки
     *                             [алиас => [таблица, условие ON]].
     * @return array<Issue> Массив загруженных задач.
     */
    protected static function loadListV2($where, $extraSelect = '', $joinTables = null, $orderBy = null)
    {
        $instanceType = LPMInstanceTypes::ISSUE;

        $passTestType = IssueCommentType::PASS_TEST;
        $requestChangesType = IssueCommentType::REQUEST_CHANGES;
        $mergeRequestType = IssueCommentType::MERGE_REQUEST;

        $statusWait = Issue::STATUS_WAIT;
        $statusCompleted = Issue::STATUS_COMPLETED;
        // Даты последней активности нужны только для сортировки задач в тесте,
        // поэтому считаются лишь для них: в больших проектах задач в тесте единицы,
        // а подзапрос по комментариям выполняется для каждой строки выборки.
        $sql = <<<SQL
SELECT `i`.*, 'with_sticker', `st`.`state` `s_state`, 
    IF(`i`.`status` = $statusCompleted, `i`.`completedDate`, NULL) AS `realCompleted`, 
    `u`.*, `cnt`.*, `p`.`uid` as `projectUID`, `p`.`name` AS `projectName`,
    (SELECT `icm`.`type` 
       FROM `%6\$s` `cm`
 INNER JOIN `%7\$s` `icm` 
         ON `icm`.`commentId` = `cm`.`id`
      WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0 
        AND `icm`.`type` IN ('$passTestType', '$requestChangesType', '$mergeRequestType')
   ORDER BY `date` DESC
      LIMIT 1) AS `t_testState`,
    IF(`i`.`status` = $statusWait,
      (SELECT MAX(`cm`.`date`)
         FROM `%6\$s` `cm`
        WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0
      ), NULL) AS `t_lastCommentDate`,
    IF(`i`.`status` = $statusWait,
      (SELECT MAX(`cm`.`date`)
         FROM `%6\$s` `cm`
   INNER JOIN `%7\$s` `icm`
           ON `icm`.`commentId` = `cm`.`id`
        WHERE `cm`.`instanceType` = '$instanceType' AND `cm`.`instanceId` = `i`.`id` AND `cm`.`deleted` = 0
          AND `icm`.`type` = '$requestChangesType'
      ), NULL) AS `t_lastBugDate`
SQL;
        if (!empty($extraSelect)) {
            $sql .= ', ' . $extraSelect;
        }

        $sql .= <<<SQL
       FROM `%1\$s` AS `i`
 INNER JOIN `%2\$s` AS `u` ON `i`.`authorId` = `u`.`userId`
 INNER JOIN `%4\$s` AS `p` ON `i`.`projectId` = `p`.`id`
SQL;

        $args = array(
            LPMTables::ISSUES,
            LPMTables::USERS,
            LPMTables::ISSUE_COUNTERS,
            LPMTables::PROJECTS,
            LPMTables::SCRUM_STICKER,
            LPMTables::COMMENTS,
            LPMTables::ISSUE_COMMENT
        );

        if (!empty($joinTables)) {
            $i = count($args);
            foreach ($joinTables as $alias => $data) {
                $table = $data[0];
                $onCond = $data[1];
                $sql .= ' INNER JOIN `%' . (++$i) . '$s` AS `' . $alias . '` ON ' . $onCond;
                $args[] = $table;
            }
        }

        $sql .= <<<SQL
  LEFT JOIN `%3\$s` AS `cnt` ON `i`.`id` = `cnt`.`issueId` 
  LEFT JOIN `%5\$s` AS `st` ON `i`.`id` = `st`.`issueId` 
      WHERE `i`.`deleted` = '0'
SQL;

        if ($where != '') {
            $sql  .= " AND " . $where;
        }

        if (empty($orderBy)) {
            $statusesOrder = implode(', ', [Issue::STATUS_WAIT, Issue::STATUS_IN_WORK, Issue::STATUS_COMPLETED]);
            $testStatesOrderDesc = "'" . implode("', '", [$requestChangesType, $passTestType]) . "'";
            // Задачи в тесте внутри своей группы (прошла тест / есть баг / без отметки)
            // сортируются по давности последней активности - наверх те, которыми дольше
            // всего не занимались. Для задач с багом активность - это дата последнего бага.
            // Если комментариев нет вообще - берем дату изменения самой задачи
            // (в том числе перевода в тест), чтобы только что отправленная в тест
            // старая задача не оказалась наверху как самая застоявшаяся.
            $orderBy = <<<SQL
            FIELD(`i`.`status`, $statusesOrder),
            `realCompleted` DESC,
            IF(`i`.`status` = $statusWait, FIELD(`t_testState`, $testStatesOrderDesc), 0) DESC,
            IF(`i`.`status` = $statusWait,
               COALESCE(IF(`t_testState` = '$requestChangesType', `t_lastBugDate`, `t_lastCommentDate`),
                        GREATEST(`i`.`createDate`, `i`.`modifiedDate`)),
               NULL) ASC,
            `i`.`priority` DESC,
            `i`.`completeDate` ASC, `id` ASC
            SQL;
        }

        $sql .= " ORDER BY " . $orderBy;

        array_unshift($args, $sql);
        
        return StreamObject::loadObjList(self::getDB(), $args, __CLASS__);
    }

    /**
     * Получает список задач по проекту. Загруженный список кэшируется по проектам,
     * если список еще не был получен - он будет загружен из базы.
     * @param  integer $projectId Идентификатор проекта,
     * @param  integer $type      Тип задач.
     * @return array<Issue> Массив задач.
     */
    public static function getListByProject($projectId, $type = -1)
    {
        if (!isset(self::$_listByProjects[$projectId])) {
            if (LightningEngine::getInstance()->isAuth()) {
                $where = "`i`.`projectId` = '" . $projectId . "'";
                if ($type != -1) {
                    $where .= "AND `i`.`type` = '" . $type . "'";
                }
                    
                self::$_listByProjects[$projectId] = self::loadList($where);
            } else {
                self::$_listByProjects[$projectId] = array();
            }
        }
        return self::$_listByProjects[$projectId];
    }

    /**
     * Загружает список задач по проекту.
     * @param  integer 		  $projectId   Идентификатор проекта,
     * @param  array<integer> $issueStatus Список статусов задач, которые должны быть загружены.
     * @param  string 		  $fromCompletedDate Минимальная дата завершения задачи
     *                                       	 (в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС)
     * @param  string 		  $toCompletedDate Максимальная дата завершения задачи
     *                                     	   (в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС)
     * @return array<Issue> Массив загруженных задач.
     */
    public static function loadListByProject(
        $projectId,
        $issueStatus = null,
        $fromCompletedDate = null,
        $toCompletedDate = null
    ) {
        $where = "`i`.`projectId` = '" . $projectId . "'";
            
        $args = '';
        if (!empty($issueStatus)) {
            $args = " AND `i`.`status` IN(" . implode(',', $issueStatus) . ')';
        }
        if (!empty($fromCompletedDate)) {
            $args .= " AND `i`.`completedDate` >= '" . $fromCompletedDate . "'";
        }
        if (!empty($toCompletedDate)) {
            $args .= " AND `i`.`completedDate` <= '" . $toCompletedDate . "'";
        }

        $where .= $args;
        return self::loadList($where);
    }

    /**
     * Загружает список задач по идентификаторам
     * @param  array<int> $issueIds Идентификаторы задач
     * @return array<Issue>
     */
    public static function loadListByIds($issueIds)
    {
        if (empty($issueIds)) {
            return array();
        } else {
            $where = "`i`.`id` IN (" . implode(',', $issueIds) . ")";
            return self::loadList($where);
        }
    }

    /**
     * Загружает список задач по части идентификатора в проекте.
     * @return array<Issue>
     */
    public static function searchListInProject($projectId, $needle)
    {
        if (empty($needle)) {
            return self::loadListByProject($projectId);
        } else {
            $needle = self::getDB()->escape4Search_t($needle);
            $where = <<<WHERE
(`i`.`projectId` = $projectId
AND
(`i`.`idInProject` LIKE '$needle%%' OR `i`.`name` LIKE '%%$needle%%'))
WHERE;
            return self::loadList($where, '', null, '`i`.`idInProject` DESC');
        }
    }
    
    public static function getListByMember($memberId)
    {
        if (!isset(self::$_listByUser[$memberId])) {
            if (LightningEngine::getInstance()->isAuth()) {
                /*$sql = "SELECT `%1\$s`.*,`%3\$s`.`uid` AS `projectUID`,
                `%3\$s`.`name` AS `projectName`,`%4\$s`.* FROM `%1\$s`, `%2\$s`, `%3\$s`,`%4\$s`".
                  "WHERE `%1\$s`.`id` = `%2\$s`.`instanceId` " .
                  "AND `%4\$s`.`issueId` = `%1\$s`.`id` ".
                  "AND `%3\$s`.`id` = `%1\$s`.`projectId` ".
                 "AND `%2\$s`.`userId` = '" . $memberId . "'".
                 "AND `%1\$s`.`status` = '0'".
                 "AND `%1\$s`.`deleted` = '0'".
                 "ORDER BY `%1\$s`.`idInProject` ";*/

                self::$_listByUser[$memberId] = self::loadList(
                    // только задачи, в которых я участник
                    '`i`.`id` = `m`.`instanceId` AND `m`.`instanceType` = ' . LPMInstanceTypes::ISSUE .
                    ' AND `m`.`userId` = ' . $memberId .
                    // открытые
                    ' AND `i`.`status` = ' . Issue::STATUS_IN_WORK .
                    // и проект не в архиве
                    ' AND `p`.`isArchive` = 0',
                    '',
                    ['m' => LPMTables::MEMBERS]
                );
            } else {
                self::$_listByUser[$memberId] = array();
            }
        }

        return self::$_listByUser[$memberId];
    }

    /**
     * Загружает список задач, связанных с указанной.
     * @param int $issueId Идентификатор задачи.
     * @return array<Issue>
     */
    public static function getListLinkedWith($issueId)
    {
        return self::loadListV2(
            "`i`.`id` <> $issueId AND (`l`.`issueId` = $issueId OR `l`.`linkedIssueId` = $issueId)",
            '(`l`.`issueId` = `i`.`id`) AS `isBaseLinked`', 
            [
                'l' => [
                    LPMTables::ISSUE_LINKED, 
                    '`l`.`issueId` = `i`.`id` OR `l`.`linkedIssueId` = `i`.`id`'
                ]
            ]
        );
    }

    public static function getCurrentList()
    {
        /*foreach (self::$_listByProjects as $list) {
            return $list;
        }

        return array();*/
        //return Project::
        return Project::$currentProject != null ?
                self::getListByProject(Project::$currentProject->id) :
                array();
    }
    
    /**
     *
     * @param float $issueId
     * @return Issue
     */
    public static function load($issueId)
    {
        return StreamObject::singleLoad($issueId, __CLASS__, "", "i`.`id");
    }

    /**
     * Загружает issue по идентификатору в проекте
     * @param $projectId
     * @param $idInProject
     * @return Issue
     */
    public static function loadByIdInProject($projectId, $idInProject)
    {
        return StreamObject::singleLoad(
            $idInProject,
            __CLASS__,
            "`i`.`projectId` = " . $projectId,
            "i`.`idInProject"
        );
    }

    /**
     * Загружает идентификатор задачи по идентификатору в проекте
     * @param $projectId
     * @param $idInProject
     * @return int
     */
    public static function loadIssueId($projectId, $idInProject)
    {
        return self::loadIntValFromDb(LPMTables::ISSUES, 'id', [
            'projectId' => $projectId,
            'idInProject' => $idInProject
        ]);
    }
    /**
     * Номер (idInProject) последней задачи в проекте
     * @return int
     */
    public static function getLastIssueId($projectId)
    {
        $db = self::getDB();
        $sql = "SELECT MAX(`idInProject`) AS maxID FROM `%s` " .
               "WHERE `projectId` = '" . $projectId . "'";
        if (!$query = $db->queryt($sql, LPMTables::ISSUES)) {
            throw new Exception('Ошибка доступа к базе', \GMFramework\ErrorCode::LOAD_DATA);
        }
        
        if ($query->num_rows == 0) {
            return 1;
        } else {
            $result = $query->fetch_assoc();
            return $result['maxID'] + 1;
        }
    }
    
    public static function updateCommentsCounter($issueId)
    {
        $sql = "INSERT INTO `%1\$s` (`issueId`, `commentsCount`) " .
                                    "VALUES ('" . $issueId . "', '1') " .
                       "ON DUPLICATE KEY UPDATE `commentsCount` = " .
                            "(SELECT COUNT(*) FROM `%2\$s` " .
                              "WHERE `%2\$s`.`instanceType` = '" . LPMInstanceTypes::ISSUE . "' " .
                                "AND `%2\$s`.`instanceId` = '" . $issueId . "' " .
                                "AND `%2\$s`.`deleted` = 0)";
        $db = LPMGlobals::getInstance()->getDBConnect();
        $db->queryt($sql, LPMTables::ISSUE_COUNTERS, LPMTables::COMMENTS);
    }
    
    public static function updateImgsCounter($issueId, $count)
    {
        $sql = "INSERT INTO `%1\$s` (`issueId`, `imgsCount`) " .
                                    "VALUES ('" . $issueId . "', '" . $count . "') " .
                       "ON DUPLICATE KEY UPDATE `imgsCount` = " .
                            "(SELECT COUNT(*) FROM `%2\$s` " .
                              "WHERE `%2\$s`.`itemType` = '" . LPMInstanceTypes::ISSUE . "' " .
                                "AND `%2\$s`.`itemId` = '" . $issueId . "' ".
                                "AND `%2\$s`.`deleted` = 0)";
        $db = LPMGlobals::getInstance()->getDBConnect();
        $db->queryt($sql, LPMTables::ISSUE_COUNTERS, LPMTables::IMAGES);
    }

    public static function getCountImportantIssues($userId, $projectId = null)
    {
        $projectId = (int)$projectId;

        $issueType = LPMInstanceTypes::ISSUE;
        $statusInWork = self::STATUS_IN_WORK;
        $minPriority = self::IMPORTANT_PRIORITY;

        if (empty($projectId)) {
            $projectFrom = "INNER JOIN `%3\$s` `p` ON `p`.`id` = `i`.`projectId`";
            $projectWhere = 'AND `p`.`isArchive` = 0';
        } else {
            $projectFrom = '';
            $projectWhere = "AND `i`.`projectId` = $projectId";
        }

        $sql = <<<SQL
    SELECT COUNT(`i`.`id`) AS `count`
      FROM `%1\$s` `i`
INNER JOIN `%2\$s` `m`
        ON `m`.`instanceId` = `i`.`id`
           $projectFrom
     WHERE `m`.`userId` = $userId
       AND `m`.`instanceType` = $issueType
       $projectWhere
       AND `i`.`priority` >= $minPriority
       AND `i`.`status` = $statusInWork
       AND `i`.`deleted` = 0
SQL;

        $db = self::getDB();
        $res = $db->queryt($sql, LPMTables::ISSUES, LPMTables::MEMBERS, LPMTables::PROJECTS);
        return $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    /**
     * Возвращает список стандартных меток для задачи, отсортированных по количеству использований
     * в рамках указанного проекта (общие метки ранжируются по использованиям именно в этом проекте,
     * а не суммарно по всем проектам).
     * @return array[{id, label, countUses, projectUses, projectId}...n] Список меток для задачи.
     */
    // TODO: перенести в IssueLabel
    public static function getLabels($projectId)
    {
        $projectId = (int) $projectId;
        $labels = array();
        // countUses — суммарное количество использований по всем проектам (вторичный критерий),
        // projectUses — количество использований метки в текущем проекте (основной критерий).
        $sql = "SELECT `l`.`id`, `l`.`label`, `l`.`countUses`, `l`.`projectId`, " .
            "COALESCE(`u`.`countUses`, 0) AS `projectUses` " .
            "FROM `%1\$s` `l` " .
            "LEFT JOIN `%2\$s` `u` ON `u`.`labelId` = `l`.`id` AND `u`.`projectId` = " . $projectId . " " .
            "WHERE (`l`.`deleted` = " . LabelState::ACTIVE . ") AND " .
            "(`l`.`projectId` = " . $projectId . " OR `l`.`projectId` = 0) " .
            "ORDER BY `projectUses` DESC, `l`.`countUses` DESC";

        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS, LPMTables::ISSUE_LABEL_USES);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
        }
        return $labels;
    }

    /**
     * Возвращает список меток во всех проектах по тексту метки.
     * @param Имя меток, которые нужно вернуть.
     * @return array Список меток по имени.
     */
    // TODO: перенести в IssueLabel
    public static function getLabelsByLabelText($label)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $label = $db->escape_string($label);
        $labels = array();
        $sql = "SELECT * FROM `%s` WHERE `label` = '" . $label . "'";
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        if ($res) {
            while ($array = $res->fetch_assoc()) {
                $labels[] = $array;
            }
        }
        return $labels;
    }

    /**
     * Возвращает метку по id.
     * @param $id
     * @return array|null
     */
    // TODO: перенести в IssueLabel
    public static function getLabel($id)
    {
        $id = (int) $id;
        $sql = "SELECT * FROM `%s` WHERE `id` = " . $id;
        $db = LPMGlobals::getInstance()->getDBConnect();
        $res = $db->queryt($sql, LPMTables::ISSUE_LABELS);
        return ($res) ? $res->fetch_assoc() : null;
    }

    /**
     * Добавить метками количество использований.
     * Увеличивает как суммарный счётчик метки (`countUses`), так и счётчик использований
     * метки в рамках указанного проекта (таблица использований по проектам).
     * @param $labelNames Список имен меток, которым нужно добавить использование.
     * @param $projectId Идентификатор проекта приоритет метки которого нужно изменить, либо 0,
     * если нужно изменить приоритет только общей для проектов метки.
     */
    // TODO: перенести в IssueLabel
    public static function addLabelsUsing($labelNames, $projectId = 0)
    {
        $projectId = (int) $projectId;
        if (empty($labelNames)) {
            return;
        }

        $db = LPMGlobals::getInstance()->getDBConnect();
        foreach ($labelNames as $key => $value) {
            $labelNames[$key] = $db->escape_string($value);
        }
        $inList = "'" . implode("','", $labelNames) . "'";

        // Суммарный счётчик использований по всем проектам (вторичный критерий сортировки).
        $sql = "UPDATE `%s` SET `countUses` = `countUses` + 1 WHERE `label` IN(" . $inList . ")" .
            " AND (`projectId` = 0 OR `projectId` = " . $projectId . ")";
        $db->queryt($sql, LPMTables::ISSUE_LABELS);

        // Счётчик использований метки в рамках конкретного проекта (основной критерий сортировки).
        // Область совпадает с обновлением суммарного счётчика выше (без фильтра по `deleted`):
        // отключённые проектные метки, замещённые общими, продолжают накапливать статистику,
        // чтобы при их восстановлении (removeLabel) значение projectUses не оказалось устаревшим.
        // Источник обёрнут в подзапрос, выбирающий только `id`: иначе колонка `countUses` есть
        // и в таблице-источнике, и в целевой, из-за чего ссылка в ON DUPLICATE KEY UPDATE
        // становится неоднозначной (ERROR 1052) и запрос молча не выполняется.
        $sql = "INSERT INTO `%1\$s` (`labelId`, `projectId`, `countUses`) " .
            "SELECT `src`.`id`, " . $projectId . ", 1 " .
            "FROM (SELECT `id` FROM `%2\$s` " .
            "WHERE `label` IN(" . $inList . ") " .
            "AND (`projectId` = 0 OR `projectId` = " . $projectId . ")) `src` " .
            "ON DUPLICATE KEY UPDATE `countUses` = `countUses` + 1";
        $db->queryt($sql, LPMTables::ISSUE_LABEL_USES, LPMTables::ISSUE_LABELS);
    }

    /**
     * Переносит (суммирует) использования по проектам с метки-источника на целевую метку.
     * Применяется при слиянии меток (например, повышение проектной метки до общей), чтобы
     * накопленная статистика использований в проектах сохранилась за целевой (активной) меткой.
     * Строки метки-источника не удаляются — так же, как при слиянии не обнуляется её `countUses`,
     * что позволяет корректно вернуть статистику при обратной операции (removeLabel).
     * @param $fromLabelId int Идентификатор метки-источника.
     * @param $toLabelId int Идентификатор целевой метки.
     */
    // TODO: перенести в IssueLabel
    public static function mergeLabelUses($fromLabelId, $toLabelId)
    {
        $fromLabelId = (int) $fromLabelId;
        $toLabelId = (int) $toLabelId;
        if ($fromLabelId <= 0 || $toLabelId <= 0 || $fromLabelId === $toLabelId) {
            return;
        }

        // Источник оборачиваем в подзапрос и переименовываем `countUses` в `uses`: иначе
        // одноимённая колонка есть и в источнике, и в целевой таблице, из-за чего ссылка
        // на `countUses` в ON DUPLICATE KEY UPDATE становится неоднозначной (ERROR 1052).
        $sql = "INSERT INTO `%1\$s` (`labelId`, `projectId`, `countUses`) " .
            "SELECT " . $toLabelId . ", `src`.`projectId`, `src`.`uses` " .
            "FROM (SELECT `projectId`, `countUses` AS `uses` FROM `%1\$s` WHERE `labelId` = " . $fromLabelId . ") `src` " .
            "ON DUPLICATE KEY UPDATE `countUses` = `countUses` + VALUES(`countUses`)";
        $db = LPMGlobals::getInstance()->getDBConnect();
        $db->queryt($sql, LPMTables::ISSUE_LABEL_USES);
    }

    /**
     * Возвращает список меток по имени.
     * @param $issueName Имя задачи.
     * @return array<string> Список меток в указанном имени.
     */
    // TODO: перенести в IssueLabel
    public static function getLabelsByName($issueName)
    {
        $name = trim($issueName);
        if (mb_substr($name, 0, 1) !== '[') return [];

        $labels = [];
        $matches = [];
        if (preg_match_all("/(?:\[([\w: -]+?)\])+.*/UA", $name, $matches)) {
            if (count($matches) > 1) {
                $labels = array_unique($matches[1]);
            }
        }
        return $labels;
    }

    /**
     * Сохраняет метку.
     * @param $label string Текст метки.
     * @param $projectId int Идентификатор проекта для которого создается метка (если не передан, то метка будет общей).
     * @param $id int Идентификатор метки (если не передан, то будет создана новая метка).
     * @param $countUses int Количество использований метки.
     * @param $deleted bool Удалена ли метка.
     * @return int|null Идентификатор вставленной/обновленной записи или null в случае ошибки.
     */
    // TODO: перенести в IssueLabel
    public static function saveLabel($label, $projectId = 0, $id = 0, $countUses = 0, $deleted = 0)
    {
        $db = LPMGlobals::getInstance()->getDBConnect();
        $id = ((int)$id > 0) ? (int)$id : "NULL";
        $projectId = (int) $projectId;
        $countUses = (int) $countUses;
        $label = $db->escape_string($label);

        $sql = "INSERT INTO `%s` (`id`, `projectId`, `label`, `countUses`, `deleted`) " .
            "VALUES ('" . $id . "', '" . $projectId . "', '" . $label . "', '" . $countUses . "', '" . $deleted . "') " .
            "ON DUPLICATE KEY UPDATE ".
            "`projectId` = VALUES(`projectId`), `label` = VALUES(`label`), `countUses` = VALUES(`countUses`), `deleted` = VALUES(`deleted`)";

        if ($db->queryt($sql, LPMTables::ISSUE_LABELS)) {
            return $db->insert_id;
        }
        return null;
    }

    /**
     * Удаляет метку.
     * @param $id int Идентификатор метки.
     * @param $deleted bool Состояние удаления метки.
     * @return bool true в случае успешной операции, иначе false.
     */
    // TODO: перенести в IssueLabel
    public static function changeLabelDeleted($id, $deleted)
    {
        $id = (int)$id;
        if ($id > 0) {
            $sql = "UPDATE `%s` SET `deleted` = " . $deleted . " WHERE `id` = " . $id;

            $db = LPMGlobals::getInstance()->getDBConnect();
            return ($db->queryt($sql, LPMTables::ISSUE_LABELS)) ? true : false;
        }
    }

    public static function loadTotalCountIssuesByProject($projectId)
    {
        $sql = "SELECT COUNT(*) AS `count` FROM `%1\$s` WHERE `projectId` = " . $projectId .
                    " AND `deleted` = 0 ";
        $db = LPMGlobals::getInstance()->getDBConnect();
        if ($q = $db->queryt($sql, LPMTables::ISSUES)) {
            $row = $q->fetch_assoc();
            return $row ? $row['count'] : 0;
        } else {
            return null;
        }
    }

    /**
     * Помечает задачу как удаленную.
     */
    public static function remove(User $user, Issue $issue)
    {
        $db = self::getDB();
        $sql = "update `%s` set `deleted` = '1' where `id` = '" . $issue->id . "'";
        if (!$db->queryt($sql, LPMTables::ISSUES)) {
            throw new Exception('Remove issue failed', \GMFramework\ErrorCode::SAVE_DATA);
        }

        Project::updateIssuesCount($issue->projectId);

        // Записываем лог
        UserLogEntry::create(
            $user->userId,
            DateTimeUtils::$currentDate,
            UserLogEntryType::DELETE_ISSUE,
            $issue->id
        );

        // отправка оповещений
        Issue::notifyByEmail(
            $issue,
            IssueEmailFormatter::issueDeletedSubject($issue),
            IssueEmailFormatter::issueDeletedText($issue, $user),
            EmailNotifier::PREF_ISSUE_STATE
        );
    }

    public static function notifyByEmail(Issue $issue, $subject, $text, $basicPref, $addAuthor = true)
    {
        $allRecipients = [];

        // отправляем оповещение участникам и автору задачи
        $members = $issue->getMemberIds();
        if ($addAuthor && !in_array($issue->authorId, $members)) {
            $members[] = $issue->authorId;
        }
        
        if (!empty($members)) {
            $sent = EmailNotifier::getInstance()->sendMail2Allowed(
                $subject,
                $text,
                $members,
                $basicPref
            );
         
            $allRecipients = array_merge($allRecipients, $sent);
        }

        // отправляем оповещение PM проекта
        $project = $issue->getProject();
        $pm = $project->getPM();
        if ($pm != null && !in_array($pm->getID(), $allRecipients)) {
            $sent = EmailNotifier::getInstance()->sendMail2Allowed(
                $subject,
                $text,
                [$pm->getID()],
                $basicPref,
                EmailNotifier::PREF_ROLE_PM
            );

            $allRecipients = array_merge($allRecipients, $sent);
        }
    }

    /**
     * Отправляет оповещение о создании новой задачи (автору, участникам и PM).
     */
    public static function notifyAdded(Issue $issue, User $author)
    {
        self::notifyByEmail(
            $issue,
            IssueEmailFormatter::issueAddedSubject($issue),
            IssueEmailFormatter::issueAddedText($issue, $author),
            EmailNotifier::PREF_ADD_ISSUE,
            false
        );
    }

    /**
     * Создаёт новую задачу в проекте и возвращает её идентификатор.
     * Значения передаются в «сыром» виде — экранирование выполняется внутри метода.
     * @return int|null Идентификатор созданной задачи или null при ошибке записи.
     */
    public static function createNew(Project $project, $name, $desc, $type, $priority, $hours, $completeDate, $authorId)
    {
        $db = self::getDB();
        $idInProject = (int)self::getLastIssueId($project->id);
        $revision = self::getNewRevision();

        // Экранируем строки и удваиваем `%`, т.к. queryt() пропускает запрос через sprintf.
        $nameEsc = $db->real_escape_string(str_replace('%', '%%', (string)$name));
        $descEsc = $db->real_escape_string(str_replace('%', '%%', (string)$desc));
        $revisionEsc = $db->real_escape_string($revision);

        $sql = "INSERT INTO `%s` (`projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
                                    "`authorId`, `createDate`, `completeDate`, `priority`, `revision` ) " .
                            "VALUES ('" . (int)$project->id . "', '" . $idInProject . "', " .
                                        "'" . $nameEsc . "', '" . (float)$hours . "', '" . $descEsc . "', " .
                                        "'" . (int)$type . "', '" . (int)$authorId . "', " .
                                        "'" . DateTimeUtils::mysqlDate() . "', " .
                                        (empty($completeDate) ? 'NULL' : "'" . $db->real_escape_string($completeDate) . "'") . ", " .
                                        "'" . (int)$priority . "', '" . $revisionEsc . "' )";

        if (!$db->queryt($sql, LPMTables::ISSUES)) {
            return null;
        }

        return $db->insert_id;
    }

    /**
     * Устанавливает статус задачи.
     *
     * Также меняет статус стикера на доске и отправляет оповещения.
     *
     * @param User $user Пользователь, который совершает действие.
     * Если действие автоматическое - передайте null.
     */
    public static function setStatus(Issue $issue, $status, $user, $sendNotify = true, $updateStickerState = true)
    {
        self::updateStatus($issue, $status);
        Project::updateIssuesCount($issue->projectId);

        // Записываем лог
        UserLogEntry::issueEdit(
            empty($user) ? 0 : $user->userId,
            $issue->id,
            'Update status to ' . $status
        );

        // Обновляем состояние стикера
        if ($updateStickerState && $issue->isOnBoard()) {
            $stickerState = null;
            switch ($status) {
                case Issue::STATUS_IN_WORK:
                    $stickerState = ScrumStickerState::IN_PROGRESS;
                    break;
                case Issue::STATUS_WAIT:
                    $stickerState = ScrumStickerState::TESTING;
                    break;
                case Issue::STATUS_COMPLETED:
                    $stickerState = ScrumStickerState::DONE;
                    break;
            }

            if ($stickerState != null &&
                    !ScrumSticker::updateStickerState($issue->id, $stickerState)) {
                throw new Exception('Status save failed', \GMFramework\ErrorCode::SAVE_DATA);
            }
        }

        if ($sendNotify) {
            self::sendStatusChangeNotify($issue, $user);
        }
    }

    private static function updateStatus(Issue $issue, $status)
    {
        $issue->status = $status;
        $hash = [
            'UPDATE' => LPMTables::ISSUES,
            'SET' => [
                'status' => $issue->status
            ],
            'WHERE' => [
                'id' => $issue->id
            ]
        ];

        if ($issue->status === Issue::STATUS_COMPLETED) {
            $issue->completedDate = (float)DateTimeUtils::date();
            $hash['SET']['completedDate'] = DateTimeUtils::mysqlDate($issue->completedDate);
            $issue->autoSetMasters();
        } elseif ($issue->status === Issue::STATUS_IN_WORK) {
            // Сбрасываем дату завершения
            $issue->completedDate = null;
            $hash['SET']['completedDate'] = '0000-00-00 00:00:00';
        } elseif ($issue->status === Issue::STATUS_WAIT) {
            $issue->autoSetTesters();
            $issue->autoSetMasters();
        }

        $db = self::getDB();
        if (!$db->queryb($hash)) {
            throw new Exception('Status save failed', \GMFramework\ErrorCode::SAVE_DATA);
        }
    }

    private static function sendStatusChangeNotify(Issue $issue, $user)
    {
        // Slack
        $slack = SlackIntegration::getInstance();

        $subject = '';
        $text = '';
        switch ($issue->status) {
            case Issue::STATUS_COMPLETED:
                $subject = IssueEmailFormatter::issueCompletedSubject($issue);
                $text = IssueEmailFormatter::issueCompletedText($issue, $user);

                $slack->notifyIssueCompleted($issue);
                break;
            case Issue::STATUS_IN_WORK:
                $subject = IssueEmailFormatter::issueReopenedSubject($issue);
                $text = IssueEmailFormatter::issueReopenedText($issue, $user);
                // TODO: оповестить в slaсk если вернули в работу
                break;
            case Issue::STATUS_WAIT:
                $subject = IssueEmailFormatter::issueSendForTestSubject($issue);
                $text = IssueEmailFormatter::issueSendForTestText($issue, $user);

                $slack->notifyIssueForTest($issue);
                break;
        }

        // Почта
        if (!empty($subject) && !empty($text)) {
            Issue::notifyByEmail(
                $issue,
                $subject,
                $text,
                EmailNotifier::PREF_ISSUE_STATE
            );
        }
    }

    /**
     * Обновляет значение приоритета задачи.
     * @param  User   $user Пользователь, который меняет приоритет.
     * @param  Issue  $issue Задача, у которой меняется приоритет.
     * @param  int    $delta Изменение приоритета.
     */
    public static function changePriority(User $user, Issue $issue, $delta)
    {
        $issue->priority = (int)max(0, min($issue->priority + $delta, 100));
        $issue->updateRevision();
        $hash = [
            'UPDATE' => LPMTables::ISSUES,
            'SET' => [
                'priority' => $issue->priority,
                'revision' => $issue->revision,
            ],
            'WHERE' => [
                'id' => $issue->id
            ]
        ];

        $db = self::getDB();
        if (!$db->queryb($hash)) {
            throw new Exception('Priority save failed', \GMFramework\ErrorCode::SAVE_DATA);
        }

        // Записываем лог
        UserLogEntry::issueEdit(
            $user->userId,
            $issue->id,
            'Change priority: ' . ($delta > 0 ? '+' : '') . $delta
        );
    }

    /**
     * Возвращает постоянный URL задачи.
     * @param  string $projectUID       Уникальный строковый идентификатор проекта.
     * @param  int    $issueIdInProject Идентификатор задачи в проекте.
     * @return URL задачи или URL сайта, идентификатор проекта пуст.
     */
    public static function getConstURLBy($projectUID, $issueIdInProject)
    {
        if (empty($projectUID)) {
            return SITE_URL;
        } else {
            return Link::getUrlByUid(
                ProjectPage::UID,
                $projectUID,
                ProjectPage::PUID_ISSUE,
                $issueIdInProject
            );
        }
    }

    /**
     * Генерирует уникальную строку ревизии задачи.
     * @return string Уникальная строка ревизии задачи.
     */
    public static function getNewRevision() {
        return uniqid();
    }

    /**
     * Разбирает введённое значение оценки в SP в число.
     * Запись `0.5`, `0,5` или `1/2` трактуется как `0.5`; в остальных
     * значениях запятая приводится к точке.
     * @return float
     */
    public static function parseStoryPoints($value)
    {
        $str = trim((string)$value);
        return ($str === '0.5' || $str === '0,5' || $str === '1/2') ? 0.5 :
            floatval(str_replace(',', '.', $str));
    }

    /**
     * Проверяет, что значение — допустимая оценка в SP.
     *
     * По умолчанию допускаются только неотрицательное целое или `0.5`;
     * дробные значения крупнее `0.5` (например, `1.5`) оценкой не считаются.
     * При `$allowFractional` разрешено любое неотрицательное кратное `0.5`
     * (`1.5`, `2.5` и т.п.) — используется для задач с несколькими
     * исполнителями, где SP распределяются дробно.
     * @param bool $allowFractional Разрешить дробные значения кратные `0.5`.
     * @return bool
     */
    public static function isValidStoryPoints($value, $allowFractional = false)
    {
        if ($value < 0) {
            return false;
        }

        if ($allowFractional) {
            $doubled = $value * 2;
            return $doubled == (int)$doubled;
        }

        return $value == (int)$value || $value == 0.5;
    }

    /**
     * Разбирает дату завершения из строки формата `YYYY-MM-DD`.
     * @return string|null|false Нормализованное `YYYY-MM-DD 00:00:00`,
     *         null при пустом значении, false при неверном формате.
     */
    public static function parseCompleteDate($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $value, $m)) {
            return false;
        }

        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return false;
        }

        return $value . ' 00:00:00';
    }

    const TYPE_DEVELOP     	= 0;
    const TYPE_BUG         	= 1;
    const TYPE_SUPPORT     	= 2;
    
    const STATUS_IN_WORK   	= 0;
    const STATUS_WAIT      	= 1;
    const STATUS_COMPLETED 	= 2;

    const MAX_IMAGES_COUNT	= 10;
    const MAX_FILES_COUNT     = 10;
    const DESC_MAX_LEN = 60000;
    const DEFAULT_PRIORITY = 49;
    const IMPORTANT_PRIORITY = 79;
    
    public $id            =  0;
    public $projectId     =  0;
    public $projectName  = ''; /*для загрузки задач по нескольким проектам*/
    public $idInProject   =  0;
    public $projectUID    = '';
    public $name          = '';
    public $desc          = '';
    /**
     * Нормочасы. Для проектов, использующих Scrum - здесь story points
     * @var float
     */
    public $hours		  =  0;
    public $type          = -1;
    public $authorId      =  0;
    public $createDate    =  0;
    /**
     * Дата последнего изменения задачи.
     * @var float
     */
    public $modifiedDate  =  0;
    public $startDate     =  0;
    public $completeDate  =  0;
    public $completedDate =  0;
    public $priority      = 49;
    public $status        = -1;
    /**
     * Уникальная строка ревизии задачи.
     * 
     * Используется для идентификации конкретного состояния контента задачи.
     * Изменение статуса задачи или ее удаление не меняет ревизию.
     * @var string 
     */
    public $revision;

    public $commentsCount = 0;

    /**
     *
     * @var User
     */
    public $author;

    /**
     * Если это модель связанной задачи, то поле определяет,
     * является ли задача базовой в этой связке или зависимой.
     *
     * Для несвязанных задач будет null.
     *
     * @var bool
     */
    public $isBaseLinked;

    /**
     * Есть ли отметка о тестировании.
     *
     * Если null, то это означает, что данные не загружены.
     * @var bool
     */
    public $isPassTest;

    /**
     * Задача ожидает внесения изменений.
     *
     * Задача ушла в тест, при тестировании обнаружены проблемы
     * и в данный момент задача в состоянии ожидания внесения правок.
     *
     * Если null, то это означает, что данные не загружены.
     * @var bool
     */
    public $isChangesRequested;

    /**
     * Проект, к которому относится задача
     * @var Project
     */
    private $_project;
    /**
     * Стикер
     * @var ScrumSticker
     */
    private $_sticker = false;
    
    private $_images = null;
    private $_files = null;
    private $_testers = null;
    private $_masters = null;

    private $_linkedIssues = null;

    private $_htmlDesc = null;
    
    public function __construct($id = 0)
    {
        parent::__construct();
        
        $this->id = $id;
        
        $this->_typeConverter->addFloatVars(
            'id',
            'authorId',
            'type',
            'status',
            'commentsCount',
            'hours'
        );
        $this->_typeConverter->addIntVars('priority', 'projectId', 'idInProject');
        $this->_typeConverter->addBoolVars('isOnBoard', 'isBaseLinked', 'isPassTest', 'isChangesRequested');
        $this->addDateTimeFields('createDate', 'startDate', 'modifiedDate', 'completeDate', 'completedDate');

        $this->addClientFields(
            'id',
            'idInProject',
            'name',
            'desc',
            'type',
            'authorId',
            'createDate',
            'modifiedDate',
            'completeDate',
            'completedDate',
            'startDate',
            'priority',
            'status',
            'commentsCount',
            'hours',
            'revision',
        );

        $this->author = new User();
    }

    public function getClientObject($addFields = null)
    {
        $obj = parent::getClientObject($addFields);

        if ($this->author) {
            $obj->author = $this->author->getClientObject();
        }

        if ($this->isBaseLinked !== null) {
            $obj->isBaseLinked = $this->isBaseLinked;
        }

        $obj->url = $this->getConstURL();
        $obj->formattedDesc = $this->getDesc();
        // Дата завершения в ISO (ГГГГ-ММ-ДД) для подстановки в поле формы —
        // форматируется на сервере, чтобы клиент не пересчитывал её из таймстампа
        // (иначе возможен сдвиг на день из-за часового пояса браузера).
        $obj->completeDateInput = $this->getCompleteDate4Input();

        return $obj;
    }
    
    public function checkViewPermit($userId)
    {
        if ($userId == $this->authorId) {
            return true;
        }
        
        // TODO проверку прав
        return true;
    }
    
    public function checkEditPermit($userId)
    {
        if ($userId == $this->authorId) {
            return true;
        }
        
        // TODO проверку прав
        return true;
    }

    public function getIdInProject()
    {
        return $this->idInProject;
    }

    public function getID()
    {
        return $this->id;
    }

    /**
     * Возвращает максимальное количество изображений.
     * @return int Максимальное количество изображений.
     */
    public function getMaxImagesCount()
    {
        return self::MAX_IMAGES_COUNT;
    }

    public function getMaxFilesCount()
    {
        return self::MAX_FILES_COUNT;
    }

    /**
     * Загружает и возвращает объект проекта.
     * Этот метод достаточно тяжелый, он будет грузить данные из БД
     * Для получения имени проекта в общем списке -
     * лучше воспользоваться projectName.
     * @return Project Модель проекта.
     */
    public function getProject()
    {
        if ($this->_project === null) {
            $this->_project = Project::loadById($this->projectId);
        }

        return $this->_project;
    }

    /**
     * Возвращает массив изображений, прикрепленных к записи
     * @var array <code>Array of LPMImg</code>
     */
    public function getImages()
    {
        if ($this->_images === null) {
            $this->_images = LPMImg::loadListByIssue($this->id);
        }

        return $this->_images;
    }

    /**
     * Возвращает массив файлов, прикрепленных к задаче.
     * @return LPMFile[]
     */
    public function getFiles()
    {
        if ($this->_files === null) {
            $this->_files = LPMFile::loadListByInstance(LPMInstanceTypes::ISSUE, $this->id);
        }

        return $this->_files;
    }

    /**
     * Стикер, прикрепленный к доске
     * @return ScrumSticker|null
     */
    public function getSticker()
    {
        if ($this->_sticker === false) {
            $this->_sticker = ScrumSticker::load($this->id);
        }

        return $this->_sticker;
    }

    public function isOnBoard()
    {
        $sticker = $this->getSticker();
        return $sticker !== null && $sticker->isOnBoard();
    }
    
    /**
     * относительно текущей страницы
     */
    public function getURL4View()
    {
        $curPage = LightningEngine::getInstance()->getCurrentPage();
        return $curPage->getBaseUrl(ProjectPage::PUID_ISSUE, $this->idInProject);
    }
    
    public function getPriorityStr()
    {
        if ($this->priority < 33) {
            return 'низкий';
        } elseif ($this->priority < 66) {
            return 'нормальный';
        } else {
            return 'высокий';
        }
    }
    
    public function getPriorityDisplayValue()
    {
        return $this->priority + 1;
    }

    /**
     * Возвращает URL страницы проекта, к которому относится задача,
     * @param  string $hash Хэш параметр.
     * @return string URL страницы проекта.
     */
    public function getProjectUrl($hash = '')
    {
        return Project::getURLByProjectUID($this->projectUID, $hash);
    }
    
    /**
     * Возвращает URL страницы Scrum доски проекта, к которому относится задача,
     * @param  string $hash Хэш параметр.
     * @return string URL страницы проекта.
     */
    public function getProjectScrumUrl($hash = '')
    {
        return Project::getURLByProjectUIDScrum($this->projectUID, $hash);
    }
    
    /**
     * Чтобы этот метод корректно работал, необходимо,
     * чтобы был загружен uid проекта
     */
    public function getConstURL()
    {
        return self::getConstURLBy($this->projectUID, $this->idInProject);
    }
    
    public function getName()
    {
        return $this->name;
    }

    public function getLabelNames()
    {
        return self::getLabelsByName($this->getName());
    }

    public function getStrHours()
    {
        return ($this->hours == .5) ? "1/2" : (string) $this->hours;
    }

    /**
     * Возвращает строку для параметра hours
     * @param  boolean $short Использовать сокращение
     * @return string Строка, со склонением, зависящим от значения hours. Например: часов, SP
     */
    public function getNormHoursLabel($short = false)
    {
        return $this->getProject()->getNormHoursLabel($this->hours, $short);
    }
    
    public function getDesc()
    {
        if (empty($this->_htmlDesc)) {
            $this->_htmlDesc = HTMLHelper::htmlTextForIssue($this->desc);
        }
        
        return $this->_htmlDesc;
    }

    public function isCompleted()
    {
        return $this->status == self::STATUS_COMPLETED;
    }

    public function isMember($userId)
    {
        if ($this->_members === null) {
            return Member::hasIssueMember($this->id, $userId);
        } else {
            return $this->hasUserIn($this->_members, $userId);
        }
    }

    public function isTester($userId)
    {
        return $this->hasUserIn($this->getTesters(), $userId);
    }

    /**
     * Определяет, является ли указанный пользователь мастером задачи.
     * @param int $userId Идентификатор пользователя.
     * @param bool $includingProject    Если `true`, будет выполнена проверка не только среди
     *                                  мастеров задачи, но и проверен мастер проекта.
     * @return bool
     */
    public function isMaster($userId, $includingProject = false)
    {
        return $this->hasUserIn($this->getMasters(), $userId) ||
            $includingProject && $this->getProject()->masterId == $userId;
    }
    
    /**
     * Возвращает краткое описание задачи - для превью.
     * @return string Краткое описание.
     */
    public function getShortDesc($rich = true)
    {
        $desc = $this->desc;
        // Для короткого описания вырежем весь код
        $desc = HTMLHelper::stripCode($desc);
        $desc = parent::getShort($desc);

        if ($rich) {
            $desc = parent::getRich($desc);
        }

        return $desc;
    }
    
    public function getCreateDate()
    {
        return self::getDateStr($this->createDate);
    }
    
    public function getCompleteDate()
    {
        return self::getDateStr($this->completeDate);
    }
    
    public function hasCompleteDate()
    {
        return !empty($this->completeDate);
    }
    
    /**
     * Возвращает количество дней до даты завершения задачи.
     *
     * Если дата завершения не задана - вернется 0.
     * Если дата завершение уже прошло - будет отрицательное число.
     *
     * @return float Количество дней.
     */
    public function daysTillComplete()
    {
        if (!$this->hasCompleteDate()) {
            return 0;
        }

        // Берем не текущую дату, а начало дня,
        // чтобы сегодняшние задачи не считались просроченными
        $today = DateTimeUtils::dayStart();

        $diff = $this->completeDate - $today;
        return $diff / 86400;
    }
    
    public function getCompletedDate()
    {
        return self::getDateStr($this->completedDate);
    }
    
    public function getCompleteDate4Input()
    {
        return self::getDate4Input($this->completeDate);
    }
    
    public function getAuthorLinkedName()
    {
        return $this->author ? $this->author->getLinkedName() : '';
    }
    
    public function getType()
    {
        switch ($this->type) {
            case self::TYPE_BUG: return 'Ошибка';
            case self::TYPE_DEVELOP: return 'Разработка';
            case self::TYPE_SUPPORT: return 'Поддержка';
            default: return '';
        }
    }
    
    public function getStatus()
    {
        switch ($this->status) {
            case self::STATUS_IN_WORK: return 'В работе';
            case self::STATUS_WAIT: return 'Ожидает проверки';
            case self::STATUS_COMPLETED: return 'Завершена';
            default: return '';
        }
    }
    
    /**
     * Определяет, находится ли сейчас задача в тестировании.
     */
    public function isTesting()
    {
        return $this->status == self::STATUS_WAIT;
    }

    public function loadStream($hash)
    {
        $res = parent::loadStream($hash) && $this->author->loadStream($hash);
        if (isset($hash['with_sticker'])) {
            $sData = [];
            foreach ($hash as $key => $value) {
                if (strpos($key, 's_') === 0 && $value !== null) {
                    $sData[$key] = $value;
                }
            }

            if (empty($sData)) {
                $this->_sticker = null;
            } else {
                if (!$this->_sticker) {
                    $this->_sticker = new ScrumSticker();
                }
                $this->_sticker->loadStream($sData);
                $this->_sticker->issueId = $this->issueId;
            }
        }

        if (isset($hash['t_testState'])) {
            $testState = $hash['t_testState'];
            $this->isPassTest = $testState == IssueCommentType::PASS_TEST;
            $this->isChangesRequested = $this->isTesting() && $testState == IssueCommentType::REQUEST_CHANGES;
        }

        return $res;
    }

    /**
     * @return array<{
     *  type:string = youtube|video,
     *  url:string
     * ]>
     * @see ParseTextHelper::parseVideoLinks()
     */
    public function getVideoLinks()
    {
        return ParseTextHelper::parseVideoLinks($this->getDesc());
    }

    /**
     * Возвращает список связанных задач.
     *
     * Если данных о связанных задачах нет - они будут загружены из БД.
     * @return array<Issue>
     */
    public function getLinkedIssues()
    {
        return $this->_linkedIssues == null ? $this->loadLinkedIssues() : $this->_linkedIssues;
    }

    /**
     * Определяет, есть ли хотя бы один тестировщик.
     * @return bool
     */
    public function hasTesters()
    {
        return !empty($this->getTesters());
    }

    public function getTesters()
    {
        return $this->_testers === null && !$this->loadTesters() ? [] : $this->_testers;
    }

    public function getTesterIds()
    {
        return $this->getMemberIdsBy($this->getTesters());
    }

    public function getTesterIdsStr()
    {
        return implode(',', $this->getTesterIds());
    }

    /**
     * Автоматически назначает мастеров для задачи,
     * если нет уже заданных для конкретной задачи.
     *
     * Мастера будут добавлены, если найдутся подходящие по тегам.
     * Мастер для проекта по умолчанию - не назначается.
     */
    public function autoSetMasters()
    {
        $masters = $this->getMasters();

        if (empty($masters)) {
            $issueId = $this->id;
            $project = $this->getProject();

            $this->_masters = $this->autoSetByLabels(
                function () use ($project) {
                    return $project->getSpecMasters();
                },
                function ($userIds) use ($issueId) {
                    return Member::saveIssueMasters($issueId, $userIds);
                }
            );
        }
    }

    /**
     * Автоматически назначает тестировщиков для задачи,
     * если нет уже заданных для конкретной задачи.
     *
     * Тестеры будут добавлены, если найдутся подходящие по тегам.
     * Если тестировщик по тегу не найдет, то будет назначен тестировщик по умолчанию.
     * Мастер для проекта по умолчанию - не назначается.
     */
    public function autoSetTesters()
    {
        $testers = $this->getTesters();

        if (empty($testers)) {
            $issueId = $this->id;
            $project = $this->getProject();

            // Пытаемся выставить по тегам 
            $testersByTags = $this->autoSetByLabels(
                function () use ($project) {
                    return $project->getSpecTesters();
                },
                function ($userIds) use ($issueId) {
                    return Member::saveIssueTesters($issueId, $userIds);
                }
            );

            if (!empty($testersByTags)) {
                $this->_testers = $testersByTags;
            } else {
                // Если нет, пытаемся выставить дефолтного 
                $defaultTester = $project->getTester();
                if (!empty($defaultTester)) {
                    Member::saveIssueTesters($issueId, [$defaultTester->userId]);
                    $this->_testers = [$defaultTester];
                }
            }
        }
    }

    private function autoSetByLabels($getSpecMembers, $saveMembers)
    {
        // Получаем список меток (тегов) для задачи
        $labelNames = $this->getLabelNames();
        if (!empty($labelNames)) {
            // TODO: переделать чтобы не грузить лишнего (можно грузить только нужные теги)
            $labels = $this->getProject()->getLabels();
            $labelIds = [];

            foreach ($labels as $label) {
                if (in_array($label['label'], $labelNames)) {
                    $labelIds[] = (int)$label['id'];
                }
            }
            $labelIds = array_unique($labelIds);

            // TODO: грузить только кого надо
            $specMembers = $getSpecMembers();
            $usersById = [];
            foreach ($specMembers as $member) {
                if (in_array($member->extraId, $labelIds)) {
                    $usersById[$member->userId] = $member;
                }
            }

            if (!empty($usersById)) {
                $newUserIds = array_keys($usersById);
                if (!$saveMembers($newUserIds)) {
                    throw new \GMFramework\ProviderSaveException(
                        'Не удалось сохранить автоматически назначенных участников для задачи'
                    );
                }

                return array_values($usersById);
            }
        }
    
        return [];
    }

    /**
     * Возвращает список мастеров, назначенных этой задаче.
     *
     * Мастер проекта игнорируется.
     * @return array<Member>
     */
    public function getMasters()
    {
        return $this->_masters === null && !$this->loadMasters() ? [] : $this->_masters;
    }

    /**
     * Возвращает список идентификаторов мастеров, назначенных этой задаче.
     *
     * Мастер проекта игнорируется.
     * @return array<int>
     */
    public function getMasterIds()
    {
        return $this->getMemberIdsBy($this->getMasters());
    }

    /**
     * Возвращает строку идентификаторов мастеров, соединенных через запятую.
     * @return string
     */
    public function getMasterIdsStr()
    {
        return implode(',', $this->getMasterIds());
    }
    
    public function getMembersSp()
    {
        $members = $this->getMembers();
        $arr = array();
        foreach ($members as $member) {
            $arr[] = $member->sp;
        }
        return $arr;
    }
    
    public function getMembersSpStr()
    {
        return implode(',', $this->getMembersSp());
    }

    public function extractParticipantsFrom(&$list, $extractMembers = true, $extractTesters = true, $extractMasters = true) {
        $allowedTypes = [];

        if ($extractMembers) {
            $this->_members = [];
            $allowedTypes[] = LPMInstanceTypes::ISSUE;
        }

        if ($extractTesters) {
            $this->_testers = [];
            $allowedTypes[] = LPMInstanceTypes::ISSUE_FOR_TEST;
        }

        if ($extractMasters) {
            $this->_masters = [];
            $allowedTypes[] = LPMInstanceTypes::ISSUE_FOR_MASTER;
        }
        
        $len = count($list);
        for ($i = 0; $i < $len; $i++) {
            $member = $list[$i];
            if ($member->instanceId == $this->id && in_array($member->instanceType, $allowedTypes)) {
                switch ($member->instanceType) {
                    case LPMInstanceTypes::ISSUE: $this->_members[] = $member; break;
                    case LPMInstanceTypes::ISSUE_FOR_TEST: $this->_testers[] = $member; break;
                    case LPMInstanceTypes::ISSUE_FOR_MASTER: $this->_masters[] = $member; break;
                }

                array_splice($list, $i, 1);
                $i--;
                $len--;
            }
        }
    }

    public function updateRevision()
    {
        $this->revision = $this->getNewRevision();
    }

    protected function loadMembers()
    {
        $this->_members = Member::loadListByIssue($this->id);
        if ($this->_members === false) {
            throw new Exception('Ошибка при загрузке списка исполнителей задачи');
        }
        return true;
    }

    protected function loadTesters()
    {
        $this->_testers = Member::loadListByIssueForTest($this->id);
        if ($this->_testers === false) {
            throw new Exception('Ошибка при загрузке списка тестеров задачи');
        }
        return $this->_testers;
    }

    /**
     * Загружается список мастеров для задачи.
     * @return array<Member>
     */
    protected function loadMasters()
    {
        $this->_masters = Member::loadMastersForIssue($this->id);
        if ($this->_masters === false) {
            throw new Exception('Ошибка при загрузке списка мастеров задачи');
        }

        return $this->_masters;
    }

    private function loadLinkedIssues()
    {
        $list = Issue::getListLinkedWith($this->id);
        if ($list === false) {
            throw new Exception('Ошибка при загрузке связанных задач');
        }

        $this->_linkedIssues = $list;
        return $list;
    }

    private function hasUserIn($list, $userId)
    {
        foreach ($list as $user) {
            if ($user->userId == $userId) {
                return true;
            }
        }

        return false;
    }

    private function getMemberIdsBy($list)
    {
        $arr = [];
        foreach ($list as $user) {
            $arr[] = $user->userId;
        }

        return $arr;
    }
}
