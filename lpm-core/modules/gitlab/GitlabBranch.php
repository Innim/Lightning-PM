<?php
/**
 * Данные Gitlab Branch.
 */
class GitlabBranch extends \GMFramework\StreamObject
{
    /**
     * Имя ветки.
     * @var string
     */
    public $name;

    /**
     * URL веб-интерфейса ветки.
     * @var string
     */
    public $url;

    /**
     * Текущий коммит.
     * @var GitlabCommit
     */
    public $commit;

    private $_data;

    public function __construct($data)
    {
        parent::__construct();
        
        $this->commit = new GitlabCommit();

        $this->_data = $data;

        $this->loadStream($data);
    }

    protected function initTypes()
    {
        parent::initTypes();

        $this->addAlias('web_url', 'url');
    }
}
