<?php
/**
 * Состояние сборки, запущенной влитием merge request'а задачи.
 *
 * На пару «задача — merge request» приходится одна запись: у задачи бывает
 * несколько MR, в том числе в разных репозиториях, и один MR может закрывать
 * несколько задач.
 *
 * Запись заводится в момент влития MR, ещё до того, как известен пайплайн:
 * сборка создаётся тем же пушем, что и мерж-коммит, и в первые секунды её
 * может не быть. Пока пайплайн не найден, {@see $pipelineId} равен нулю,
 * а {@see $status} пуст.
 *
 * Штатный источник состояния - событие пайплайна от GitLab
 * (см. GitlabExternalApi). Запрос к GitLab API служит починкой расхождений:
 * данные, уже полученные при показе задачи, записываются обратно.
 */
class IssuePipeline extends LPMBaseObject
{
    /**
     * Загруженные состояния сборок в разрезе задач: issueId => IssuePipeline[].
     *
     * Страница задачи рисует несколько комментариев о влитии, и все они
     * спрашивают состояния одной и той же задачи.
     * @var array
     */
    private static $_loadedByIssue = [];

    /**
     * Заводит запись о сборке, которую запустит влитие merge request'а.
     *
     * Уже известное состояние сборки не затирается: событие пайплайна может
     * прийти раньше, чем событие о влитии MR.
     *
     * @param  int    $issueId      Идентификатор задачи.
     * @param  int    $mrId         Идентификатор MR на GitLab
     *                              ({@see GitlabMergeRequest::$id}).
     * @param  int    $repositoryId Идентификатор репозитория на GitLab.
     * @param  string $branch       Ветка merge request'а.
     * @param  string $ref          Ветка, в которую влит merge request:
     *                              в ней и идёт сборка.
     * @param  string $sha          Коммит, для которого запущена сборка.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить.
     */
    public static function registerForMr($issueId, $mrId, $repositoryId, $branch, $ref, $sha)
    {
        self::buildAndSaveToDbV2([
            'INSERT' => [
                'issueId'      => (int)$issueId,
                'mrId'         => (int)$mrId,
                'repositoryId' => (int)$repositoryId,
                'branch'       => (string)$branch,
                'ref'          => (string)$ref,
                'sha'          => (string)$sha,
                'updatedAt'    => DateTimeUtils::mysqlDate(),
            ],
            'INTO'   => LPMTables::ISSUE_PIPELINE,
            'ODKU'   => ['repositoryId', 'branch', 'ref', 'sha', 'updatedAt'],
        ]);

        self::$_loadedByIssue = [];
    }

    /**
     * Сохраняет состояние сборки по данным пайплайна.
     *
     * Обновляет только уже заведённые записи: пайплайны, к задачам отношения
     * не имеющие, игнорируются. Записи ищутся по репозиторию, ветке и коммиту,
     * поэтому одно событие обновляет сразу все задачи, чьи MR влиты этим
     * коммитом.
     *
     * События приходят снаружи и могут опоздать, продублироваться или прийти
     * не по порядку, поэтому состояние не откатывается назад:
     * - пайплайн с меньшим идентификатором, чем сохранённый, игнорируется -
     *   его уже вытеснила более поздняя сборка того же коммита;
     * - повторное событие с тем же статусом не пишется в БД;
     * - завершённая сборка не возвращается в незавершённое состояние;
     * - у одной и той же сборки более раннее завершение не перебивает
     *   более позднее.
     *
     * Перезапуск сборки не меняет её идентификатор, поэтому после перезапуска
     * запись остаётся с прошлым результатом до конца новой попытки: её
     * завершение свежее сохранённого и применяется.
     *
     * @param  GitlabPipeline $pipeline Данные пайплайна.
     * @return int Количество обновлённых записей.
     * @throws \GMFramework\ProviderLoadException Если не удалось прочитать состояния.
     * @throws \GMFramework\ProviderSaveException Если не удалось сохранить состояние.
     */
    public static function applyPipeline(GitlabPipeline $pipeline)
    {
        if (empty($pipeline->id) || empty($pipeline->projectId) ||
                empty($pipeline->ref) || empty($pipeline->sha) || empty($pipeline->status)) {
            return 0;
        }

        $rows = self::loadAndParseV2([
            'SELECT' => '*',
            'FROM'   => LPMTables::ISSUE_PIPELINE,
            'WHERE'  => [
                'repositoryId' => (int)$pipeline->projectId,
                'ref'          => (string)$pipeline->ref,
                'sha'          => (string)$pipeline->sha,
            ],
        ], __CLASS__);

        $finishedAt = empty($pipeline->finishedAt) || $pipeline->finishedAt->isUndefined()
            ? 0
            : (int)$pipeline->finishedAt->getUnixtime();

        $updated = 0;
        foreach ($rows as $row) {
            if (!$row->isEventActual($pipeline->id, $pipeline->status, $finishedAt)) {
                continue;
            }

            self::buildAndSaveToDbV2([
                'UPDATE' => LPMTables::ISSUE_PIPELINE,
                'SET'    => [
                    'pipelineId' => (int)$pipeline->id,
                    'status'     => (string)$pipeline->status,
                    'url'        => (string)$pipeline->url,
                    'finishedAt' => $finishedAt,
                    'updatedAt'  => DateTimeUtils::mysqlDate(),
                ],
                'WHERE'  => ['id' => (int)$row->id],
            ]);

            $updated++;
        }

        if ($updated > 0) {
            self::$_loadedByIssue = [];
        }

        return $updated;
    }

