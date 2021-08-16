<?php
/**
 * Связанные задачи.
 */
class IssueLinked extends LPMBaseObject
{
    /**
     * Создает запись.
     *
     * Если запись уже создана - будет заменена.
     *
     * @param  int    $issueId       Идентификатор основной задачи.
     * @param  int    $linkedIssueId Идентификатор связанной задачи.
     * @param  float  $created       Дата создания.
     */
    public static function create($issueId, $linkedIssueId, $created = null)
    {
        $created = DateTimeUtils::mysqlDate($created);
        $hash = [
            'INSERT'  => compact('issueId', 'linkedIssueId', 'created'),
            'INTO'    => LPMTables::ISSUE_LINKED,
            'IGNORE'  => '',
        ];
        
        self::buildAndSaveToDb($hash);
    }

    /**
     * ID основной задачи.
     * Issue::$id
     * @var int
     */
    public $issueId;

    /**
     * ID связанной задачи.
     * Issue::$id
     * @var int
     */
    public $linkedIssueId;

    /**
     * Дата создания связи.
     * @var float
     */
    public $created;

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('issueId', 'linkedIssueId');
        $this->addDateTimeFields('created');
    }
}
