<?php
class UserPref extends LPMBaseObject
{
    public $userId = -1;
    public $seAddIssue     = false;
    public $seEditIssue    = false;
    public $seIssueState   = false;
    public $seIssueComment = false;
    public $seAddIssueForPM = false;
    public $seEditIssueForPM = false;
    public $seIssueStateForPM = false;
    public $seIssueCommentForPM = false;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->_typeConverter->addFloatVars('userId');
        $this->_typeConverter->addBoolVars(
            'seAddIssue',
            'seEditIssue',
            'seIssueState',
            'seIssueComment',
            'seAddIssueForPM',
            'seEditIssueForPM',
            'seIssueStateForPM',
            'seIssueCommentForPM'
        );
    }
}
