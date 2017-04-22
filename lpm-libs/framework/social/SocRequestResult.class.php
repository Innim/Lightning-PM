<?php
namespace GMFramework;

/**
 * Ответ за запрос к API социальной сети
 * @package ru.vbinc.gm.framework.social
 * @author greymag
 * @copyright 2013
 * @version 0.1
 */ 
class SocRequestResult
{
    // Успешность запроса
    private $_success;
    // Сообщение об ошибке
    private $_error;
    // Номер (код) ошибки
    private $_errno;
    // Полученные данные 
    private $_data;
    // Нераспарсенный ответ
    private $_response;

    /**
     * 
     * @param string $response Полная строка ответа
     */
    function __construct($response)
    {
        $this->_response    = $response;
    }

    /**
     * Определяет успешность запроса
     * (если не был совершен или был завершен с ошибкой - 
     * то запрос считается неуспешным)
     * @return boolean
     */
    public function success()
    {
        return $this->_success;
    }

    /**
     * Сообщение об ошибке
     * @return string
     */
    public function error()
    {
        return $this->_error;
    }

    /**
     * Номер (код) ошибки
     * @return int
     */
    public function errno()
    {
        return $this->_errno;
    }

    /**
     * Распарсенные данные ответа
     * @return mixed
     */
    public function data()
    {
        return $this->_data;
    }

    /**
     * Возвращает полную строку ответа
     * @return string
     */
    public function response()
    {
        return $this->_response;
    }

    /**
     * Устанавливает успешный результат выполнения запроса
     * @param mixed $data Распарсенные данные ответа
     */
    public function setSuccess($data)
    {
        $this->_success     = true;
        $this->_data        = $data;
    }

    /**
     * Устанавливает неудачный результат запроса
     * @param string $error сообщение об ошибке
     * @param int $errno Номер (код) ошибки
     */
    public function setFault($error, $errno = 0)
    {
        $this->_success     = false;
        $this->_error       = $error;
        $this->_errno       = $errno;
    }
}
?>