<?php
/**
 * Данные Gitlab Pipeline.
 */
class GitlabPipeline extends \GMFramework\StreamObject
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
     * Пайплайн запущен пушем в ветку.
     */
    const SOURCE_PUSH = 'push';

    /**
     * Пайплайн запущен по расписанию.
     */
    const SOURCE_SCHEDULE = 'schedule';

    /**
     * Пайплайн порождён другим пайплайном (child pipeline).
     */
    const SOURCE_PARENT_PIPELINE = 'parent_pipeline';

    /**
     * Идентификатор пайплайна.
     * @var int
     */
    public $id;

    /**
     * Идентификатор проекта.
     * @var int
     */
    public $projectId;

    /**
     * Ветка или тег, для которого создан пайплайн.
     * @var string
     */
    public $ref;

    /**
     * Коммит, для которого создан пайплайн.
     * @var string
     */
    public $sha;

    /**
     * Статус пайплайна.
     * 
     * Возможные значения (см. STATUS_*): 
     * created, waiting_for_resource, preparing, pending, running, success, 
     * failed, canceled, skipped, manual, scheduled.
     * 
     * @var string
     */
    public $status;

    /**
     * Чем запущен пайплайн (см. SOURCE_*).
     *
     * Пустая строка, если источник неизвестен: в части ответов GitLab API
     * этого поля нет.
     *
     * @var string
     */
    public $source;

    /**
     * URL веб-интерфейса пайплайна.
     * @var string
     */
    public $url;

    /**
     * Дата завершения пайплайна (если завершен).
     * @var \GMFramework\Date
     */
    public $finishedAt;

    /**
     * Дата создания пайплайна.
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

        $this->_int('id', 'projectId');

        $this->finishedAt = new \GMFramework\Date();
        $this->createdAt = new \GMFramework\Date();

        $this->addAlias('web_url', 'url');
        $this->addAlias('project_id', 'projectId');
        $this->addAlias('finished_at', 'finishedAt');
        $this->addAlias('created_at', 'createdAt');
    }
}
