<?php
namespace GMFramework;

/**
 * Класс для работы с ftp
 * @package ru.vbinc.gm.framework.net
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.1
 * @access public
 */
class FTP
{
	const MODE_FTP_ASCII  = FTP_ASCII;
	const MODE_FTP_BINARY = FTP_BINARY; 

	public $error;

	private $_connect;

	/**
	 * FTP
	 * Класс для работы с FTP
	 * @param string $host имя хоста
	 * @param string $login имя пользователя ftp
	 * @param string $pass пароль пользователя ftp
	 * @param integer $port номер порта, через который осуществляется соединение
	 * @param integer $timeout максимально время выполнения сценария
	 * @return void
	 */
	function __construct( $host, $login = '', $pass = '', $port = 21, $timeout = 90 )
	{
		$port = (int)$port;
		$timeout = (int)$timeout;

		$this->_connect = ftp_connect( $host, $port, $timeout );

		if( !$this->checkConnect() ) return false;
		if( $login != '' ) ftp_login( $this->_connect, $login, $pass );
	}

	/**
	 * FTP::put()
	 * Загрузить файл на удалённый сервер
	 * @param string $remoteFile имя на удалённом сервере
	 * @param string $localFile имя на локальном сервере
	 * @param integer $mode режим передачи
	 * @param integer $startPos начальная позиция
	 * @return bool
	 */
	public function put( $remoteFile, $localFile, $mode, $startPos = 0 )
	{
		if( !$this->checkConnect() ) return false;

		$mode = $this->checkMode( $mode );
		$startPos = (int)$startPos;

		if( ftp_put( $this->_connect, $remoteFile, $localFile, $mode, $startPos ) ) return true;
		else {
			$this->error = "Ошибка загрузки файла";
			return false;
		}
	}

	/**
	 * FTP::fPut()
	 * Чтение и загрузка файла на сервер
	 * @param mixed $remoteFile
	 * @param integer $openFile дескриптор открытого файла
	 * @param integer $mode режим передачи
	 * @return
	 */
	public function fPut( $remoteFile, $openFile, $mode )
	{
		if( !$this->checkConnect() ) return false;

		$mode = $this->checkMode( $mode );

		if( ftp_fput( $this->_connect, $remoteFile, $openFile, $mode ) ) return true;
		else {
			$this->error = "Ошибка загрузки открытого файла";
			return false;
		}
	}

	/**
	 * FTP::mkDir()
	 * Создание директории
	 * @param string $directory имя создаваемой директории
	 * @return имя созданной директории или false в случае ошибки
	 */
	public function mkDir( $directory )
	{
		if( !$this->checkConnect() ) return false;

		if( $name = ftp_mkdir( $this->_connect, $directory ) ) return $name;
		else {
			$this->error = "Ошибка создания директории";
			return false;
		}
	}

	/**
	 * FTP::close()
	 * Закрываем соединение
	 * @return void
	 */
	public function close()
	{
		if( $this->checkConnect() ) ftp_quit( $this->_connect );
	}

	/**
	 * FTP::checkConnect()
	 * Проверка соединения
	 * @return bool
	 */
	protected function checkConnect()
	{
		if( !$this->_connect )
		{
			$this->error = "Ошибка соединения";
			return false;
		}
		else return true;
	}

	/**
	 * FTP::checkMode()
	 * Проверка режима
	 * @param integer $mode проверяемый режим
	 * @return integer режим
	 */
	protected function checkMode( $mode )
	{
		switch( $mode )
		{
			case FTP::MODE_FTP_ASCII :
			case FTP::MODE_FTP_BINARY : return $mode;
			default : return FTP::MODE_FTP_ASCII;
		}
	}
}
?>