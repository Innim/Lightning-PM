<?php
class Member extends User
{
    public static function loadListByInstance(
        $instanceType,
        $instanceId,
        $onlyNotLocked = false,
        $leftJoinTable = null,
        $class = null
    )
    {
        $sql = "select * from `%2\$s`, `%1\$s` ";
        if ($leftJoinTable) {
            $sql .= "LEFT JOIN `%3\$s` USING (`userId`, `instanceId`) ";
        }
        $sql .= "where `%1\$s`.`instanceId`   = '" . $instanceId   . "' " .
                         "and `%1\$s`.`instanceType` = '" . $instanceType . "' " .
                         "and `%1\$s`.`userId`       = `%2\$s`.`userId`";
        if ($onlyNotLocked) {
            $sql .= " and `%2\$s`.`locked` = 0";
        }
        $query = [$sql, LPMTables::MEMBERS, LPMTables::USERS];
        if ($leftJoinTable) {
            $query[] = $leftJoinTable;
        }

        // exit('<pre>' . self::getDB()->sprintft($query));
        return StreamObject::loadObjList(self::getDB(), $query, empty($class) ? __CLASS__ : $class);
    }
    
    public static function loadListByProject($projectId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::PROJECT, $projectId);
    }
    
    public static function loadListByIssue($issueId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(
            LPMInstanceTypes::ISSUE,
            $issueId,
            false,
            LPMTables::ISSUE_MEMBER_INFO,
            'IssueMember'
        );
    }

    public static function loadListByIssueForTest($issueId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::ISSUE_FOR_TEST, $issueId);
    }

    public static function loadTesterForProject($projectId, $onlyNotLocked = false)
    {
        return self::loadListByInstance(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId);
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


    public static function deleteMembers($instanceType, $instanceId, $userIds = null)
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

        return self::getDB()->queryb($hash);
        //throw new Exception('Delete members error', \GMFramework\ErrorCode::SAVE_DATA);
    }

    public static function saveIssueMembers($issueId, $userIds)
    {
        return self::saveMembers(LPMInstanceTypes::ISSUE, $issueId, $userIds);
    }

    public static function saveProjectForTester($projectId, $userId)
    {
        return self::saveMembers(LPMInstanceTypes::TESTER_FOR_PROJECT, $projectId, [$userId]);
    }

    public static function saveMembers($instanceType, $instanceId, $userIds)
    {
        $values = [];
        foreach ($userIds as $userId) {
            $values[] = [$userId, $instanceType, $instanceId];
        }

        $hash = [
            'REPLACE' => ['userId', 'instanceType', 'instanceId'],
            'INTO' => LPMTables::MEMBERS,
            'VALUES' => $values
        ];
        return self::getDB()->queryb($hash);
    }
}
