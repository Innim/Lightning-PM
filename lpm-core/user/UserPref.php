<?php
class UserPref extends LPMBaseObject
{
    /**
     * Сохраняет настройки личной scrum доски.
     * @param  int       $userId          Идентификатор пользователя.
     * @param  int       $role            Фильтр по роли, {@see ScrumBoardRoleFilter}.
     * @param  bool|null $showFreeIssues  Показывать ли свободные задачи;
     *                                    `null` - оставить прежнее значение,
     *                                    т.к. для выбранной роли настройка не применима.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить.
     */
    public static function saveMyBoardPref($userId, $role, $showFreeIssues)
    {
        $set = ['myBoardRole' => ScrumBoardRoleFilter::sanitize($role)];
        if ($showFreeIssues !== null) {
            $set['showFreeIssuesOnBoard'] = $showFreeIssues ? 1 : 0;
        }

        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::USERS_PREF,
            'SET'    => $set,
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
    /**
     * По какой роли пользователя в задаче отбирается его личная scrum доска.
     * @var int
     * @see ScrumBoardRoleFilter
     */
    public $myBoardRole = ScrumBoardRoleFilter::ANY;
    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addFloatVars('userId');
        $this->_typeConverter->addIntVars('myBoardRole');
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
