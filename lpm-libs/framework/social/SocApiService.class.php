<?php
namespace GMFramework;

/**
 * Базовый amfphp - cервис для проектов под соц. сети с использованием из api 
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @copyright 2011
 * @version 0.2
 */
abstract class SocApiService extends Service
{
	/**
	 * Параметры, передаваемые с соц. сети
	 * @var SocParams
	 */
	protected $_info; 
    /**
     * Вызванный метод
     * @var String
     */
    protected $_method;
    /**
     * Методы, вызывать которые можно без авторизации
     * @var array
     */
    protected $_allowMethods = array();
    /**
     * Объект для взаимодействия с api соц. сети
     * @var SocApi
     */ 
    protected $_api;
    
    function __construct( $createDBC = true )
    {
    	parent::__construct( $createDBC );
    	
        $this->createApiObj();
    }

    /**
     * Метод, который вызывается перед вызовом любого метода
     * @param String $calledFunc
     * @return
     */
    public function beforeFilter( $calledFunc )
    {
        $this->_method = $calledFunc;
        if (!$info = $this->_api->getFlashvars()) {
        	if (!in_array( $calledFunc, $this->_allowMethods )) return false;
        } else {
            $this->_info = $info;
        } 
        return true;
    }
    
    /**
     * Отправляет уведомления пользователям
     * @param float || array $toUids id пользователя кому отправлять
     * или массив id'ов пользователей
     * @param string $message текст сообщения
     * @return boolean
     */
    protected function sendNotification( $toUids, $message )
    {
    	return $this->_api->sendNotification( $toUids, $message );
    }
    
    /**
     * Проверяет и сохраняет параметры, переданные на сервер
     * @param $flashvars
     * @return boolean
     */
    protected function setFlashvars( $flashvars )
    {
        if (!$this->_api->checkFlashvars( $flashvars )) {
        	$this->error( $this->_api->error );
        	return false;
        }
    	
        $this->_info = $this->_api->getParams();//SocGlobals::getFlashVars();

        return true;
    } 
    
    /**
     * Инициализирует свойство $_api
     */
    protected function createApiObj()
    {
        // по умолчанию, пытаемся использовать константу PLATFORM
        // возможные значения этой константы приведены в SocGlobals::PLATFORM_*   
        // если это не подходит - надо переопределить этот метод
        $err = '';
        $platform = $this->getPlatform();
        if ($platform !== false) {
            $appId = $this->getAppId();
            $secure = $this->getSecureCode();
            switch ($platform)
            {
                case SocPlatform::VKONTAKTE     : $this->_api = new VKApi  ($appId, $secure); break;
                case SocPlatform::MAILRU        : $this->_api = new MailApi($appId, $secure); break;
                case SocPlatform::ODNOKLASSNIKI : $this->_api = new OKApi  ($appId, $secure); break;
                case SocPlatform::FACEBOOK      : $this->_api = new FBApi  ($appId, $secure); break;
                default                         : $err = 'Неизвестная платформа';
            }
        } else $err = 'Платформа не определена';
        
        if ($err != '') throw new Exception($err . '! Выставьте допустимое значение константы PLATFORM в файле конфигурации или переопределите метод ' . __CLASS__ . '::getPlatform()!' );
    }

    protected function getPlatform()
    {
        return defined('PLATFORM') ? PLATFORM : false;
    }

    protected function getSecureCode()
    {
        return null;
    }

    protected function getAppId()
    {
        return null;
    }
}
?>