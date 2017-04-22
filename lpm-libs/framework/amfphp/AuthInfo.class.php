<?php
namespace GMFramework;

/**
 * Информация авторизации, которая хранится в сессии
 * @package ru.vbinc.gm.framework.amfphp
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 */
class AuthInfo
{	
	/**
	 * Логин
	 * @var string
	 */
	public $login;
		 
	public function __construct( $login )
	{
		$this->login = $login;
	}
}
?>