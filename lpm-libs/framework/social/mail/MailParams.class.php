<?php
namespace GMFramework;

/**
 * Переменные, передаваемые для приложения с Мой мир@mail.ru
 * @package ru.vbinc.gm.framework.social.mail
 * @author GreyMag & Antonio
 * @version 0.1
 */
class MailParams extends SocParams
{	
    /**
     * Идентификатор в соц. сети пользователя, установившего приложение
     * @var string
     */
    public $ownerId;
    /**
     * Идентификатор сессии
     * @var string
     */
    public $sessionKey;
    /**
     * Определяет место, из которого открыто приложение
     * @var string
     */
    public $view;
    
    /**
     * 
     *  app_id	int	идентификатор вашего приложения
        session_key	string	идентификатор сессии
        session_expire	timestamp	время в формате unixtime когда сессия перестанет быть валидной
        oid	uint64	идентификатор пользователя, установившего приложение
        vid	uint64	идентификатор пользователя, запустившего приложение
        is_app_user	bool	флаг, обозначающий установил ли приложение пользователь просматривающий приложение (1 — установил, иначе 0)
        ext_perm	string	пользовательские настройки приложения; значением данного параметра является перечисление через запятую настроек пользователя, описанных в документации к методу users.hasAppPermissionrest; например: ext_perm=stream,notifications
        window_id	string	идентификатор окна, в котором запущено приложение
        view	string	определяет место, из которого открыто приложение
        referer_type	string	определяет тип реферера (см. Бонус за друга); необязательный параметр
        referer_id	string	определяет id реферера (см. Бонус за друга); необязательный параметр
        sig	string	
    */	
    
    /**
     * Распарсить параметры, пришедшие с клиента
     * @param array $params 
     */
    public function parse( $params )
    {
        parent::parse( $params );
        ksort( $this->_params );
         
        $this->apiId      = $this->getFloatVar  ( 'app_id'      );
        $this->viewerId   = $this->getFloatVar  ( 'viewer_id'   );
        $this->authKey    = $this->getStringVar ( 'sig'         );
        $this->vid        = $this->getStringVar ( 'vid'         );                
        $this->ownerId    = $this->getStringVar ( 'oid'         );
        $this->sessionKey = $this->getStringVar ( 'session_key' );
        $this->isAppUser  = $this->getBooleanVar( 'is_app_user' );
    }
    
    /**
     * Установить идентификатор пользователя внутри приложения
     * @param float $id
     */
    public function setviewerId( $id )
    {
        $this->viewerId = $id;
        //$this->userId = $id;        
    }
}
?>