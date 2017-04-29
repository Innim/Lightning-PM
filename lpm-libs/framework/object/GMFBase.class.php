<?php
namespace GMFramework;

/**
 * Базовый класс фреймворка
 * @package ru.vbinc.gm.framework.amfphp
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.7
 * @access protected
 * 
 * @property-read string $error Ошибка
 * @property-read int $errno Номер ошибки
 * @deprecated Устаревший базовый объект
 */ 
abstract class GMFBase
{
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

    function __construct( /*$createDBC = true,*/$useMultilang = false )
    {
        //if ($createDBC) $this->_db = $this->_globals->getDBConnect();

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
     * Возвращает строку в нужной локали
     * @param string $str
     */
    protected function __( $str )
    {
        return $this->_useMultiLang === true ? Lang::getString( $str ) : $str; 
    }
    
}
?>