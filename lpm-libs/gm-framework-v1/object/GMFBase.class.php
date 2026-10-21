<?php
/**
 * Базовый класс фреймворка
 * @package ru.vbinc.gm.framework.amfphp
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.7
 * @access protected
 * @see GMFBase::getGlobals()
 * 
 * @property-read string $error Ошибка
 * @property-read int $errno Номер ошибки
 */ 
abstract class GMFBase
{
    /**
     * Cоединение с БД
     * @var DBConnect
     */
    protected $_db;
    /**
     * Глобальные настройки проекта
     * @var Globals
     */
    protected $_globals;
    /**
     * Номер ошибки
     * @var int
     */
    protected $_errno = 0;
    /**
     * Текст ошибки
     * @var string
     */
    protected $_error = '';
    /**
     * Использовать поддержку нескольких языков или нет
     * @var boolean
     */
    protected $_useMultiLang = false;

    function __construct( $createDBC = true, $useMultilang = false )
    {
        $this->_globals = $this->getGlobals();

        if ($createDBC) $this->_db = $this->_globals->getDBConnect();

        $this->_useMultiLang = $useMultilang;
    }
    
    /**
     * @return the $error
     * @return the $errno
     */
    public function __get( $var )
    {
        switch( $var )
        {
            case 'error' : return $this->_error; break;
            case 'errno' : return $this->_errno; break;
            default : return;
        }
    }

    /**
     * Возвращает глобальные настройки проекта.<br/>
     * по умолчанию возвращается инстанция класса <code>DefaultGlobals</code><br/>
     * После вызова конструктора ссылка на объект настроек 
     * сохраняется в свойстве <code>$_globals</code> 
     * и далее рекомендуется использовать именно это свойство
     * @var Globals
     * @see Globals
     * @see DefaultGlobals
     * @see GMBase::$_globals
     */ 
    protected function getGlobals() {
        return DefaultGlobals::getInstance();
    }
    
    /**
     * Возвращает строку в нужной локали
     * @param string $str
     */
    protected function __( $str )
    {
        return $this->_useMultiLang === true ? Lang::getString( $str ) : $str; 
    }
    
}
?>