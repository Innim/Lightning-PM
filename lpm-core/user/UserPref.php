<?php
class UserPref extends LPMBaseObject
{
    /**
     * Сохраняет признак показа свободных задач на личной scrum доске.
     * @param  int  $userId Идентификатор пользователя.
     * @param  bool $value  Показывать ли свободные задачи.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить.
     */
    public static function saveShowFreeIssuesOnBoard($userId, $value)
    {
        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::USERS_PREF,
            'SET'    => ['showFreeIssuesOnBoard' => $value ? 1 : 0],
            'WHERE'  => ['userId' => (int)$userId],
        ]);
    }

    public $userId = -1;
    public $seAddIssue     = false;
    public $seEditIssue    = false;
    public $seIssueState   = false;
    public $seIssueComment = false;
    public $seAddIssueForPM = false;
    public $seEditIssueForPM = false;
    public $seIssueStateForPM = false;
    public $seIssueCommentForPM = false;
    /**
     * На личной scrum доске показываются не только задачи пользователя,
     * но и свободные задачи из его проектов.
     * @var bool
     */
    public $showFreeIssuesOnBoard = false;
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
            'seIssueCommentForPM',
            'showFreeIssuesOnBoard'
        );
    }
}
