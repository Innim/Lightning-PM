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
    public static function create($issueId, $repositoryId, $name, $userId, $lastСommit = null, $mergedInDevelop = null)
    {
        $date = DateTimeUtils::mysqlDate();

        $fields4Update = [];

        if ($mergedInDevelop === null) {
            $mergedInDevelop = false;
        } else {
            $fields4Update[] = 'mergedInDevelop';
        }

        if ($lastСommit === null) {
            $lastСommit = '';
        } else {
            $fields4Update[] = 'lastСommit';
        }

        $hash = [
            'INSERT'  => compact('issueId', 'repositoryId', 'name', 'date', 'lastСommit', 'mergedInDevelop', 'userId'),
            'INTO'    => LPMTables::ISSUE_BRANCH
        ];

        if (empty($fields4Update)) {
            $hash['IGNORE'] = '';
        } else {
            $hash['ON DUPLICATE KEY UPDATE'] = $fields4Update;
        }

        self::buildAndSaveToDb($hash);
    }

    /**
     * Загружает список веток по указанным последним коммитам.
     *
     * @param  int           $repositoryId Идентификатор репозитория.
     * @param  array<string> $lastCommits  Список последних коммитов.
     * @param  bool          $onlyNotMergedInDevelop  Будут загружены только те, что еще не были влиты в develop.
     */
    public static function loadByLastCommits($repositoryId, $lastCommits, $onlyNotMergedInDevelop = false)
    {
        $lastCommitsVal = "'" . implode("', '", $lastCommits) . "'";
        $where = '`repositoryId` = ' . $repositoryId . ' AND `lastСommit` IN (' . $lastCommitsVal . ')';
        if ($onlyNotMergedInDevelop) {
            $where .= ' AND `mergedInDevelop` = 0';
        }

        return self::loadAndParse([
            'SELECT' => '*',
            'FROM'   => LPMTables::ISSUE_BRANCH,
            'WHERE'  => $where,
        ], __CLASS__);
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
     * Проверяет, существует ли хотя бы одна ветка для указанной задачи,
     * для которой либо нет MR, либо есть MR, но он не влит.
     *
     * @param  int      $issueId Идентификатор задачи.
     * @param  int|null $exceptMrId Если не null, то этот MR будет игнорироваться в проверке.
     * @return bool
     */
    public static function existBranchesWithoutMergedMRForIssue($issueId, $exceptMrId = null)
    {
        $mergedState = GitlabMergeRequest::STATE_MERGED;
        $db = self::getDB();

        $mrWhere = "`mr`.`state` <> '$mergedState'";
        if ($exceptMrId != null) {
            $mrWhere .= ' AND `mrId` <> ' . $exceptMrId;
        }

        $sql = <<<SQL
    SELECT 1 FROM `%1\$s` `b`
 LEFT JOIN `%2\$s` `mr` 
        ON `b`.`issueId` = `mr`.`issueId`
       AND `b`.`repositoryId` = `mr`.`repositoryId`
       AND `b`.`name` = `mr`.`branch`
     WHERE `b`.`issueId` = $issueId 
       AND ($mrWhere OR `mr`.`state` IS NULL)
SQL;
        
        $res = $db->queryt($sql, LPMTables::ISSUE_BRANCH, LPMTables::ISSUE_MR);
        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        return $res->num_rows > 0;
    }

    /**
     * Проверяет, существует ли хотя бы одна ветка для указанной задачи,
     * которая еще не влита в develop.
     *
     * @param  int    $issueId Идентификатор задачи.
     * @return bool
     */
    public static function existNotMergedInDevelopForIssue($issueId)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => '1',
            'FROM'   => LPMTables::ISSUE_BRANCH,
            'WHERE'  => [
                'issueId' => $issueId,
                'mergedInDevelop' => 0,
            ],
            'LIMIT'  => 1,
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        return $res->num_rows > 0;
    }

    /**
     * Проверяет, существует ли запись для указанной ветки.
     *
     *
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $name         Имя ветки.
     * @return bool
     */
    public static function existIssuesWithBranch($repositoryId, $name)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => '1',
            'FROM'   => LPMTables::ISSUE_BRANCH,
            'WHERE'  => compact(['repositoryId', 'name']),
            'LIMIT'  => 1,
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        return $res->num_rows > 0;
    }

    /**
     * Отмечает что ветка влита в develop.
     *
     * @param  int    $issueId      Идентификатор задачи.
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $name         Имя ветки.
     */
    public static function mergedInDevelop($issueId, $repositoryId, $name)
    {
        self::buildAndSaveToDb([
            'UPDATE'  => LPMTables::ISSUE_BRANCH,
            'SET' => ['mergedInDevelop' => 1],
            'WHERE' => compact('issueId', 'repositoryId', 'name'),
        ]);
    }

    /**
     * Обновляет ID последнего коммита.
     *
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $name         Имя ветки.
     * @param  string $lastCommit   ID последнего коммита.
     */
    public static function updateLastCommit($repositoryId, $name, $lastСommit)
    {
        self::buildAndSaveToDb([
            'UPDATE'  => LPMTables::ISSUE_BRANCH,
            'SET' => compact('lastСommit'),
            'WHERE' => compact('repositoryId', 'name'),
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

    /**
     * ID последнего коммита.
     *
     * Здесь подразумевается именно коммит, принадлежащей этой ветке,
     * т.е. это должен быть коммит, которого нет в develop
     * (пока ветка не влита).
     */
    public $lastСommit;

    /**
     * Отметка о влитии в develop.
     */
    public $mergedInDevelop;

    /**
     * Идентификатор пользователя.
     */
    public $userId;

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('repositoryId', 'issueId', 'userId');
        $this->_typeConverter->addBoolVars('mergedInDevelop');
        $this->addDateTimeFields('date');
    }
}
