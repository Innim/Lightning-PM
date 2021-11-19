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
     */
    public static function create(int $commentId, string $type, string $data = null)
    {
        $data = (string)$data;
        $hash = [
            'REPLACE' => compact('commentId', 'type', 'data'),
            'INTO'    => LPMTables::ISSUE_COMMENT
        ];

        self::buildAndSaveToDb($hash);
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

    public function __construct()
    {
        parent::__construct();

        $this->_typeConverter->addIntVars('commentId');
    }
}
