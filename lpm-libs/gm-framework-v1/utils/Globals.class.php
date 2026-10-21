<?php
/**
 * Глобальный класс приложения, использующийся в проектах для хранения глобальных опций
 * и обеспечения более удобной работы.<br/>
 * При наследовании рекомендуется создать поле для хранения инстанции ($_instance)
 * <code>
 *  protected static $_instance = null;
 * </code>
 * @package ru.vbinc.gm.framework.utils
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2010
 * @version 0.5
 * @see Globals::$_instance
 * @see DefaultGlobals
 */
abstract class Globals
{	
	/**
	 * Свойство, в котором будет храниться ссылка на инстанцию. 
	 * рекомендуется создать аналогичное свойство в наследнике, 
	 * который используется в проекте
	 * @var Globals
	 * @see DefaultGlobals
	 */ 
    protected static $_instance = null;
    /**
     * Возвращает рабочую инстанцию глобального класса
     * @return Globals
     */
    public static function getInstance() {
        if (static::$_instance === null) {
        	$class = get_called_class();
        	static::$_instance = new $class();
        }
        return static::$_instance;
    }

	/**
	 * Определяет, включен ли режим отладки для приложения. 
	 * Для включения такого режима необходимо определить константу <code>DEBUG</code>
	 * и установить ей значение <code>true</code>.
	 * @return boolean
	 */
	public static function isDebugMode()
	{
		return ( defined( 'DEBUG' ) && DEBUG );
	}

	/**
	 * Определяет, локально ли запущено приложение. 
	 * Для включения такого редима необходимо определить константу <code>_LOCAL</code>
	 * и установить ей значение <code>true</code>.
	 * @return boolean
	 */ 
    public static function isLocalMode()
    {
        return ( defined( '_LOCAL' ) && _LOCAL );
    }

	/**
	 * Соединение с БД
	 * @var DBConnect
	 */
	protected $_dbConnect = null;
	/**
	 * Опции проекта
	 * @var Options
	 */ 
	protected $_options = null;

    function __construct() {
        
    }

	/**
	 * Возвращает соединение с БД
	 * @return DBConnect
	 */ 
	public function getDBConnect()
	{
		if ($this->_dbConnect === null) $this->_dbConnect = $this->createDBConnect();
		return $this->_dbConnect;
	}

	/**
	 * Возвращает опции проекта
	 * @return DBConnect
	 */ 
	public function getOptions()
	{
		if ($this->_options === null) $this->_options = $this->createOptions();
		return $this->_options;
	}

	/**
	 * Определяет, включен ли режим отладки для приложения в рамках проекта. 
	 * @return Boolean
	 * @see Globals::isDebugMode()
	 */
	public function isDebug() {
		return self::isDebugMode();
	}

	/**
	 * Определяет, запущено ли приложение локально в рамках проекта. 
	 * @return Boolean
	 * @see Globals::isLocalMode()
	 */
	public function isLocal() {
		return self::isLocalMode();
	}

    /**
	 * Создает инстанцию соединения с базой данных для работы проекта.
	 * По умолчанию сздается новый экземпляр <code>DBConnect</code> без параметров
	 * @return DBConnect
	 * @see DBConnect
	 */
	protected function createDBConnect() {
    	return new DBConnect();
    }

    /**
	 * Создает инстанцию класса опций.
	 * По умолчанию будет возвращено <code>null</code>
	 * @return Options|null
	 * @see Options
	 */
	protected function createOptions() {
    	return null;
    }
}
?>