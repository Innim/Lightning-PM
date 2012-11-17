<?php
class UserPref extends LPMBaseObject
{
	public $userId = -1;
	public $seAddIssue     = false;
	public $seEditIssue    = false;
	public $seIssueState   = false;
	public $seIssueComment = false;
	
	function __construct()
	{
		parent::__construct();	
		
		$this->_typeConverter->addFloatVars( 'userId' );
		$this->_typeConverter->addBoolVars( 
			'seAddIssue', 'seEditIssue', 'seIssueState', 'seIssueComment'
		);
	}
}
?>