    /**
     * Загружает состояния сборок задачи.
     *
     * @param  int $issueId Идентификатор задачи.
     * @return array<IssuePipeline>
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadForIssue($issueId)
    {
        $issueId = (int)$issueId;
        if (!isset(self::$_loadedByIssue[$issueId])) {
            self::$_loadedByIssue[$issueId] = self::loadAndParseV2([
                'SELECT'   => '*',
                'FROM'     => LPMTables::ISSUE_PIPELINE,
                'WHERE'    => ['issueId' => $issueId],
                'ORDER BY' => '`id`',
            ], __CLASS__);
        }

        return self::$_loadedByIssue[$issueId];
    }

    /**
     * Загружает состояния сборок для веток, о влитии которых говорит
     * комментарий.
     *
     * Сборка привязывается к влитию по коммиту, поэтому повторное влитие той
     * же ветки не подменяет состояние в прошлом комментарии. У комментариев,
     * записанных до появления состояний сборок, коммита нет - для них берётся
     * последняя сборка ветки.
     *
     * Ветки без известного состояния сборки пропускаются: у задачи может
     * не быть привязанного MR, а у репозитория - CI.
     *
     * @param  Comment $comment Комментарий о влитии веток.
     * @return array<IssuePipeline> В том же порядке, в каком ветки перечислены
     * в комментарии.
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadForMergedComment(Comment $comment)
    {
        if ($comment->instanceType != LPMInstanceTypes::ISSUE || empty($comment->issueComment)) {
            return [];
        }

        $data = $comment->issueComment->getBranchMergedData();
        if (empty($data) || empty($data->branches)) {
            return [];
        }

        $pipelines = self::loadForIssue($comment->instanceId);
        if (empty($pipelines)) {
            return [];
        }

        $res = [];
        foreach ($data->branches as $branch) {
            $found = null;
            foreach ($pipelines as $pipeline) {
                if (!$pipeline->hasStatus() ||
                        $pipeline->repositoryId != $branch['repositoryId'] ||
                        $pipeline->branch !== $branch['branchName']) {
                    continue;
                }

                if ($branch['sha'] !== '') {
                    if ($pipeline->sha === $branch['sha']) {
                        $found = $pipeline;
                        break;
                    }
                } else {
                    // Записи отсортированы по возрастанию идентификатора,
                    // поэтому в итоге останется последняя сборка ветки
                    $found = $pipeline;
                }
            }

            if ($found !== null) {
                $res[] = $found;
            }
        }

        return $res;
    }

    /**
     * Возвращает сводное состояние сборок задач.
     *
     * @param  array<int> $issueIds Идентификаторы задач.
     * @return array<int, string> Идентификатор задачи => состояние
     * (см. IssuePipelineStatus::*). Задачи, по которым ни одного состояния
     * сборки нет, в результат не попадают.
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadSummaryStatuses(array $issueIds)
    {
        $ids = [];
        foreach ($issueIds as $issueId) {
            $ids[] = (int)$issueId;
        }

        if (empty($ids)) {
            return [];
        }

        $res = self::loadFromDV2([
            'SELECT' => ['issueId', 'status'],
            'FROM'   => LPMTables::ISSUE_PIPELINE,
            'WHERE'  => ['issueId' => $ids],
        ]);

        $statusesByIssue = [];
        foreach ($res as $raw) {
            $statusesByIssue[(int)$raw['issueId']][] = $raw['status'];
        }

        $summary = [];
        foreach ($statusesByIssue as $issueId => $statuses) {
            $state = IssuePipelineStatus::summary($statuses);
            if ($state !== null) {
                $summary[$issueId] = $state;
            }
        }

        return $summary;
    }

    /**
     * Возвращает сводное состояние сборок задачи.
     *
     * @param  int $issueId Идентификатор задачи.
     * @return string|null Состояние (см. IssuePipelineStatus::*) или null,
     * если ни одного состояния сборки по задаче нет.
     * @throws \GMFramework\ProviderLoadException Если не удалось загрузить данные.
     */
    public static function loadSummaryStatus($issueId)
    {
        $summary = self::loadSummaryStatuses([$issueId]);
        $issueId = (int)$issueId;

        return isset($summary[$issueId]) ? $summary[$issueId] : null;
    }

