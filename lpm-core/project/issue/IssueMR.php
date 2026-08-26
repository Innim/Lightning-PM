<?php
/**
 * GitLab MR от исполнителей по задачам.
 */
class IssueMR extends LPMBaseObject
{
    /**
     * Загружает список идентификаторов задач для открытого MR.
     * @param  int $mrId Идентификатор MR.
     * @return array<int>
     */
    public static function loadIssueIdsForOpenedMr($mrId)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => 'issueId',
            'FROM'   => LPMTables::ISSUE_MR,
            'WHERE'  => [
                'mrId'  => $mrId,
                'state' => GitlabMergeRequest::STATE_OPENED
            ]
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
     * Определяет, есть ли открытые MR для указанной задачи.
     *
     * @param $issueId Идентификатор задачи.
     * @param $exceptMrId Если не null, то этот MR будет игнорироваться в проверке.
     */
    public static function existOpenedMrForIssue($issueId, $exceptMrId = null)
    {
        $db = self::getDB();

        $where = [
            '`issueId` = ' . $issueId,
            "`state` = '" . GitlabMergeRequest::STATE_OPENED . "'",
        ];

        if ($exceptMrId != null) {
            $where[] = '`mrId` <> ' . $exceptMrId;
        }

        $res = $db->queryb([
            'SELECT' => '1',
            'FROM'   => LPMTables::ISSUE_MR,
            'WHERE'  => $where,
            'LIMIT'  => 1,
        ]);

        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        return $res->num_rows > 0;
    }

    /**
     * Обновляет статус Merge Request с указанным ID.
     * @param  int    $mrId  Идентификатор MR.
     * @param  string $state Новый статус.
     */
    public static function updateState($mrId, $state)
    {
        $db = self::getDB();
        return $db->queryb([
            'UPDATE' => LPMTables::ISSUE_MR,
            'SET'    => ['state' => $state],
            'WHERE'  => ['mrId'  => $mrId],
        ]);
    }

    /**
     * Приводит сохраненное состояние Merge Request к переданному.
     *
     * Обновляет только уже существующие записи — если MR не привязан ни к одной
     * задаче, ничего не происходит. Запись в БД выполняется только тогда,
     * когда сохраненное состояние отличается от переданного.
     *
     * @param  int    $mrId  Идентификатор MR: внутренний идентификатор GitLab
     *                       ({@see GitlabMergeRequest::$id}), а не номер MR
     *                       внутри проекта ({@see GitlabMergeRequest::$internalId}).
     * @param  string $state Актуальное состояние (см. GitlabMergeRequest::STATE_*).
     * @return bool true, если состояние было обновлено.
     * @throws \GMFramework\ProviderLoadException Если не удалось прочитать состояние.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить состояние.
     */
    public static function syncState($mrId, $state)
    {
        $db = self::getDB();
        $res = $db->queryb([
            'SELECT' => 'state',
            'FROM'   => LPMTables::ISSUE_MR,
            'WHERE'  => ['mrId' => $mrId],
        ]);
        if ($res === false) {
            throw new \GMFramework\ProviderLoadException();
        }

        $outdated = false;
        foreach ($res as $raw) {
            if ($raw['state'] !== $state) {
                $outdated = true;
                break;
            }
        }

        if (!$outdated) {
            return false;
        }

        if (self::updateState($mrId, $state) === false) {
            throw new \GMFramework\ProviderSaveException();
        }

        return true;
    }

    /**
     * Создает запись.
     * @param  int    $mrId    Идентификатор MR.
     * @param  int    $issueId Идентификатор задачи.
     * @param  string $state Новый статус.
     * @param  int    $repositoryId Идентификатор репозитория.
     * @param  string $branch Имя ветки.
     */
    public static function create($mrId, $issueId, $state, $repositoryId, $branch)
    {
        $db = self::getDB();
        return $db->queryb([
            'INSERT' => compact('mrId', 'issueId', 'state', 'repositoryId', 'branch'),
            'INTO'   => LPMTables::ISSUE_MR
        ]);
    }

    /**
     * Создает запись по данным MR.
     * @param  int                $issueId Идентификатор задачи.
     * @param  GitlabMergeRequest $mr Данные merge request'а.
     */
    public static function createByMr($issueId, GitlabMergeRequest $mr)
    {
        return self::create($mr->id, $issueId, $mr->state, $mr->targetProjectId, $mr->sourceBranch);
    }

    /**
     * Уникальный идентификатор.
     * @var int
     */
    public $id;

    /**
     * GitlabMergeRequest::$id
     * @var int
     */
    public $mrId;

    /**
     * Issue::$id
     * @var int
     */
    public $issueId;

    /**
     * GitlabMergeRequest::$state
     * @var string
     */
    public $state;

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
    public $branch;

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'mrId', 'issueId', 'repositoryId');
    }
}
