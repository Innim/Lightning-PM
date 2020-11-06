<?php
/**
 * Ветка на GitLab репозитории, привязанная к конкретной задаче.
 */
class IssueBranch extends LPMBaseObject
{
    /**
     * Создает запись.
     *
     * Если запись уже создана - будет заменена.
     *
     * @param  int    $issueId      Идентификатор задачи.
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $name         Имя ветки.
     */
    public static function create($issueId, $repositoryId, $name)
    {
        $db = self::getDB();
        $date = DateTimeUtils::mysqlDate();
        return $db->queryb([
            'REPLACE' => compact('issueId', 'repositoryId', 'name', 'date'),
            'INTO'   => LPMTables::ISSUE_BRANCH
        ]);
    }

    /**
     * Issue::$id
     * @var int
     */
    public $issueId;

    /**
     * Идентификатор репозитория.
     * GitlabProject::$id
     * @var int
     */
    public $repositoryId;

    /**
     * Имя ветки.
     * GitlabBranch::$name
     * @var string
     */
    public $name;

    /**
     * Дата записи.
     */
    public $date;

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'repositoryId', 'issueId');
        $this->addDateTimeFields('date');
    }
}