    /**
     * Уникальный идентификатор записи.
     * @var int
     */
    public $id;

    /**
     * Issue::$id
     * @var int
     */
    public $issueId;

    /**
     * GitlabMergeRequest::$id
     * @var int
     */
    public $mrId;

    /**
     * Идентификатор репозитория на GitLab.
     * @var int
     */
    public $repositoryId;

    /**
     * Ветка merge request'а.
     * @var string
     */
    public $branch;

    /**
     * Ветка, в которую влит merge request: в ней идёт сборка.
     * @var string
     */
    public $ref;

    /**
     * Коммит, для которого запущена сборка.
     * @var string
     */
    public $sha;

    /**
     * GitlabPipeline::$id; 0, если пайплайн ещё не найден.
     * @var int
     */
    public $pipelineId;

    /**
     * GitlabPipeline::$status; пустая строка, если состояние неизвестно.
     * @var string
     */
    public $status;

    /**
     * Ссылка на пайплайн в веб-интерфейсе GitLab.
     * @var string
     */
    public $url;

    /**
     * Время завершения сборки, unixtime; 0 - сборка не завершена.
     * @var int
     */
    public $finishedAt;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'issueId', 'mrId', 'repositoryId', 'pipelineId', 'finishedAt');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }

    /**
     * Известно ли состояние сборки.
     * @return bool
     */
    public function hasStatus()
    {
        return !empty($this->status);
    }

    /**
     * Возвращает состояние сборки в терминах задачи.
     * @return string|null Одна из констант IssuePipelineStatus или null,
     * если состояние неизвестно.
     */
    public function getState()
    {
        return IssuePipelineStatus::fromGitlabStatus($this->status);
    }

    /**
     * Определяет, несёт ли событие пайплайна более свежее состояние,
     * чем сохранённое.
     *
     * Правила описаны в {@see self::applyPipeline()}.
     *
     * @param  int    $pipelineId Идентификатор пайплайна из события.
     * @param  string $status     Статус пайплайна из события.
     * @param  int    $finishedAt Время завершения из события, unixtime;
     *                            0 - сборка не завершена.
     * @return bool
     */
    private function isEventActual($pipelineId, $status, $finishedAt)
    {
        if ($this->pipelineId > $pipelineId) {
            return false;
        }

        if ($this->pipelineId < $pipelineId) {
            return true;
        }

        if ($this->status === $status) {
            return false;
        }

        if (!IssuePipelineStatus::isFinal($this->status)) {
            return true;
        }

        return IssuePipelineStatus::isFinal($status) && $finishedAt >= $this->finishedAt;
    }
}
