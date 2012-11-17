<?php
require_once( dirname( __FILE__ ) . '/../init.inc.php' );

class ProfileService extends LPMBaseService
{
	public function emailPref( $addIssue, $editIssue, $issueState, $issueComment ) 
	{
		$sql = "UPDATE `%s` SET " .
		        "`seAddIssue` = '" . (boolean)$addIssue . "', " .
				"`seEditIssue` = '" . (boolean)$editIssue . "', " .
				"`seIssueState` = '" . (boolean)$issueState . "', " .
				"`seIssueComment` = '" . (boolean)$issueComment . "' " .
			    "WHERE `userId` = '" . $this->_auth->getUserId() . "'";
		
		if (!$this->_db->queryt( $sql, LPMTables::USERS_PREF )) 
			return $this->error( 'Ошибка записи в БД' );
		
		return $this->answer(); 
	} 
}
?>