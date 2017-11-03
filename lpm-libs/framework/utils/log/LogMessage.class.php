<?php
namespace GMFramework;

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
class LogMessage
{
	protected $_text;
	protected $_comment;
	protected $_class;
	protected $_date; 
	private $_mtime = null;
	
	function __construct($text, $comment, $class, $useMicrotime = false)
	{
		$this->_text    = $text;
		$this->_comment = $comment;
		$this->_class   = $class;

		if ($useMicrotime)
		{
			$mtime = explode(' ', microtime());
			$this->_mtime = (float)$mtime[0] * 1e+3;
			$this->_date = (float)$mtime[1] + DateTimeUtils::getTimeadjust();
		}
		else 
		{
			$this->_date = DateTimeUtils::date();
		}
	}
	
	public function __get( $var )
	{
		switch( $var )
		{
			case 'text'    : return $this->_text;
			case 'logDate' : {
				$date = DateTimeUtils::date("d/m/Y, H:i:s", $this->_date);
				if (null !== $this->_mtime) $date .= '.' . ($this->_mtime >> 0);
				return $date;
			}
		}
	}
	
	/**
	 * Получить сформированную строку логов 
	 */
	public function getLogString()
	{
		/*return 'Object : ' . $this->_class . 
		       ( $this->_comment != '' ? ' (' . $this->_comment . ')' : '' ) . 
		       ' [' . $this->logDate . ']' . "\n\r" .
		       $this->_text;*/
        return '[' . $this->logDate . '] ' .
        		( $this->_comment != '' ? ' ' . $this->_comment . ': ' : '' ) .
        		$this->_text;
	} 
}
?>