<?php
class Issue extends MembersInstance
{
    private static $_listByProjects = array();
    private static $_listByUser = array();

    /**
     * Возвращает выражение постраничной выборки для запроса списка задач.
     * @param  int $limit  Максимальное количество задач (0 - без ограничения).
     * @param  int $offset Смещение выборки.
     * @return string Часть SQL запроса (пустая строка, если ограничения нет).
     */
    private static function getLimitSql($limit, $offset)
    {
        $limit = (int)$limit;
        if ($limit <= 0) {
            return '';
        }

        $offset = max(0, (int)$offset);
        return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    /**
     * Возвращает список состояний MR для FIELD() в порядке возрастания
     * "завершённости": в выборке состояния задачи побеждает самый незавершённый MR,
     * т.к. пока есть невлитый MR - правки по задаче ещё не в стабильной ветке.
     * @return string Часть SQL запроса - состояния через запятую.
     */
    private static function getMrStatesOrderSql()
    {
        return "'" . implode("', '", [
            GitlabMergeRequest::STATE_CLOSED,
            GitlabMergeRequest::STATE_MERGED,
            GitlabMergeRequest::STATE_LOCKED,
            GitlabMergeRequest::STATE_OPENED,
        ]) . "'";
    }

    /**
     * Возвращает описание запроса, определяющего текущее состояние задачи
     * в тесте: последний комментарий среди отметки о прохождении теста,
     * запроса правок и комментария с MR. Более свежий комментарий вытесняет
     * прежний, поэтому отметка о прохождении теста снимается сама;
     * обычные комментарии в набор не входят и отметку не сбивают.
     *
     * Это единственное определение правила: его используют и общая выборка
     * задач (подзапросом), и точечная загрузка отметки
     * {@see loadTestStateComment()}.
     * @param  int|\GMFramework\DBColumn $issueId Идентификатор задачи либо
     *                                            колонка с ним - для подзапроса.
     * @param  string $select Список полей выборки. Для подзапроса поле должно
     *                        быть ровно одно.
     * @return array Описание запроса для конструктора.
     */
    private static function getTestStateSqlHash($issueId, $select = '`icm`.`type`')
    {
        return [
            'SELECT' => $select,
            'FROM'   => LPMTables::COMMENTS,
            'AS'     => 'cm',
            'JOINS'  => [[
                'INNER JOIN' => LPMTables::ISSUE_COMMENT,
                'AS'         => 'icm',
                'ON'         => ['`icm`.`commentId`' => self::col('cm.id')],
            ]],
            'WHERE' => [
                '`cm`.`instanceType`' => LPMInstanceTypes::ISSUE,
                '`cm`.`instanceId`'   => $issueId,
                '`cm`.`deleted`'      => 0,
                '`icm`.`type`'        => [
                    IssueCommentType::PASS_TEST,
                    IssueCommentType::REQUEST_CHANGES,
                    IssueCommentType::MERGE_REQUEST,
                ],
            ],
            // Комментарии одной секунды разводим по id: иначе отметка
            // определялась бы произвольно
            'ORDER BY' => ['`cm`.`date` DESC', '`cm`.`id` DESC'],
            'LIMIT'    => 1,
        ];
    }

    /**
     * Загружает комментарий, задающий текущее состояние задачи в тесте.
     *
     * Точечный запрос вместо полной перезагрузки задачи: нужен там, где после
     * изменения комментариев надо освежить только отметки о тестировании.
     * @param  int $issueId Идентификатор задачи.
     * @return array|null Массив с полями `type` (@see IssueCommentType)
     * и `date` (когда отметка поставлена). null, если отметок нет.
     */
    public static function loadTestStateComment($issueId)
    {
        $row = self::buildAndExecuteSingle(
            self::getTestStateSqlHash((int)$issueId, '`icm`.`type`, `cm`.`date`')
        );

        return empty($row) ? null : $row;
    }

    /**
     * Возвращает описание запроса, определяющего момент, с которого задача
     * считается взятой в тестирование.
     *
     * Отметка о взятии живёт в журнале задачи, а снимающие её отметки
     * о проверке - в комментариях, поэтому состояние выводится сравнением дат:
     * задача взята, если событие взятия свежее последней отметки о проверке.
     * Запрос отдаёт дату последнего события журнала о тестировании, если это
     * взятие, и NULL, если отметку уже сняли.
     * @param  int|\GMFramework\DBColumn $issueId Идентификатор задачи либо
     *                                            колонка с ним - для подзапроса.
     * @return array Описание запроса для конструктора.
     */
    private static function getTakenForTestingSqlHash($issueId)
    {
        $taken = IssueEventType::TAKEN_FOR_TESTING;

        return IssueEvent::getLastSqlHash(
            $issueId,
            [$taken, IssueEventType::RELEASED_FROM_TESTING],
            "IF(`ev`.`type` = '$taken', `ev`.`date`, NULL)"
        );
    }

    /**
     * Загружает последнее событие журнала об отметке о взятии задачи
     * в тестирование.
     *
     * Это единственное место, где задан набор событий отметки: по последнему
     * из них определяются и её состояние, и тот, за кем она стоит.
     * @param  int $issueId Идентификатор задачи.
     * @return IssueEvent|null Событие взятия либо снятия отметки, null - если
     * отметку ни разу не ставили.
     */
    public static function loadLastTestingEvent($issueId)
    {
        return IssueEvent::loadLast((int)$issueId, [
            IssueEventType::TAKEN_FOR_TESTING,
            IssueEventType::RELEASED_FROM_TESTING,
        ]);
    }

    /**
     * Загружает момент, с которого задача считается взятой в тестирование.
     * @param  int $issueId Идентификатор задачи.
     * @return string|null Дата события взятия либо null, если отметки нет.
     */
    public static function loadTakenForTestingDate($issueId)
    {
        $event = self::loadLastTestingEvent($issueId);

        return empty($event) || $event->type != IssueEventType::TAKEN_FOR_TESTING
            ? null
            : DateTimeUtils::mysqlDate($event->date);
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
     * @param  array  $extraTables Ассоциативный массив дополнительных таблиц для выборки
     *                             [алиас => таблица].
     * @param  int    $limit       Максимальное количество задач в выборке (0 - без ограничения).
     * @param  int    $offset      Смещение выборки.
     * @return array<Issue> Массив загруженных задач.
     */
    protected static function loadList(
        $where,
        $extraSelect = '',
        $extraTables = null,
        $orderBy = null,
        $limit = 0,
        $offset = 0
    ) {
        $instanceType = LPMInstanceTypes::ISSUE;

        $passTestType = IssueCommentType::PASS_TEST;
        $requestChangesType = IssueCommentType::REQUEST_CHANGES;
        $testStateSql = '(' . self::buildQuery(self::getTestStateSqlHash(self::col('i.id'))) . ')';
        $testStateDateSql = '('
            . self::buildQuery(self::getTestStateSqlHash(self::col('i.id'), '`cm`.`date`')) . ')';
        $takenForTestingSql = '('
            . self::buildQuery(self::getTakenForTestingSqlHash(self::col('i.id'))) . ')';
        $mrStatesOrder = self::getMrStatesOrderSql();

        $statusWait = Issue::STATUS_WAIT;
        $statusCompleted = Issue::STATUS_COMPLETED;
        // Даты последней активности и состояние MR нужны только для задач в тесте,
        // поэтому считаются лишь для них: в больших проектах задач в тесте единицы,
        // а подзапросы выполняются для каждой строки выборки.
        $sql = <<<SQL
SELECT `i`.*, 'with_sticker', `st`.`state` `s_state`,
    IF(`i`.`status` = $statusCompleted, `i`.`completedDate`, NULL) AS `realCompleted`,
    `u`.*, `cnt`.*, `p`.`uid` as `projectUID`, `p`.`name` AS `projectName`,
    `p`.`scrum` AS `projectScrum`,
    $testStateSql AS `t_testState`,
    IF(`i`.`status` = $statusWait, $testStateDateSql, NULL) AS `t_testStateDate`,
    IF(`i`.`status` = $statusWait, $takenForTestingSql, NULL) AS `t_takenAt`,
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
      ), NULL) AS `t_lastBugDate`,
    IF(`i`.`status` = $statusWait,
      (SELECT `mr`.`state`
         FROM `%8\$s` `mr`
        WHERE `mr`.`issueId` = `i`.`id`
     ORDER BY FIELD(`mr`.`state`, $mrStatesOrder) DESC
        LIMIT 1), NULL) AS `t_mrState`
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
            LPMTables::ISSUE_COMMENT,
            LPMTables::ISSUE_MR
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
            // Дата последней активности по задаче в тесте. Для задачи с багом это дата
            // последнего бага. Если комментариев нет вообще - берем дату изменения самой
            // задачи (в том числе перевода в тест), чтобы только что отправленная в тест
            // старая задача не считалась застоявшейся.
            $testActivity = "COALESCE(IF(`t_testState` = '$requestChangesType', "
                . "`t_lastBugDate`, `t_lastCommentDate`), "
                . "GREATEST(`i`.`createDate`, `i`.`modifiedDate`))";

            // Задачи в тесте внутри своей группы (прошла тест / есть баг / без отметки)
            // сортируются по стареющему приоритету: каждые N дней простоя добавляют
            // задаче пункт приоритета, но не больше потолка. Так важные задачи остаются
            // выше, а забытые постепенно всплывают и не тонут навсегда.
            // Неизвестная дата активности считается максимальным простоем.
            $agingDays = ISSUE_TEST_AGING_DAYS_PER_POINT;
            $agingMax = ISSUE_TEST_AGING_MAX_BONUS;
            $agingUnknown = $agingMax * $agingDays;
            $orderBy = <<<SQL
            FIELD(`i`.`status`, $statusesOrder),
            `realCompleted` DESC,
            IF(`i`.`status` = $statusWait, FIELD(`t_testState`, $testStatesOrderDesc), 0) DESC,
            IF(`i`.`status` = $statusWait,
               `i`.`priority` + LEAST(GREATEST(COALESCE(DATEDIFF(NOW(), $testActivity),
                                                        $agingUnknown), 0) DIV $agingDays, $agingMax),
               NULL) DESC,
            IF(`i`.`status` = $statusWait, $testActivity, NULL) ASC,
            `i`.`priority` DESC,
            `i`.`completeDate` ASC, `id` ASC
            SQL;
        }

