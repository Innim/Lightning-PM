<?php
namespace GMFramework;

/**
 * Переменные, передаваемые для приложения с ВКонтакте
 * @package ru.vbinc.gm.framework.social.VKontakte
 * @author GreyMag
 * @version 0.1
 */
class VKParams extends SocParams
{
	/**
	 * id пользователя, со страницы которого было запущено приложение.
     * Если приложение запущено не со страницы пользователя, то значение равно 0
	 * @var float
	 */
	public $ownerId; 
	/**
	 * id группы, со страницы которой было запущено приложение. Если приложение запущено не со страницы группы, то значение равно 0
	 * @var float
	 */
	public $groupId; 
	/**
	 * Тип пользователя, который просматривает приложение
	 * @var int
	 */
	public $viewerType;

	public function parse( $params )
	{
		parent::parse( $params );
		$this->apiId      = $this->getFloatVar  ( 'api_id'      );
		$this->authKey    = $this->getStringVar ( 'auth_key'    );
		$this->viewerId   = $this->getFloatVar  ( 'viewer_id'   );
		$this->isAppUser  = $this->getBooleanVar( 'is_app_user' );
		$this->ownerId    = $this->getFloatVar  ( 'user_id'     );
		$this->groupId    = $this->getFloatVar  ( 'group_id'    );
		$this->viewerType = $this->getIntVar    ( 'viewer_type' );
		$this->vid        = $this->viewerId;
	}
}
?>