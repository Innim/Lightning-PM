<?php
/**
 * Запись лога о действии пользователя.
 */
class UserLogEntry extends LPMBaseObject {
	public static function create($userId, $date, $type, $entityId = 0) {
		$db = self::getDB();
		$date = DateTimeUtils::mysqlDate($date);
		return $db->queryb([
			'INSERT' => compact('userId', 'date', 'type', 'entityId'),
			'INTO'   => LPMTables::USERS_LOG
		]);
	}

	public static function issueEdit($userId, $issueId) {
		return self::create(
			$userId, DateTimeUtils::$currentDate, UserLogEntryType::EDIT_ISSUE, $issueId);
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
	
	function __construct() {
		parent::__construct();
		$this->_typeConverter->addIntVars('id', 'userId', 'type', 'entityId');
		$this->addDateTimeFields('date');
	}
}