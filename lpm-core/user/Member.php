<?php
class Member extends User
{
	public static function loadListByInstance( $instanceType, $instanceId ) {
		$sql = "select * from `%1\$s`, `%2\$s` " .
					   "where `%1\$s`.`instanceId`   = '" . $instanceId   . "' " .
						 "and `%1\$s`.`instanceType` = '" . $instanceType . "' " .
						 "and `%1\$s`.`userId`       = `%2\$s`.`userId`";
		return StreamObject::loadObjList( self::getDB(), array( $sql, LPMTables::MEMBERS, LPMTables::USERS ), __CLASS__ );		
	}
	
	public static function loadListByProject( $projectId ) {
		return self::loadListByInstance( LPMInstanceTypes::PROJECT, $projectId );
	}
	
	public static function loadListByIssue($issueId) {
		return self::loadListByInstance(LPMInstanceTypes::ISSUE, $issueId);
	}

	public static function hasIssueMember($issueId, $userId) {
		return self::hasMember(LPMInstanceTypes::ISSUE, $issueId, $userId);
	}

	public static function hasMember($instanceType, $instanceId, $userId) {
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

		if (!$res) 
	    	throw new Exception('Load has member', \GMFramework\ErrorCode::LOAD_DATA);

        $row = $res->fetch_row();
		return !empty($row) && (int)$row[0] == 1;
	}

	public static function deleteIssueMembers($issueId, $userIds = null) {
		return self::deleteMembers(LPMInstanceTypes::ISSUE, $issueId, $userIds);
	}

	public static function deleteMembers($instanceType, $instanceId, $userIds = null) {
	    $hash = [
			'DELETE' => LPMTables::MEMBERS,
			'WHERE' => [
				'`instanceId` = ' . $instanceId,
				'`instanceType` = ' . $instanceType,
				// 'instanceId' => $instanceId,
				// 'instanceType' => $instanceType
			]
		];

		if ($userIds !== null)
			$hash['WHERE'][] = '`userId` IN (' . implode(',', $userIds) . ')';

		return self::getDB()->queryb($hash);
	    	//throw new Exception('Delete members error', \GMFramework\ErrorCode::SAVE_DATA);
	}

	public static function saveIssueMembers($issueId, $userIds) {
		return self::saveMembers(LPMInstanceTypes::ISSUE, $issueId, $userIds);
	}

	public static function saveMembers($instanceType, $instanceId, $userIds) {
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
?>