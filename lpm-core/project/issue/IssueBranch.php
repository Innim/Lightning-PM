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
            'INTO'    => LPMTables::ISSUE_BRANCH
        ]);
    }

    /**
     * Загружает список идентификаторов задач для указанной ветки.
     *
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $name         Имя ветки.
     * @return array<int>
     */
    public static function loadIssueIdsForBranch($repositoryId, $name)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => 'issueId',
            'FROM'   => LPMTables::ISSUE_BRANCH,
            'WHERE'  => compact(['repositoryId', 'name'])
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        $list = [];
        foreach ($res as $raw) {
            $list[] = (int)$raw['issueId'];
        }

        return $list;
    }

    /**
     * Возвращает наиболее популярный репозиторий для проекта.
     *
     * @param int $projectId    Идентификатор проекта.
     * @param int $inLast       Передайте число элементов, если надо делать выборку
     *                          не по всем, а только из указанного количества последних.
     * @return int|null Идентификатор репозитория или null, если ничего не найдено.
     */
    public static function loadPopularRepository($projectId, $inLast = null)
    {
        $db = self::getDB();
        $limitSql = empty($limit) ? '' : 'LIMIT ' . $inLast;

        $sql = <<<SQL
    SELECT `repositoryId`, COUNT(`repositoryId`) as `count` FROM (
        SELECT `repositoryId` FROM `%1\$s` `b`, `%2\$s` `i`
         WHERE `b`.`issueId` = `i`.`id` AND `i`.`projectId` = $projectId
      ORDER BY `date` DESC 
        $limitSql
    ) AS `last_entiries` 
    GROUP BY `repositoryId`
    ORDER BY `count` DESC LIMIT 1
SQL;
        $res = $db->queryt($sql, LPMTables::ISSUE_BRANCH, LPMTables::ISSUES);
        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        $row = $res->fetch_assoc();
        return $row ? (int)$row['repositoryId'] : null;
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
