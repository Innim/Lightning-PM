<?php
/**
 * Данные Gitlab Commit.
 */
class GitlabCommit extends \GMFramework\StreamObject
{
    /**
     * ID коммита (хэш).
     * @var string
     */
    public $id;

    /**
     * URL веб-интерфейса коммита.
     * @var string
     */
    public $url;

    public function __construct($data = null)
    {
        parent::__construct();

        if ($data != null) {
            $this->loadStream($data);
        }
    }

    protected function initTypes()
    {
        parent::initTypes();

        $this->addAlias('web_url', 'url');
    }
}
