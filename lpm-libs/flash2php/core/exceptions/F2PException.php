<?php
/**
 * @version 0.1
 * @author GreyMag <greymag@gmail.com>
 * 
 * @property-read string $error Текст сообщения об ошибке
 * @property-read int $errno Номер ошибки
 *
 */
class F2PException extends Exception
{
	/**
	 * Неизвестный сервис
	 * @var int
	 */
	const ERRNO_UNKNOWN_SERVICE      = 19001;
	/**
     * Неизвестный метод
     * @var int
     */
    const ERRNO_UNKNOWN_METHOD       = 19002; 
    /**
     * Неверные аргументы
     * @var int
     */
    const ERRNO_WRONG_PARAMS         = 19003;
	/**
     * Неверное количество аргументов
     * @var int
     */
    const ERRNO_WRONG_NUM_ARGS       = 19004;	
	/**
     * Вызов заблокирован функцией beforeFilter
     * @var int
     */
    const ERRNO_AUTH_BLOCKED         = 19005; 
    /**
     * Ошибка при вызове метода
     * @var int
     */
    const ERRNO_CALL_METHOD_ERROR    = 19006;   
	/**
     * Ошибка при исполнении метода
     * @var int
     */
    const ERRNO_EXECUTE_METHOD_ERROR = 19007;     
    /**
     * Ошибка: невозможно использовать сжатие -
     * модуль с требуемыми функциями не подключё  
     * @var int
     */
    const ERRNO_COMPRESS_UNAVAILABLE = 19008; 
	
	/**
	 * @var string
	 */
	//protected $_error;
	/**
     * @var int
     */
    //protected $_errno;
	
	/**	
	 * @param $error Текст сообщения об ошибке
	 * @param $errno Номер ошибки
	 */
	function __construct( $error = '', $errno = 0 )
	{
		parent::__construct( $error, $errno );
		$this->_error = $error;
		$this->_errno = $errno;
	}
	
	function __get( $var )
	{
		switch( $var ) 
		{
			case 'error' : return $this->getMessage();//$this->_error;
			case 'errno' : return $this->getCode();//$this->_errno;
		}
	}
}
?>