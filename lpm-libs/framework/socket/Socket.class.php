<?php
namespace GMFramework;

/**
 * Класс, позволяющий делать запросы к сокету
 * @package ru.vbinc.gm.framework
 * @author GreyMag <greymag@gmail.com>
 * @version 1.0
 */
class Socket
{
	private $_socket = null;
	private $_address;
	private $_port;
	
	function __construct( $address, $port ) 
	{
		$this->_address = $address;
		$this->_port    = $port;
	}
	
	function __destruct() 
	{
		$this->close();
	}
	
	/**
	 * Возвращает текущий адрес сокета
	 * @return string
	 */
	public function getAddress() 
	{
		return $this->_address;
	}	
	
	/**
	 * Возвращает текущий порт сокета
	 * @return string
	 */
	public function getPort() 
	{
		return $this->_port;
	}
	
	/**
	 * Подключение к сокету
	 * @return boolean
	 */
	public function connect() 
	{
		if (!$this->isConnected()) 
		{
			// создаем сокет
			if (!$this->_socket = socket_create( AF_INET, SOCK_STREAM, 0 )) return false;
			// устанавливаем соединение
			if (!@socket_connect( $this->_socket, $this->_address, $this->_port )) 
			{
				$this->_socket = null;
				return false;				 
			} 
			    
		}
		
		return true;
	}
	
	/**
	 * Отключение от сокета
	 */
	public function close() 
	{
		//socket_shutdown( $this->_socket );
		if ($this->_socket) socket_close( $this->_socket );
		$this->_socket = null;
	}
	
	/**
	 * 
	 * @param string $message
	 * @param boolean $read
	 */
	public function send( $message, $read = true ) 
	{
		if (!$this->connect()) return false;		
		$message .= "\0";
		
		if (@socket_write( $this->_socket, $message, strlen( $message ) ) === false) return false;
		//if (socket_send( $this->_socket, $message, strlen( $message ), MSG_EOF ) === false) return false;
		
		if ($read) 
		{
        	$response = '';        	
        	while ($reply = @socket_read( $this->_socket, 10240, PHP_BINARY_READ )) 
        	{
        		$response .= $reply;
        		if (substr( $reply, -1 ) == "\0") 
        		{
        			$response = substr( $response, 0, -1 );
        			break;
        		}
        	}

        	return $response;
        } else return true;
	}
    
    public function isConnected() 
    {
        return $this->_socket != null;
    }
}
?>