<?php
class Member extends User
{
    public static function loadListByInstance(
        $instanceTypes,
        $instanceId,
        $onlyNotLocked = false,
        $leftJoinTables = null,
        $class = null,
        $userId = null,
        $conditions = null
    ) {
        
        $tables = [LPMTables::MEMBERS, LPMTables::USERS];

        $selectSql = '`u`.*, `m`.*';
        $joinSql = '';
        if ($leftJoinTables) {
            $i = 0;
            foreach ($leftJoinTables as $table => $hash) {
                $s = count($tables) + 1;
                $alias = empty($hash[0]) ? "t_$i" : $hash[0];
                
                $joinSql .= " LEFT JOIN `%$s\$s` `$alias`";
                
                if (!empty($hash['USING'])) {
                    $joinSql .= ' USING (`' . implode('`, `', $hash['USING']) . '`)';
                } else if (!empty($hash['ON'])) {
                    $joinSql .= ' ON ' . $hash['ON'];
                }

                if (!empty($hash['SELECT'])) {
                    $selectFields = array_map(function ($v) use ($alias) {
                        if ($v != '*' && substr($v, 0, 1) !== '`') $v = '`' . $v . '`';
                        return "`$alias`.$v";
                    }, (array)$hash['SELECT']);
                    $selectSql .= ", " . implode(', ',  $selectFields);
                }

                $tables[] = $table;
                $i++;
            }
        }

        $sql = "SELECT " . $selectSql . " FROM `%2\$s` `u`, `%1\$s` `m`" . $joinSql . 
               " WHERE `m`.`userId`       = `u`.`userId`";

        $sql .= " AND `m`.`instanceType`";
        if (is_array($instanceTypes)) {
            $sql .= " IN (" . implode(', ', $instanceTypes) . ")";
        } else {
            $sql .= " = '" . (int)$instanceTypes . "'";
        }
        
        if ($instanceId != null) {
            $sql .= " AND `m`.`instanceId` = " . $instanceId;
        }

        if ($userId != null) {
            $sql .= " AND `m`.`userId` = " . $userId;
        }

        if ($onlyNotLocked) {
            $sql .= " AND `u`.`locked` = 0";
        }

        if ($conditions != null) {
            $sql .= " AND ($conditions)";
        }


        $query = array_merge([$sql], $tables);

        return StreamObject::loadObjList(self::getDB(), $query, empty($class) ? __CLASS__ : $class);
    }
    
