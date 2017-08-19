<?php
namespace GMFramework;

/**
 * Переменные, передаваемые для приложения с социальной сети
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @version 0.1
 * @property-read array $params Переданные параметры
 */
class SocParams
{
	/**
     * Идентификатор в соц. сети пользователя, запустившего приложение 
     * (это может быть его идентификатор в приложении в соц. сети)
     * @var string
     */
    public $vid;
    /**
	 * Идентификатор приложения
	 * @var float
	 */
	public $apiId;
	/**
	 * Идентификатор пользователя, который просматривает приложение<br/>
	 * Здесь имеется ввиду внутренний идентификатор пользователя в приложении. 
	 * В общем случае он может не сопдать с идентификатором пользователя в социальной сети и
	 * идентификатором пользователя в приложении в социальной сети
	 * @var float
	 */
	public $viewerId;
	/**
	 * Ключ, необходимый для авторизации пользователя
	 * @var string
	 */ 
	public $authKey; // это 
	/**
	 * Установлено ли пользователем приложение
	 * @var boolean
	 */
	public $isAppUser;
	
	/**
	 * Переданные параметры
	 * @var array
	 */
	protected $_params;
	
	function __construct( $params = null )
	{
	   if ($params != null) $this->parse( $params );
	}
	
	function __get( $var )
	{
		if ($var == 'params') return $this->_params;
	}
	
	/**
	 * Распарсить параметры, пришедшие с клиента
	 * @param array $params 
	 */
	public function parse( $params )
	{
		if (!is_array( $params )) $params = get_object_vars( $params );
		$this->_params = $params;
	}
	
	protected function getVar( $name, $type )
	{
		$value = isset( $this->_params[$name] ) ? $this->_params[$name] : '';
		
		settype( $value, $type );
		
		return $value;
	}
	
	protected function getIntVar( $name )
	{
		return $this->getVar( $name, TypeConverter::TYPE_INTEGER );
	} 
    
    protected function getFloatVar( $name )
    {
        return $this->getVar( $name, TypeConverter::TYPE_FLOAT );
    }
    
    protected function getStringVar( $name )
    {
        return $this->getVar( $name, TypeConverter::TYPE_STRING );
    }
    
    protected function getBooleanVar( $name )
    {
        return $this->getVar( $name, TypeConverter::TYPE_BOOLEAN );
    }
}
?>