        $sql .= " AND `i`.`authorId` = `u`.`userId` ORDER BY " . $orderBy;

        $sql .= self::getLimitSql($limit, $offset);

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
        $testStateSql = '(' . self::buildQuery(self::getTestStateSqlHash(self::col('i.id'))) . ')';
        $testStateDateSql = '('
            . self::buildQuery(self::getTestStateSqlHash(self::col('i.id'), '`cm`.`date`')) . ')';
        $takenForTestingSql = '('
            . self::buildQuery(self::getTakenForTestingSqlHash(self::col('i.id'))) . ')';
        $mrStatesOrder = self::getMrStatesOrderSql();

        $statusWait = Issue::STATUS_WAIT;
        $statusCompleted = Issue::STATUS_COMPLETED;
        // Даты последней активности и состояние MR нужны только для задач в тесте,
        // поэтому считаются лишь для них: в больших проектах задач в тесте единицы,
        // а подзапросы выполняются для каждой строки выборки.
        $sql = <<<SQL
SELECT `i`.*, 'with_sticker', `st`.`state` `s_state`,
    IF(`i`.`status` = $statusCompleted, `i`.`completedDate`, NULL) AS `realCompleted`,
    `u`.*, `cnt`.*, `p`.`uid` as `projectUID`, `p`.`name` AS `projectName`,
    `p`.`scrum` AS `projectScrum`,
    $testStateSql AS `t_testState`,
    IF(`i`.`status` = $statusWait, $testStateDateSql, NULL) AS `t_testStateDate`,
    IF(`i`.`status` = $statusWait, $takenForTestingSql, NULL) AS `t_takenAt`,
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
      ), NULL) AS `t_lastBugDate`,
    IF(`i`.`status` = $statusWait,
      (SELECT `mr`.`state`
         FROM `%8\$s` `mr`
        WHERE `mr`.`issueId` = `i`.`id`
     ORDER BY FIELD(`mr`.`state`, $mrStatesOrder) DESC
        LIMIT 1), NULL) AS `t_mrState`
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
            LPMTables::ISSUE_COMMENT,
            LPMTables::ISSUE_MR
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
            // Дата последней активности по задаче в тесте. Для задачи с багом это дата
            // последнего бага. Если комментариев нет вообще - берем дату изменения самой
            // задачи (в том числе перевода в тест), чтобы только что отправленная в тест
            // старая задача не считалась застоявшейся.
            $testActivity = "COALESCE(IF(`t_testState` = '$requestChangesType', "
                . "`t_lastBugDate`, `t_lastCommentDate`), "
                . "GREATEST(`i`.`createDate`, `i`.`modifiedDate`))";

            // Задачи в тесте внутри своей группы (прошла тест / есть баг / без отметки)
            // сортируются по стареющему приоритету: каждые N дней простоя добавляют
            // задаче пункт приоритета, но не больше потолка. Так важные задачи остаются
            // выше, а забытые постепенно всплывают и не тонут навсегда.
            // Неизвестная дата активности считается максимальным простоем.
            $agingDays = ISSUE_TEST_AGING_DAYS_PER_POINT;
            $agingMax = ISSUE_TEST_AGING_MAX_BONUS;
            $agingUnknown = $agingMax * $agingDays;
            $orderBy = <<<SQL
            FIELD(`i`.`status`, $statusesOrder),
            `realCompleted` DESC,
            IF(`i`.`status` = $statusWait, FIELD(`t_testState`, $testStatesOrderDesc), 0) DESC,
            IF(`i`.`status` = $statusWait,
               `i`.`priority` + LEAST(GREATEST(COALESCE(DATEDIFF(NOW(), $testActivity),
                                                        $agingUnknown), 0) DIV $agingDays, $agingMax),
               NULL) DESC,
            IF(`i`.`status` = $statusWait, $testActivity, NULL) ASC,
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
     * Загружает список задач проекта с фильтрацией и постраничной выборкой.
     * @param  int   $projectId Идентификатор проекта.
     * @param  array $filters   Фильтры выборки, см. buildProjectFilterWhere().
     * @param  int   $limit     Максимальное количество задач (0 - без ограничения).
     * @param  int   $offset    Смещение выборки.
     * @return array<Issue> Массив загруженных задач.
     */
    public static function loadListByProjectFiltered($projectId, array $filters = [], $limit = 0, $offset = 0)
    {
        return self::loadList(self::buildProjectFilterWhere($projectId, $filters), '', null, null, $limit, $offset);
    }

    /**
     * Возвращает общее количество задач проекта, подходящих под фильтры.
     * @param  int   $projectId Идентификатор проекта.
     * @param  array $filters   Фильтры выборки, см. buildProjectFilterWhere().
     * @return int Количество задач.
     */
    public static function countListByProjectFiltered($projectId, array $filters = [])
    {
        $where = self::buildProjectFilterWhere($projectId, $filters);
        $sql = "SELECT COUNT(*) AS `count` FROM `%s` `i` WHERE `i`.`deleted` = '0' AND " . $where;

        $res = self::getDB()->queryt($sql, LPMTables::ISSUES);
        return $res ? (int)$res->fetch_assoc()['count'] : 0;
    }

    /**
     * Формирует условие выборки задач проекта по фильтрам.
     *
     * Условие использует только поля таблицы задач (алиас `i`).
     * @param  int   $projectId Идентификатор проекта.
     * @param  array $filters   Фильтры выборки:
     *                          - `statuses` array<int> статусы задач;
     *                          - `types` array<int> типы задач;
     *                          - `labels` array<string> метки, каждая из которых должна быть у задачи;
     *                          - `search` string подстрока имени или начало номера задачи в проекте.
     * @return string Условие выборки.
     */
    private static function buildProjectFilterWhere($projectId, array $filters)
    {
        $db = self::getDB();
        $where = '`i`.`projectId` = ' . (int)$projectId;

        if (!empty($filters['statuses'])) {
            $where .= ' AND `i`.`status` IN (' . implode(',', array_map('intval', $filters['statuses'])) . ')';
        }

        if (!empty($filters['types'])) {
            $where .= ' AND `i`.`type` IN (' . implode(',', array_map('intval', $filters['types'])) . ')';
        }

        if (!empty($filters['labels'])) {
            $issueIds = self::loadIdsByLabels($projectId, $filters['labels']);
            $where .= ' AND `i`.`id` IN (' . (empty($issueIds) ? '0' : implode(',', $issueIds)) . ')';
        }

        $search = isset($filters['search']) ? (string)$filters['search'] : '';
        if ($search !== '') {
            $needle = $db->escape4Search_t($search);
            $where .= " AND (`i`.`idInProject` LIKE '$needle%%' OR `i`.`name` LIKE '%%$needle%%')";
        }

        return $where;
    }

    /**
     * Возвращает идентификаторы задач проекта, у которых есть все указанные метки.
     *
     * Метки задачи - это только блоки в квадратных скобках в начале её имени, поэтому
     * выборка по имени в запросе дает лишь кандидатов: точное совпадение проверяется
     * разбором имени. Регистр меток не учитывается.
     * @param  int           $projectId Идентификатор проекта.
     * @param  array<string> $labels    Метки, каждая из которых должна быть у задачи.
     * @return array<int> Идентификаторы задач.
     */
    private static function loadIdsByLabels($projectId, array $labels)
    {
        $db = self::getDB();
        $needles = [];
        foreach (array_unique($labels) as $label) {
            $needles[] = mb_strtolower($label);
        }

        $where = '`i`.`projectId` = ' . (int)$projectId . " AND `i`.`deleted` = '0'" .
            " AND `i`.`name` LIKE '[%%'";
        foreach ($needles as $needle) {
            $where .= " AND `i`.`name` LIKE '%%[" . $db->escape4Search_t($needle) . "]%%'";
        }

        $res = $db->queryt("SELECT `i`.`id`, `i`.`name` FROM `%s` AS `i` WHERE " . $where, LPMTables::ISSUES);
        if (!$res) {
            return [];
        }

        $issueIds = [];
        while ($row = $res->fetch_assoc()) {
            $issueLabels = array_map('mb_strtolower', self::getLabelsByName($row['name']));
            if (count(array_intersect($needles, $issueLabels)) === count($needles)) {
                $issueIds[] = (int)$row['id'];
            }
        }

        return $issueIds;
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
    
    /**
     * Загружает незавершённые задачи неархивных проектов, в которых
     * пользователь участвует - исполнителем либо тестировщиком.
     *
     * Незавершённые - это задачи в работе и задачи, ожидающие проверки:
     * ушедшая в тест задача остаётся в списке и у исполнителя, и у тестировщика.
     * @param  int $memberId Идентификатор пользователя.
     * @return array<Issue>
     */
    public static function getListByMember($memberId)
    {
        if (!isset(self::$_listByUser[$memberId])) {
            if (LightningEngine::getInstance()->isAuth()) {
                // Участие проверяется подзапросом, а не присоединением таблицы:
                // тот, кто в задаче и исполнитель, и тестировщик, дал бы
                // на присоединении две строки, и задача попала бы в список дважды
                $participationSql = self::buildQuery([
                    'SELECT' => '1',
                    'FROM'   => LPMTables::MEMBERS,
                    'AS'     => 'm',
                    'WHERE'  => [
                        '`m`.`instanceId`'   => self::col('i.id'),
                        '`m`.`instanceType`' => [
                            LPMInstanceTypes::ISSUE,
                            LPMInstanceTypes::ISSUE_FOR_TEST,
                        ],
                        '`m`.`userId`' => (int)$memberId,
                    ],
                ]);

                $statuses = implode(', ', [Issue::STATUS_IN_WORK, Issue::STATUS_WAIT]);

                $list = self::loadList(
                    // только задачи, в которых я исполнитель или тестировщик
                    "EXISTS ($participationSql)" .
                    // незавершённые
                    " AND `i`.`status` IN ($statuses)" .
                    // и проект не в архиве
                    ' AND `p`.`isArchive` = 0'
                );

                self::$_listByUser[$memberId] = self::preloadParticipants($list);
            } else {
                self::$_listByUser[$memberId] = array();
            }
        }

        return self::$_listByUser[$memberId];
    }

    /**
     * Заранее загружает исполнителей и тестировщиков всех задач списка.
     *
     * Мастера не загружаются.
     * @param  array<Issue> $list
     * @return array<Issue> Тот же список.
     */
    private static function preloadParticipants(array $list)
    {
        $issueIds = [];
        foreach ($list as $issue) {
            $issueIds[] = $issue->id;
        }

        $participants = Member::loadListAnyForIssues($issueIds, true, true, false);
        foreach ($list as $issue) {
            $issue->extractParticipantsFrom($participants, true, true, false);
        }

        return $list;
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
     *
     * Метки - это идущие подряд блоки в квадратных скобках в начале имени;
     * между ними допустимы пробелы. Текст метки может быть на любом языке,
     * пустые блоки пропускаются.
     *
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
        if (preg_match_all(self::LABELS_PATTERN, $name, $matches)) {
            foreach ($matches[1] as $label) {
                $label = trim($label);
                if ($label !== '' && !in_array($label, $labels)) {
                    $labels[] = $label;
                }
            }
        }
        return $labels;
    }

    /**
     * Возвращает имя задачи без ведущих меток.
     * @param $issueName Имя задачи.
     * @return string Заголовок задачи: то, что остаётся от имени, если убрать
     *                метки в его начале. Пустая строка, если кроме меток
     *                в имени ничего нет.
     */
    public static function getNameWithoutLabels($issueName)
    {
        $name = trim($issueName);
        if (mb_substr($name, 0, 1) !== '[') return $name;

        return trim(preg_replace(self::LABELS_PATTERN, '', $name));
    }

    /**
     * Проверяет, что в имени задачи есть заголовок, а не только метки.
     * @param $issueName Имя задачи.
     * @return bool
     */
    public static function hasTitle($issueName)
    {
        return self::getNameWithoutLabels($issueName) !== '';
    }

    /**
     * Проверяет, что в имени задачи указана хотя бы одна метка.
     * @param $issueName Имя задачи.
     * @return bool
     */
    public static function hasLabels($issueName)
    {
        return !empty(self::getLabelsByName($issueName));
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
     *
     * Вложения задачи и её комментариев при этом удаляются безвозвратно:
     * удалённую задачу ни один загрузчик больше не отдаёт, поэтому добраться
     * до её вложений уже нельзя.
     */
    public static function remove(User $user, Issue $issue)
    {
        $db = self::getDB();
        $sql = "update `%s` set `deleted` = '1' where `id` = '" . $issue->id . "'";
        if (!$db->queryt($sql, LPMTables::ISSUES)) {
            throw new Exception('Remove issue failed', \GMFramework\ErrorCode::SAVE_DATA);
        }

        Project::updateIssuesCount($issue->projectId);

        UploadsCleanupManager::removeIssueUploads($issue->id);

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
     *
     * Номер задачи внутри проекта («последний плюс один») вычисляется тем же
     * запросом, который вставляет задачу, — иначе два одновременных создания
     * в одном проекте выберут один и тот же номер. Пара «проект + номер»
     * уникальна в базе: если номер всё же занят, вставка отклоняется,
     * ничего после себя не оставляя, и повторяется — до ISSUE_CREATE_MAX_ATTEMPTS раз.
     *
     * Значения передаются в «сыром» виде — экранирование выполняется внутри метода.
     * @return int|null Идентификатор созданной задачи или null при ошибке записи,
     * в том числе если свободный номер не удалось занять за отведённое число попыток.
     */
    public static function createNew(Project $project, $name, $desc, $type, $priority, $hours, $completeDate, $authorId)
    {
        $db = self::getDB();
        $revision = self::getNewRevision();

        // Экранируем строки и удваиваем `%`, т.к. queryt() пропускает запрос через sprintf.
        $nameEsc = $db->real_escape_string(str_replace('%', '%%', (string)$name));
        $descEsc = $db->real_escape_string(str_replace('%', '%%', (string)$desc));
        $revisionEsc = $db->real_escape_string($revision);
        $completeDateSql = empty($completeDate)
            ? 'NULL'
            : "'" . $db->real_escape_string($completeDate) . "'";

        $projectId = (int)$project->id;
        $sql = "INSERT INTO `%1\$s` (`projectId`, `idInProject`, `name`, `hours`, `desc`, `type`, " .
                                    "`authorId`, `createDate`, `completeDate`, `priority`, `revision` ) " .
                            "SELECT '" . $projectId . "', COALESCE(MAX(`idInProject`), 0) + 1, " .
                                        "'" . $nameEsc . "', '" . (float)$hours . "', '" . $descEsc . "', " .
                                        "'" . (int)$type . "', '" . (int)$authorId . "', " .
                                        "'" . DateTimeUtils::mysqlDate() . "', " . $completeDateSql . ", " .
                                        "'" . (int)$priority . "', '" . $revisionEsc . "' " .
                              "FROM `%1\$s` WHERE `projectId` = '" . $projectId . "'";

        $issueId = 0;
        for ($attempt = 1; $attempt <= ISSUE_CREATE_MAX_ATTEMPTS; $attempt++) {
            // Пауза со случайной длительностью разводит во времени запросы,
            // столкнувшиеся на одном номере: без неё они повторяют попытку
            // одновременно и снова выбирают один и тот же номер.
            if ($attempt > 1) {
                usleep(mt_rand(1, 5) * 1000 * ($attempt - 1));
            }

            if ($db->queryt($sql, LPMTables::ISSUES)) {
                $issueId = (int)$db->insert_id;
                break;
            }

            // ER_DUP_ENTRY — номер занял параллельный запрос; любая другая
            // ошибка записи повтором не лечится.
            if ($db->errno != self::DB_ERR_DUP_ENTRY) {
                return null;
            }
        }

        if (empty($issueId)) {
            LPMLog::error(
                'Не удалось занять свободный номер задачи в проекте',
                LPMLog::CH_DB,
                ['projectId' => $projectId, 'attempts' => ISSUE_CREATE_MAX_ATTEMPTS]
            );
            return null;
        }

        // Начальный слепок содержимого — с него начинается история задачи.
        IssueContentSnapshot::record($issueId, $authorId);

        return $issueId;
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
        $issue->priority = self::normalizePriority($issue->priority + $delta);
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
     * Возвращает номер группы приоритета для указанного значения приоритета.
     *
     * Группы нумеруются от 0 (самый низкий приоритет) и по возрастанию
     * приоритета, каждая охватывает диапазон в `PRIORITY_GROUP_STEP` процентов.
     * @param  int $priority Значение приоритета.
     * @return int Номер группы приоритета.
     */
    public static function getPriorityGroupByValue($priority)
    {
        return (int)floor(self::normalizePriority($priority) / self::PRIORITY_GROUP_STEP);
    }

    /**
     * Возвращает отображаемое название статуса задачи.
     * @param  int $status Статус задачи.
     * @return string Название. Пустая строка для неизвестного статуса.
     */
    public static function getStatusName($status)
    {
        switch ((int)$status) {
            case self::STATUS_IN_WORK: return 'В работе';
            case self::STATUS_WAIT: return 'Ожидает проверки';
            case self::STATUS_COMPLETED: return 'Завершена';
            default: return '';
        }
    }

    /**
     * Возвращает подпись диапазона приоритетов группы, например `71–75`.
     * @param  int $group Номер группы приоритета.
     * @return string
     */
    public static function getPriorityGroupLabel($group)
    {
        $min = $group * self::PRIORITY_GROUP_STEP;
        $max = $min + self::PRIORITY_GROUP_STEP - 1;
        return self::getPriorityDisplayValueBy($min) . '–'
            . self::getPriorityDisplayValueBy($max);
    }

    /**
     * Возвращает отображаемое значение приоритета (в процентах).
     * @param  int $priority Значение приоритета.
     * @return int Значение от 1 до 100.
     */
    public static function getPriorityDisplayValueBy($priority)
    {
        return self::normalizePriority($priority) + 1;
    }

    /**
     * Переводит отображаемое значение приоритета во внутреннее.
     *
     * Обратная операция к {@see getPriorityDisplayValueBy()}: интерфейс и
     * внешнее API работают со шкалой 1..100, хранится значение 0..`MAX_PRIORITY`.
     * @param  int $displayValue Отображаемое значение приоритета.
     * @return int Значение от 0 до `MAX_PRIORITY`.
     */
    public static function priorityFromDisplayValue($displayValue)
    {
        return self::normalizePriority((int)$displayValue - 1);
    }

    /**
     * Приводит значение приоритета к допустимому диапазону.
     * @param  int $priority Значение приоритета.
     * @return int Значение от 0 до `MAX_PRIORITY`.
     */
    public static function normalizePriority($priority)
    {
        return (int)max(0, min((int)$priority, self::MAX_PRIORITY));
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

    /**
     * Метка в начале имени задачи: блок в квадратных скобках и пробелы за ним.
     *
     * Шаблон привязан к началу поиска (`A`), поэтому подряд идущие совпадения -
     * это ровно те метки, с которых начинается имя. Должен совпадать
     * с разбором меток в issue-form.js.
     */
    const LABELS_PATTERN = '/\[([^\]]*)\]\s*/uA';
    
    /**
     * Максимальное значение приоритета — отображается как 100%.
     */
    const MAX_PRIORITY = 99;

    /**
     * Шаг (в процентах), с которым задачи разбиваются на группы по приоритету.
     */
    const PRIORITY_GROUP_STEP = 5;

    /**
     * Код ошибки MySQL «дубликат значения уникального ключа» (ER_DUP_ENTRY).
     */
    const DB_ERR_DUP_ENTRY = 1062;
    
    public $id            =  0;
    public $projectId     =  0;
    public $projectName  = ''; /*для загрузки задач по нескольким проектам*/
    public $idInProject   =  0;
    public $projectUID    = '';
    /**
     * Использует ли проект задачи Scrum доску.
     *
     * Заполняется вместе с задачей в общей выборке, чтобы вид задачи
     * не грузил ради этого весь проект.
     * @var bool
     */
    public $projectScrum  = false;
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
     * Дата последней активности по задаче в тесте.
     *
     * Для задачи с активным багом это дата последнего бага, иначе - дата
     * последнего комментария; если комментариев нет - дата изменения задачи.
     * Заполняется только для задач в тесте, для остальных 0.
     * @var float
     */
    public $testActivityDate = 0;

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
     * Стоит ли на задаче отметка о прохождении тестирования.
     *
     * Отметку ставит комментарий типа {@see IssueCommentType::PASS_TEST},
     * а снимает любой более свежий комментарий с багом или с MR - поэтому
     * отметка может и вернуться, если такой комментарий удалили или его баг
     * пометили решённым.
     *
     * Признак не зависит от статуса задачи: он остаётся и после завершения.
     * Если нужно «задача сейчас в тесте и уже прошла его» - это подстатус
     * {@see IssueSubstatus::PASS_TEST}, а не этот флаг; для показа
     * пользователю почти всегда нужен именно подстатус.
     *
     * Если null, то это означает, что данные не загружены.
     * @see getSubstatus()
     * @var bool
     */
    public $hasPassTestMark;

    /**
     * Задача ожидает внесения изменений.
     *
     * Задача ушла в тест, при тестировании обнаружены проблемы
     * и в данный момент задача в состоянии ожидания внесения правок.
     *
     * В отличие от {@see $hasPassTestMark}, признак ограничен статусом:
     * у задачи не в тесте он всегда false.
     *
     * Если null, то это означает, что данные не загружены.
     * @var bool
     */
    public $isChangesRequested;

    /**
     * Задачу проверяют прямо сейчас.
     *
     * Признак поднимает событие журнала
     * {@see IssueEventType::TAKEN_FOR_TESTING}, а снимает либо событие
     * {@see IssueEventType::RELEASED_FROM_TESTING}, либо более свежая отметка
     * о прохождении теста, о баге или о MR: задача остаётся за тем, кто её
     * взял, но проверка уже не идёт.
     *
     * Как и {@see $isChangesRequested}, признак ограничен статусом:
     * у задачи не в тесте он всегда false.
     *
     * Если null, то это означает, что данные не загружены.
     * @var bool
     */
    public $isUnderTesting;

    /**
     * Состояние правок по задаче в тесте: состояние MR задачи
     * (см. GitlabMergeRequest::STATE_*).
     *
     * Если по задаче несколько MR - берётся самый незавершённый: пока
     * есть открытый MR, правки ещё не влиты.
     *
     * Если null, то это означает, что данных нет: задача не в тесте,
     * по ней нет ни одного MR либо данные не загружены.
     * @var string
     */
    public $testMrState;

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
        $this->_typeConverter->addBoolVars(
            'isOnBoard',
            'isBaseLinked',
            'hasPassTestMark',
            'isChangesRequested',
            'isUnderTesting',
            'projectScrum'
        );
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
        return self::getPriorityDisplayValueBy($this->priority);
    }

    /**
     * Возвращает номер группы приоритета, к которой относится задача.
     * @return int
     */
    public function getPriorityGroup()
    {
        return self::getPriorityGroupByValue($this->priority);
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
     * Снимает отметку о взятии задачи в тестирование, если тот, кто проверяет
     * задачу, больше не её тестировщик.
     *
     * Взятие задачи добавляет взявшего в тестировщики, поэтому исключение
     * из тестировщиков означает, что задачу он больше не проверяет. Отметка
     * снимается тем же событием, что и вручную.
     * @param  float $byUserId Идентификатор пользователя, снимающего отметку.
     * @return bool Была ли снята отметка.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать событие.
     */
    public function releaseFromTestingIfNotTester($byUserId)
    {
        if (!$this->isUnderTesting) {
            return false;
        }

        $event = self::loadLastTestingEvent($this->id);
        if (empty($event) || $this->isTester($event->userId)) {
            return false;
        }

        IssueEvent::create($this->id, IssueEventType::RELEASED_FROM_TESTING, $byUserId);
        $this->isUnderTesting = false;

        return true;
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
    
    /**
     * Возвращает количество полных дней без активности по задаче в тесте.
     *
     * Если задача не в тесте или дата активности неизвестна - вернет null.
     * @return int|null
     */
    public function daysWithoutTestActivity()
    {
        if (!$this->isTesting() || empty($this->testActivityDate)) {
            return null;
        }

        // Считаем по календарным дням, чтобы сегодняшняя активность давала 0 дней
        $diff = DateTimeUtils::dayStart() - DateTimeUtils::dayStart('U', $this->testActivityDate);
        return $diff > 0 ? (int)round($diff / 86400) : 0;
    }

    /**
     * Возвращает дату последней активности по задаче в тесте.
     * @return string
     */
    public function getTestActivityDate()
    {
        return self::getDateTimeStr($this->testActivityDate);
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
        return self::getStatusName($this->status);
    }

    /**
     * Уточнение статуса задачи: на каком этапе она внутри своего статуса.
     *
     * Подстатус не хранится, а выводится из данных, которые приезжают вместе
     * с задачей в общей выборке, поэтому дополнительного запроса на задачу
     * не делается.
     *
     * Задачу «В работе» уточняет состояние её стикера на Scrum доске: задача
     * без стикера считается лежащей в бэклоге, стикер в колонке «К выполнению»
     * даёт TODO, остальные - IN_PROGRESS. В проектах без Scrum уточнения нет.
     *
     * Задачу «Ожидает проверки» уточняют отметки о тестировании: о том, что
     * её проверяют сейчас ({@see $isUnderTesting}), и о прохождении теста
     * ({@see $hasPassTestMark}). Побеждает первая: она по построению свежее
     * отметки о прохождении теста, а значит задачу перепроверяют и прежний
     * результат уже неактуален. Подстатус, в отличие от самих
     * отметок, ограничен статусом: у завершённой задачи отметка остаётся,
     * а подстатуса уже нет - «Завершена» говорит больше, чем «прошла
     * тестирование».
     * @return int Подстатус.
     * @see IssueSubstatus
     * @see reloadSubstatusSources()
     */
    public function getSubstatus()
    {
        if ($this->isTesting()) {
            if ($this->isUnderTesting) {
                return IssueSubstatus::UNDER_TESTING;
            }

            return $this->hasPassTestMark
                ? IssueSubstatus::PASS_TEST
                : IssueSubstatus::NONE;
        }

        if ($this->status != self::STATUS_IN_WORK || !$this->projectScrum) {
            return IssueSubstatus::NONE;
        }

        $sticker = $this->getSticker();
        if (empty($sticker) || !$sticker->isOnBoard()) {
            return IssueSubstatus::BACKLOG;
        }

        return $sticker->isTodo() ? IssueSubstatus::TODO : IssueSubstatus::IN_PROGRESS;
    }

    /**
     * Перечитывает данные, из которых выводится подстатус задачи.
     *
     * Нужен, когда задача уже загружена, а потом изменилось что-то, от чего
     * подстатус зависит: комментарии задачи, её статус или стикер на доске.
     * Перечитывается только то, что нужно при текущем статусе задачи, поэтому
     * запрос будет один, а у завершённой задачи - ни одного.
     *
     * Набор источников должен совпадать с тем, что читает {@see getSubstatus()}.
     */
    public function reloadSubstatusSources()
    {
        if ($this->isTesting()) {
            $row = self::loadTestStateComment($this->id);
            $this->applyTestState(
                empty($row) ? null : $row['type'],
                empty($row) ? null : $row['date'],
                self::loadTakenForTestingDate($this->id)
            );
            return;
        }

        if ($this->status == self::STATUS_IN_WORK && $this->projectScrum) {
            $sticker = ScrumSticker::load($this->id);
            // Загрузчик отдаёт false, а отсутствие стикера здесь - это null
            $this->_sticker = empty($sticker) ? null : $sticker;
        }
    }

    /**
     * Раскладывает состояние задачи в тесте по отметкам.
     * @param string|null $testState     Тип комментария, задающего состояние
     * (@see IssueCommentType), либо null, если отметок нет.
     * @param string|null $testStateDate Дата этого комментария.
     * @param string|null $takenAt       Дата, с которой задача взята
     * в тестирование, либо null, если отметки о взятии нет.
     */
    private function applyTestState($testState, $testStateDate = null, $takenAt = null)
    {
        $this->hasPassTestMark = $testState == IssueCommentType::PASS_TEST;
        $this->isChangesRequested = $this->isTesting()
            && $testState == IssueCommentType::REQUEST_CHANGES;
        // Даты в формате MySQL сравниваются как строки: формат фиксированной ширины.
        // Отметка о проверке той же секунды считается более свежей - взятие
        // потеряло смысл, как только по задаче появился результат
        $this->isUnderTesting = $this->isTesting()
            && !empty($takenAt)
            && (empty($testStateDate) || $takenAt > $testStateDate);
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

        if (array_key_exists('t_testState', $hash)) {
            $this->applyTestState(
                $hash['t_testState'],
                isset($hash['t_testStateDate']) ? $hash['t_testStateDate'] : null,
                isset($hash['t_takenAt']) ? $hash['t_takenAt'] : null
            );
        }

        if (isset($hash['t_mrState'])) {
            $this->testMrState = $hash['t_mrState'];
        }

        if ($this->isTesting() && array_key_exists('t_lastCommentDate', $hash)) {
            $date = $this->isChangesRequested ? $hash['t_lastBugDate'] : $hash['t_lastCommentDate'];
            $this->testActivityDate = empty($date)
                ? max($this->createDate, $this->modifiedDate)
                : (float)DateTimeUtils::convertMysqlDate($date);
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
