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
		return self::loadListByInstance( Project::ITYPE_PROJECT, $projectId );
	}
	
	public static function loadListByIssue( $issueId ) {
		return self::loadListByInstance( Issue::ITYPE_ISSUE, $issueId );
	}
	
	/*const ITYPE_ISSUE   = 1;
	const ITYPE_PROJECT = 2;*/
}
?>