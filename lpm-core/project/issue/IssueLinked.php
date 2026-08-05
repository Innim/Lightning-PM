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
     * Удаляет связь между задачами.
     *
     * Удаляет запись в обоих направлениях, независимо от того,
     * какая из задач была основной при создании связи.
     *
     * @param  int $issueId       Идентификатор одной из связанных задач.
     * @param  int $linkedIssueId Идентификатор другой связанной задачи.
     */
    public static function remove($issueId, $linkedIssueId)
    {
        $issueId = (int)$issueId;
        $linkedIssueId = (int)$linkedIssueId;

        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::ISSUE_LINKED,
            'WHERE'  => ['issueId' => $issueId, 'linkedIssueId' => $linkedIssueId],
        ]);
        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::ISSUE_LINKED,
            'WHERE'  => ['issueId' => $linkedIssueId, 'linkedIssueId' => $issueId],
        ]);
    }

    /**
     * Автоматически связывает задачу со всеми задачами, на которые есть ссылки в тексте.
     *
     * Связи только добавляются: упоминание самой задачи и уже существующие связи
     * игнорируются, связи с недоступными пользователю задачами не создаются.
     *
     * @param Issue  $issue  Задача, для которой обрабатываются упоминания.
     * @param string $text   Текст (описание или комментарий) с возможными ссылками на задачи.
     * @param int    $userId Идентификатор пользователя, от имени которого создаются связи.
     * @return int Количество созданных связей.
     */
    public static function syncFromText(Issue $issue, $text, $userId)
    {
        $mentioned = OwnUrlHelper::extractLinkedIssues($text);
        if (empty($mentioned)) {
            return 0;
        }

        $linkedIds = [];
        foreach ($issue->getLinkedIssues() as $linked) {
            $linkedIds[$linked->getID()] = true;
        }

        $created = 0;
        foreach ($mentioned as $target) {
            $targetId = $target->getID();
            if ($targetId == $issue->getID() || isset($linkedIds[$targetId])) {
                continue;
            }
            if (!$target->checkViewPermit($userId)) {
                continue;
            }

            self::create($issue->getID(), $targetId, DateTimeUtils::$currentDate);
            $linkedIds[$targetId] = true;
            $created++;
        }

        return $created;
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
