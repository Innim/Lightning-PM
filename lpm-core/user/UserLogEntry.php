<?php
/**
 * Запись лога о действии пользователя.
 */
class UserLogEntry extends LPMBaseObject
{
    public static function create($userId, $date, $type, $entityId = 0, $comment = '')
    {
        $db = self::getDB();
        $date = DateTimeUtils::mysqlDate($date);
        return $db->queryb([
            'INSERT' => compact('userId', 'date', 'type', 'entityId', 'comment'),
            'INTO'   => LPMTables::USERS_LOG
        ]);
    }

    public static function issueEdit($userId, $issueId, $comment = '')
    {
        return self::create(
            $userId,
            DateTimeUtils::$currentDate,
            UserLogEntryType::EDIT_ISSUE,
            $issueId,
            $comment
        );
    }

    public $id;
    public $userId;
    public $date;

    /**
     * Тип записи лога.
     * @var int
     * @see UserLogEntryType
     */
    public $type;

    /**
     * Идентификатор сущности, с которой было произведено действие.
     * @var int
     */
    public $entityId = 0;
    public $comment;
    
    public function __construct()
    {
        parent::__construct();
        $this->_typeConverter->addIntVars('id', 'userId', 'type', 'entityId');
        $this->addDateTimeFields('date');
    }
}
