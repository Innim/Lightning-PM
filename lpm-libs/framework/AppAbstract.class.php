<?php
namespace GMFramework;

/**
 * Базовый класс приложения<br/>
 * @package ru.vbinc.gm.framework
 * @author greymag <greymag@gmail.com>
 * @copyright 2013
 * @version 0.2
 */
abstract class AppAbstract
{	

	/**
	 * Опции проекта
	 * @var Options
	 */ 
	protected $_options = null;

    function __construct() 
    {
        
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
	public function isDebug() 
    {
		return GMFramework::isDebugMode();
	}

	/**
	 * Определяет, запущено ли приложение локально в рамках проекта. 
	 * @return Boolean
	 * @see Globals::isLocalMode()
	 */
	public function isLocal() 
    {
		return GMFramework::isLocalMode();
	}

    /**
	 * Создает инстанцию класса опций.
	 * По умолчанию будет возвращено <code>null</code>
	 * @return Options|null
	 * @see Options
	 */
	protected function createOptions() 
    {
    	return null;
    }
}
?>