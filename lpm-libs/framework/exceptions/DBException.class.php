<?php
namespace GMFramework;

/**
 * Исключение, возникающее при работе с БД
 * @package ru.vbinc.gm.framework.db
 * @author GreyMag <greymag@gmail.com>
 * @version 0.2
 */
class DBException extends Exception
{
	private $_db;
	
	/**
	 * 
	 * @param DBConnect $db
	 * @param string $message Сообщение. 
	 * Если не передано - будет использовано значение <code>$db->error</code> 
	 * @param int $code Код исключения
	 */
	public function __construct( DBConnect $db, $message = null, $code = null )
	{
		parent::__construct( $message, $code );
		$this->_db = $db;
		if ($message == null) 
			$this->message = $db->error;
		/*if ($code == null) {
			$this->message = $db->errno;
		}*/
	}
	
	/**
	 * 
	 * @return DBConnect
	 */
	public function getDB() {
		return $this->_db;
	}
}
?>