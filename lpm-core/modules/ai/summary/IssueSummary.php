<?php
/**
 * Сохранённая ИИ-сводка обсуждения задачи.
 *
 * Сводка составляется по явному запросу пользователя и хранится одна
 * на задачу — её видят все, у кого есть доступ к задаче. Соответствие
 * сводки текущему состоянию задачи проверяется через isActualFor():
 * устаревшая сводка остаётся доступной для чтения, пока её не обновят.
 */
class IssueSummary extends LPMBaseObject
{
    /**
     * Загружает сводку задачи.
     * @param int $issueId Идентификатор задачи.
     * @return IssueSummary|null Сводка или null, если её ещё нет.
     */
    public static function loadByIssue($issueId)
    {
        $issueId = (int)$issueId;
        if ($issueId <= 0) {
            return null;
        }

        return self::loadAndParseSingleV2([
            'SELECT' => '*',
            'FROM' => LPMTables::AI_ISSUE_SUMMARY,
            'WHERE' => [
                'issueId' => $issueId,
            ],
            'LIMIT' => 1,
        ], __CLASS__);
    }

    /**
     * Сохраняет сводку задачи, заменяя предыдущую.
     *
     * @param int $issueId Идентификатор задачи.
     * @param string $sourceHash Слепок данных задачи, по которым составлена сводка.
     * @param array $summary Разделы сводки: `summary`, `state`,
     * `openQuestions`, `remaining`.
     * @param string $model Модель, составившая сводку.
     * @param AiUsage $usage Расход токенов, если провайдер его сообщил.
     * @return IssueSummary Сохранённая сводка.
     * @throws \GMFramework\ProviderSaveException Если не удалось записать данные.
     */
    public static function save($issueId, $sourceHash, array $summary, $model, ?AiUsage $usage = null)
    {
        $fields = [
            'issueId' => (int)$issueId,
            'sourceHash' => (string)$sourceHash,
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'model' => (string)$model,
            'promptTokens' => $usage === null ? 0 : $usage->getPromptTokens(),
            'completionTokens' => $usage === null ? 0 : $usage->getCompletionTokens(),
            'totalTokens' => $usage === null ? 0 : $usage->getTotalTokens(),
            'createdAt' => DateTimeUtils::mysqlDate(),
        ];

        self::buildAndSaveToDbV2([
            'INSERT' => $fields,
            'INTO' => LPMTables::AI_ISSUE_SUMMARY,
            'ODKU' => [
                'sourceHash',
                'summary',
                'model',
                'promptTokens',
                'completionTokens',
                'totalTokens',
                'createdAt',
            ],
        ]);

        return new IssueSummary($fields);
    }

    /**
     * Идентификатор задачи.
     * @var int
     */
    public $issueId = 0;

    /**
     * Слепок данных задачи, по которым составлена сводка.
     * @var string
     */
    public $sourceHash = '';

    /**
     * Разделы сводки в формате JSON.
     * @var string
     */
    public $summary = '';

    /**
     * Модель, составившая сводку.
     * @var string
     */
    public $model = '';

    /**
     * Количество токенов в запросе к модели.
     * @var int
     */
    public $promptTokens = 0;

    /**
     * Количество токенов в ответе модели.
     * @var int
     */
    public $completionTokens = 0;

    /**
     * Общее количество токенов, потраченных на сводку.
     * @var int
     */
    public $totalTokens = 0;

    /**
     * Дата составления сводки.
     * @var float
     */
    public $createdAt = 0;

    private $_data;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addFloatVars('issueId');
        $this->_typeConverter->addIntVars('promptTokens', 'completionTokens', 'totalTokens');
        $this->addDateTimeFields('createdAt');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }

    /**
     * Определяет, составлена ли сводка по текущему состоянию задачи.
     * @param string $sourceHash Слепок данных задачи.
     * @return bool
     */
    public function isActualFor($sourceHash)
    {
        return $this->sourceHash !== '' && $this->sourceHash === (string)$sourceHash;
    }

    /**
     * Краткое содержание задачи и обсуждения.
     * @return string
     */
    public function getText()
    {
        return $this->getValue('summary');
    }

    /**
     * Описание того, на чём задача остановилась.
     * @return string
     */
    public function getState()
    {
        return $this->getValue('state');
    }

    /**
     * Вопросы, по которым нет решения.
     * @return array<string>
     */
    public function getOpenQuestions()
    {
        return $this->getList('openQuestions');
    }

    /**
     * Что осталось сделать по задаче.
     * @return array<string>
     */
    public function getRemaining()
    {
        return $this->getList('remaining');
    }

    /**
     * Дата составления сводки для отображения.
     * @return string
     */
    public function getDate()
    {
        return self::getDateTimeStr($this->createdAt);
    }

    /**
     * Разделы сводки.
     * @return array
     */
    private function getData()
    {
        if ($this->_data === null) {
            $data = json_decode((string)$this->summary, true);
            $this->_data = is_array($data) ? $data : [];
        }

        return $this->_data;
    }

    /**
     * Возвращает текстовый раздел сводки.
     * @param string $key Название раздела.
     * @return string
     */
    private function getValue($key)
    {
        $data = $this->getData();
        return isset($data[$key]) ? (string)$data[$key] : '';
    }

    /**
     * Возвращает раздел сводки, состоящий из списка пунктов.
     * @param string $key Название раздела.
     * @return array<string>
     */
    private function getList($key)
    {
        $data = $this->getData();
        if (empty($data[$key]) || !is_array($data[$key])) {
            return [];
        }

        return array_map('strval', $data[$key]);
    }
}
