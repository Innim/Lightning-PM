<?php
/**
 * Класс, хранящий информацию для записи в лог
 * @package ru.vbinc.gm.framework.utils.log
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2011
 * @version 0.1
 * 
 * @property-read string $text Строка для записи в лог
 * @property-read string $logDate Дата в формате для записи в лог
 */
class GMLogMessage
{
	protected $_text;
	protected $_comment;
	protected $_class;
	protected $_date; 
	
	function __construct( $text, $comment, $class )
	{
		$this->_text    = $text;
		$this->_comment = $comment;
		$this->_class   = $class;
		$this->_date    = DateTimeUtils::date();
	}
	
	public function __get( $var )
	{
		switch( $var )
		{
			case 'text'    : return $this->_text;
			case 'logDate' : {
				return DateTimeUtils::date( "d/m/Y, H:i:s" );
			}
		}
	}
	
	/**
	 * Получить сформированную строку логов 
	 */
	public function getLogString()
	{
		return 'Object : ' . $this->_class . 
		       ( $this->_comment != '' ? ' (' . $this->_comment . ')' : '' ) . 
		       ' [' . $this->logDate . ']' . "\n\r" .
		       $this->_text;
               
	} 
}
?>