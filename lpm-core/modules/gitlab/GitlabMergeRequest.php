<?php
/**
 * Данные Gitlab Merge Request.
 */
class GitlabMergeRequest extends \GMFramework\StreamObject
{
    const STATE_OPENED = 'opened';
    const STATE_MERGED = 'merged';
    const STATE_CLOSED = 'closed';
    const STATE_LOCKED = 'locked';

    /**
     * Идентификатор запроса.
     * @var int
     */
    public $id;

    /**
     * Внутренний ID запроса,
     *
     * ID внутри проекта, он обычно используется в URL и так далее,
     * @var int
     */
    public $internalId;

    /**
     * Текущее состояние (см. STATE_*).
     * @var string
     */
    public $state;

    /**
     * Ссылка на MR в веб-интерфейсе.
     * @var String
     */
    public $url;

    private $_data;

    public function __construct($data)
    {
        parent::__construct();

        $this->_data = $data;

        $this->loadStream($data);
    }

    protected function initTypes()
    {
        parent::initTypes();

        $this->_int('id', 'internalId');

        $this->addAlias('web_url', 'url');
        $this->addAlias('iid', 'internalId');
    }

    public function isOpened()
    {
        return $this->state === self::STATE_OPENED;
    }

    public function isMerged()
    {
        return $this->state === self::STATE_MERGED;
    }

    public function isClosed()
    {
        return $this->state === self::STATE_CLOSED;
    }
}
