<?php
/**
 * Данные Gitlab Job.
 */
class GitlabJob extends \GMFramework\StreamObject
{
    const STATUS_CREATED = 'created';
    const STATUS_WAITING_FOR_RESOURCE = 'waiting_for_resource';
    const STATUS_PREPARING = 'preparing';
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_MANUAL = 'manual';
    const STATUS_SCHEDULED = 'scheduled';

    /**
     * Идентификатор джобы.
     * @var int
     */
    public $id;

    /**
     * Название джобы.
     * @var string
     */
    public $name;

    /**
     * Стадия, к которой относится джоба.
     * @var string
     */
    public $stage;

    /**
     * Ветка или тег, для которого создана джоба.
     * @var string
     */
    public $ref;

    /**
     * Статус джобы.
     *
     * Возможные значения (см. STATUS_*):
     * created, waiting_for_resource, preparing, pending, running, success,
     * failed, canceled, skipped, manual, scheduled.
     *
     * @var string
     */
    public $status;

    /**
     * URL веб-интерфейса джобы.
     * @var string
     */
    public $url;

    /**
     * Дата завершения джобы (если завершена).
     * @var \GMFramework\Date
     */
    public $finishedAt;

    /**
     * Дата создания джобы.
     * @var \GMFramework\Date
     */
    public $createdAt;

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

        $this->_int('id');

        $this->finishedAt = new \GMFramework\Date();
        $this->createdAt = new \GMFramework\Date();

        $this->addAlias('web_url', 'url');
        $this->addAlias('finished_at', 'finishedAt');
        $this->addAlias('created_at', 'createdAt');
    }
}
