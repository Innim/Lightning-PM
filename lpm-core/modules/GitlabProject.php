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

        $this->addAlias('web_url', 'url');
    }
}
