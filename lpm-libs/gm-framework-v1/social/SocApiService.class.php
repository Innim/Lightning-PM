<?php
/**
 * Базовый amfphp - cервис для проектов под соц. сети с использованием из api 
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @copyright 2011
 * @version 0.2
 */
class SocApiService extends Service
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
    	
        $this->_info = SocGlobals::getFlashVars();

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
        if (defined( 'PLATFORM' )) {
            switch (PLATFORM)
            {
                case SocGlobals::PLATFORM_VKONTAKTE     : $this->_api = new VKApi  (); break;
                case SocGlobals::PLATFORM_MAILRU        : $this->_api = new MailApi(); break;
                case SocGlobals::PLATFORM_ODNOKLASSNIKI : $this->_api = new OKApi  (); break;
                default                                 : $err = 'Неизвестная платформа';
            }
        } else $err = 'Платформа не определена';
        
        if ($err != '') throw new Exception( $err . '! Выставьте допустимое значение константы PLATFORM в файле конфигурации или непеопределите метод ' . __CLASS__ . 'createApiObj()!' );
    }
}
?>