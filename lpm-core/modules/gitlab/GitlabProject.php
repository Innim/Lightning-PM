<?php
/**
 * Данные Gitlab Project.
 */
class GitlabProject extends \GMFramework\StreamObject
{
    /**
     * Идентификатор проекта.
     * @var int
     */
    public $id;

    /**
     * Имя проекта.
     * @var string
     */
    public $name;

    /**
     * Относительный путь проекта.
     * @var string
     */
    public $path;

    /**
     * URL веб-интерфейса проекта.
     * @var string
     */
    public $url;

    /**
     * Дата создания репозитория.
     * @var \GMFramework\Date
     */
    public $created;

    /**
     * Дата последней активности.
     * @var \GMFramework\Date
     */
    public $lastActivity;

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
        $this->created = new \GMFramework\Date();
        $this->lastActivity = new \GMFramework\Date();

        $this->addAlias('web_url', 'url');
        $this->addAlias('created_at', 'created');
        $this->addAlias('last_activity_at', 'lastActivity');
    }
}