    public static function loadListByProject($projectId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::PROJECT, $projectId, $onlyNotLocked);
    }

    public static function loadListAnyForIssuesInProject($projectId, $issueStatus = null, $loadMembers = true, $loadTesters = true, $loadMasters = true) 
    {
        $types = [];
        if ($loadMembers) $types[] = LPMInstanceTypes::ISSUE;
        if ($loadTesters) $types[] = LPMInstanceTypes::ISSUE_FOR_TEST;
        if ($loadMasters) $types[] = LPMInstanceTypes::ISSUE_FOR_MASTER;
        if (empty($types)) return [];

        return self::loadListForIssue($types, $projectId, $issueStatus);
    }
    
    public static function loadListByIssue($issueId, $onlyNotLocked = false)
    {
        return self::loadListByIssueInstance($issueId, $onlyNotLocked);
    }
    
    /**
     * Загружает конкретного участника по ID задачи.
     * @param int $issueId
     * @param int $userId
     * @return IssueMember Данные участника или null, если участник не найден.
     */
    public static function loadByIssue($issueId, $userId)
    {
        $list = self::loadListByIssueInstance($issueId, false, $userId);
        return empty($list) ? null : $list[0];
    }

    public static function loadListByIssueForTest($issueId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::ISSUE_FOR_TEST, $issueId, $onlyNotLocked);
    }

    public static function loadTesterForProject($projectId, $onlyNotLocked = false)
    {
        $list = self::loadListByInstance(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId, $onlyNotLocked);
        return empty($list) ? null : $list[0];
    }

    public static function loadPMForProject($projectId, $onlyNotLocked = false)
    {
        $list = self::loadListByInstance(LPMInstanceTypes::PM_FOR_PROJECT, $projectId, $onlyNotLocked);
        return empty($list) ? null : $list[0];
    }

    /**
     * Загружает список мастеров, назначенных конкретной задаче.
     * @return array<Member>
     */
    public static function loadMastersForIssue($issueId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::ISSUE_FOR_MASTER, $issueId, $onlyNotLocked);
    }

    /**
     * Загружает список специализированных мастеров для конкретного проекта.
     * @return array<Member>
     */
    public static function loadSpecMastersForProject($projectId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::PROJECT_FOR_SPEC_MASTER, $projectId, $onlyNotLocked);
    }

    /**
     * Загружает список специализированных тестеров для конкретного проекта.
     * @return array<Member>
     */
    public static function loadSpecTestersForProject($projectId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::PROJECT_FOR_SPEC_TESTER, $projectId, $onlyNotLocked);
    }

    public static function hasIssueMember($issueId, $userId)
    {
        return self::hasMember(LPMInstanceTypes::ISSUE, $issueId, $userId);
    }

    public static function hasMember($instanceType, $instanceId, $userId)
    {
        $hash = [
            'SELECT' => 1,
            'FROM' => LPMTables::MEMBERS,
            'WHERE' => [
                'instanceId' => $instanceId,
                'instanceType' => $instanceType,
                'userId' => $userId
            ]
        ];

        $res = self::getDB()->queryb($hash);

        if (!$res) {
            throw new Exception('Load has member', \GMFramework\ErrorCode::LOAD_DATA);
        }

        $row = $res->fetch_row();
        return !empty($row) && (int)$row[0] == 1;
    }

    public static function deleteIssueMembers($issueId, $userIds = null)
    {
        return self::deleteMembers(LPMInstanceTypes::ISSUE, $issueId, $userIds);
    }

    public static function deleteIssueTesters($issueId, $userIds = null)
    {
        return self::deleteMembers(LPMInstanceTypes::ISSUE_FOR_TEST, $issueId, $userIds);
    }

    public static function deleteProjectSpecMaster($projectId, $userId, $labelId)
    {
        return self::deleteMembers(LPMInstanceTypes::PROJECT_FOR_SPEC_MASTER, $projectId, [$userId], $labelId);
    }

    public static function deleteProjectSpecTester($projectId, $userId, $labelId)
    {
        return self::deleteMembers(LPMInstanceTypes::PROJECT_FOR_SPEC_TESTER, $projectId, [$userId], $labelId);
    }

    public static function deleteMembers($instanceType, $instanceId, $userIds = null, $extraId = null)
    {
        $hash = [
            'DELETE' => LPMTables::MEMBERS,
            'WHERE' => [
                '`instanceId` = ' . $instanceId,
                '`instanceType` = ' . $instanceType,
                // 'instanceId' => $instanceId,
                // 'instanceType' => $instanceType
            ]
        ];

        if ($userIds !== null) {
            $hash['WHERE'][] = '`userId` IN (' . implode(',', $userIds) . ')';
        }

        if ($extraId !== null) {
            $hash['WHERE'][] = '`extraId` = ' . $extraId;
        }

        return self::getDB()->queryb($hash);
    }

    public static function saveIssueMembers($issueId, $userIds)
    {
        return self::saveMembers(LPMInstanceTypes::ISSUE, $issueId, $userIds);
    }

    public static function saveIssueTesters($issueId, $userIds)
    {
        return self::saveMembers(LPMInstanceTypes::ISSUE_FOR_TEST, $issueId, $userIds);
    }

    public static function saveIssueMasters($issueId, $userIds)
    {
        return self::saveMembers(LPMInstanceTypes::ISSUE_FOR_MASTER, $issueId, $userIds);
    }

    public static function saveTesterForProject($projectId, $userId)
    {
        return self::saveMembers(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId, [$userId]);
    }

    public static function saveProjectPM($projectId, $userId)
    {
        return self::saveMembers(LPMInstanceTypes::PM_FOR_PROJECT, $projectId, [$userId]);
    }

    public static function saveProjectSpecMaster($projectId, $userId, $labelId)
    {
        return self::saveMembers(LPMInstanceTypes::PROJECT_FOR_SPEC_MASTER, $projectId, [$userId], $labelId);
    }

    public static function saveProjectSpecTester($projectId, $userId, $labelId)
    {
        return self::saveMembers(LPMInstanceTypes::PROJECT_FOR_SPEC_TESTER, $projectId, [$userId], $labelId);
    }

    public static function saveMembers($instanceType, $instanceId, $userIds, $extraId = 0)
    {
        $values = [];
        foreach ($userIds as $userId) {
            $values[] = [$userId, $instanceType, $instanceId, $extraId];
        }

        $hash = [
            'REPLACE' => ['userId', 'instanceType', 'instanceId', 'extraId'],
            'INTO' => LPMTables::MEMBERS,
            'VALUES' => $values
        ];
        return self::getDB()->queryb($hash);
    }

    private static function loadListByIssueInstance(
        $issueId,
        $onlyNotLocked = false,
        $userId = null
    ) {
        return self::loadListByInstance(
            LPMInstanceTypes::ISSUE,
            $issueId,
            $onlyNotLocked,
            [
                LPMTables::ISSUE_MEMBER_INFO => self::getIssueMemberInfoJoin(),
            ],
            'IssueMember',
            $userId
        );
    }

    private static function loadListForIssue(
        $instanceTypes,
        $projectId,
        $issueStatus = null,
        $onlyNotLocked = false,
        $userId = null
    ) {
        $issueWhere = "`i`.`projectId` = '" . $projectId . "'";
        if (!empty($issueStatus)) {
            $issueWhere .= " AND `i`.`status` IN (" . implode(',', $issueStatus) . ')';
        }

        return self::loadListByInstance(
            $instanceTypes,
            null,
            $onlyNotLocked,
            [
                LPMTables::ISSUE_MEMBER_INFO => self::getIssueMemberInfoJoin(),
                LPMTables::ISSUES => [
                    'i',
                    'ON' => '`m`.`instanceId` = `i`.`id`'
                ]
            ],
            'IssueMember',
            $userId,
            $issueWhere,
        );
    }

    private static function getIssueMemberInfoJoin() {
        return [
            'SELECT' => 'sp',
            'USING' => ['userId', 'instanceId'], 
        ];
    }

    public $instanceType;
    public $instanceId;

    /**
     * Дополнительный идентификатор, определяющий связь.
     *
     * Хранимое значение определяется контекстом.
     */
    public $extraId;

    public function __construct()
    {
        parent::__construct();
        
        $this->_typeConverter->addIntVars('instanceType', 'instanceId', 'extraId');
    }
}
