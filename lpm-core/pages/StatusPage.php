<?php
/**
 * Страница просмотра текущего статуса таска.
 * 
 * Функционал для администратора.
 */
class StatusPage extends LPMPage
{
    const UID = 'status';
    
    public function __construct()
    {
        parent::__construct(self::UID, 'Статус', true, false, 'status', '', User::ROLE_ADMIN);
        $this->addJS('admin', 'status');
    }

    public function init()
    {
        if (!parent::init()) {
            return false;
        }

        $engine = $this->_engine;

        $status = new AppStatus();

        $cache = $engine->cache();
        $status->cacheEnabled = $cache->isEnabled();

        $this->addTmplVar('status', $status);

        return $this;
    }
}