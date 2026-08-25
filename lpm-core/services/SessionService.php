<?php
require_once(__DIR__ . '/../init.inc.php');

/**
 * Служебный сервис поддержания сессии открытой страницы.
 */
class SessionService extends LPMBaseService
{
    /**
     * Имя переменной сессии с временем последнего пинга.
     */
    const SESSION_PING = 'lightning_ping';

    public function __construct()
    {
        parent::__construct();

        // Пинг нужен и до входа: формы входа и восстановления пароля
        // тоже заполняют дольше, чем живёт сессия. Токен при этом
        // проверяется в любом случае - см. LPMBaseService::beforeFilter().
        array_push($this->_allowMethods, 'ping');
    }

    /**
     * Продлевает сессию открытой страницы.
     *
     * Токен страницы живёт вместе с сессией, а PHP сбрасывает её после
     * `session.gc_maxlifetime` без запросов. Форму - например, описание
     * задачи - заполняют и дольше, поэтому пока страница открыта,
     * она сама напоминает о себе.
     */
    public function ping()
    {
        // Пишем в сессию, чтобы обновилось время её файла: иначе сборщик
        // мусора посчитает сессию заброшенной и удалит вместе с токеном.
        Session::getInstance()->set(self::SESSION_PING, time());

        return $this->answer();
    }
}
