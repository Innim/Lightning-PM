<?php
/**
 * Менеджер внешних API.
 */
class ExternalApiManager
{
    /**
     * @var LightningEngine
     */
    private $_engine;
    /**
     * Реализации внешних API по идентификаторам.
     * @var array[string=>ExternalApi]
     */
    private $_apiByUid;

    public function __construct(LightningEngine $engine)
    {
        $this->_engine = $engine;

        $this->register(
            new GitlabExternalApi($engine, defined('GITLAB_HOOK_TOKEN') ? GITLAB_HOOK_TOKEN : null)
        );
    }

    /**
     * Возвращает API по идентификатору.
     * @param  string $uid Уникальный идентификатор API.
     * @return ExternalApi|null
     */
    public function getByUid($uid)
    {
        return empty($this->_apiByUid[$uid]) ? null : $this->_apiByUid[$uid];
    }

    private function register($api, $_ = null)
    {
        $args = func_get_args();
        foreach ($args as $api) {
            $uid = $api->getUid();
            if (isset($this->_apiByUid[$uid])) {
                throw new Exception('API ' . $uid . ' already defined');
            }
                
            $this->_apiByUid[$uid] = $api;
        }
    }
}
