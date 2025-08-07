<?php
require_once __DIR__ . '/../init.inc.php';

/**
 * Сервис, предоставляющий API для администрирования.
 * 
 * Только пользователи с ролью `User::ROLE_ADMIN`
 * могут делать запросы к этому сервису.
 */
class AdminService extends LPMBaseService
{
    /**
     * Инициирует сброс кэша.
     * @return
     */
    public function flushCache()
    {
        $cache = $this->cache();
        $cache->flush();

        return $this->answer();
    }

    public function beforeFilter($calledFunc)
    {
        return parent::beforeFilter($calledFunc) && $this->checkRole(User::ROLE_ADMIN);
    }
}
