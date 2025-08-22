<?php

/**
 * Блокировка какой-либо сущности пользователем.
 */
class UserLock extends LPMBaseObject
{
    const DEFAULT_ISSUE_LOCK_DURATION_SEC = 10 * 60;

    public static function createIssueLock($userId, $issueId, $durationSeconds = self::DEFAULT_ISSUE_LOCK_DURATION_SEC)
    {
        $expiredUnixtime = DateTimeUtils::$currentDate + $durationSeconds;
        $expired = DateTimeUtils::mysqlDate($expiredUnixtime);
        self::create($userId, LPMInstanceTypes::ISSUE, $issueId, $expired);
    }

    protected static function create($userId, $instanceType, $instanceId, $expired)
    {
        $date = DateTimeUtils::mysqlDate();
        $hash = [
            'INSERT' => compact('userId', 'instanceId', 'instanceType', 'date', 'expired'),
            'INTO'    => LPMTables::USER_LOCKS
        ];

        self::buildAndSaveToDbV2($hash);
    }

    public static function removeIssueLocks($issueId) 
    {
        return self::removeLocks(LPMInstanceTypes::ISSUE, $issueId);
    }

    protected static function removeLocks($instanceType, $instanceId)
    {
        return self::buildAndExecute([
            'UPDATE' => LPMTables::USER_LOCKS,
            'SET'    => ['deleted' => 1],
            'WHERE'  => [
                'instanceType' => $instanceType,
                'instanceId'   => $instanceId,
                'deleted'      => 0,
            ],
        ]);
    }

    public static function getIssueLock($issueId) 
    {
        return self::loadInstanceLock(LPMInstanceTypes::ISSUE, $issueId);
    }

    protected static function loadInstanceLock($instanceType, $instanceId) 
    {
        $list = self::loadInstanceLocks($instanceType, $instanceId, 1);
        return !empty($list) ? $list[0] : null;
    }

    protected static function loadInstanceLocks($instanceType, $instanceId, $limit = null) 
    {
        $now = DateTimeUtils::mysqlDate();
        $sql = self::buildQuery([
            'SELECT' => '*',
            'FROM'   => LPMTables::USER_LOCKS,
            'WHERE'  => [
                'instanceType' => $instanceType,
                'instanceId'   => $instanceId,
                'deleted'      => 0,
                'expired'      => ['>' => $now],
            ],
            'ORDER BY'  => '`expired` DESC',
            'LIMIT'     => $limit,
        ]);

        return self::loadObjList(self::getDB(), $sql, __CLASS__);
    }

    protected static function loadList($where = null) 
    {
        return StreamObject::loadListDefault(
            self::getDB(),
            $where,
            LPMTables::USER_LOCKS,
            __CLASS__
        );
    }


    /**
     * Идентификатор блокировки.
     * @var int
     */
    public $id;

    /**
     * Идентификатор пользователя, который заблокировал.
     * @var int
     */
    public $userId;

    /**
     * Дата блокировки.
     * @var float
     */
    public $date;

    /**
     * Дата окончания блокировки.
     * @var float
     */
    public $expired;

    /**
     * Тип комментария.
     *
     * См. IssueCommentType.
     * @var string
     */
    public $instanceType;

    /**
     * Идентификатор сущности, которую заблокировал пользователь.
     * @var int
     */
    public $instanceId;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'userId', 'instanceType', 'instanceId');
        $this->addDateTimeFields('date', 'expired');


        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }

    public function getFormattedExpired()
    {
        return self::getDateTimeStr($this->expired);
    }
}