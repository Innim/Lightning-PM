<?php 
/**
 * Данные бейджа.
 */
class Badge extends LPMBaseObject {
    const TYPE_PIPELINE = 'pipeline';

    public static function load($id)
    {
        return StreamObject::singleLoad($id, __CLASS__);
    }

    public static function loadList($where = null)
    {
        return StreamObject::loadListDefault(
            self::getDB(),
            $where,
            LPMTables::BADGES,
            __CLASS__
        );
    }

    /**
     * Идентификатор бейджа.
     * @var int
     */
    public $id;

    /**
     * Тип бейджа.
     * 
     * См. Badge::TYPE_*.
     * @var string
     */
    public $type;

    /**
     * Метка бейджа.
     * @var string
     */
    public $label;

    /**
     * Идентификатор проекта Gitlab.
     * @var int|null
     */
    public $gitlabProjectId;

    /**
     * Ветка, тег или коммит на репозитории.
     * @var string|null
     */
    public $gitlabRef;

    /**
     * Комментарий к бейджу.
     * @var string|null
     */
    public $comment;

    public function __construct($raw = null) {
        parent::__construct();

        $this->_typeConverter->addIntVars('id', 'gitlabProjectId');

        if (!empty($raw)) {
            $this->loadStream($raw);
        }
    }
}