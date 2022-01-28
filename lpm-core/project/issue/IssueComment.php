<?php
/**
 * Дополнительные данные комментария к задаче.
 */
class IssueComment extends LPMBaseObject
{
    /**
     * Создает запись.
     *
     * Если запись уже создана - будет заменена.
     * 
     * @return 
     */
    public static function create(int $commentId, string $type, string $data = null)
    {
        $data = (string)$data;
        $fields = compact('commentId', 'type', 'data');
        $hash = [
            'REPLACE' => $fields,
            'INTO'    => LPMTables::ISSUE_COMMENT
        ];

        self::buildAndSaveToDb($hash);

        return (new IssueComment($fields));
    }

    /**
     * Comment::$id
     * @var int
     */
    public $commentId;

    /**
     * Тип комментария.
     *
     * См. IssueCommentType.
     * @var string
     */
    public $type;

    /**
     * Дополнительные данные.
     *
     * @var string
     */
    public $data;

    public function __construct($raw = null)
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('commentId');

        if (!empty($raw)) $this->loadStream($raw);
    }

    /**
     * Определяет, является ли комментарий запросом изменений
     * (правки от тестировщика).
     * @return bool
     */
    public function isRequestChanges() {
        return $this->type == IssueCommentType::REQUEST_CHANGES;
    }

    /**
     * Определяет, является ли комментарий отметкой о прохождении теста.
     * @return bool
     */
    public function isPassTest() {
        return $this->type == IssueCommentType::PASS_TEST;
    }
}
