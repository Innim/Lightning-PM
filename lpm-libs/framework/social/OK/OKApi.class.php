<?php
namespace GMFramework;

/**
 * Базовый amfphp - cервис для проектов под ВКонтакте
 * @package ru.vbinc.gm.framework.social.OK
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.1
 */

class OKApi extends SocApi
{	
    function __construct($appId = null, $secureCode = null)
  	{
          parent::__construct($secureCode); 

          if ($appId === null) 
          {
            if (!defined('API_ID'))
              throw new Exception('Не установлен идентификатор приложения! Передайте параметр в конструктор VKApi или определите константу API_ID');
            $appId = API_ID;
          }
          
      		// создаем класс для запросов
      		$this->_invoker = new OKRequest( $appId, $this->_secureCode, APPLICATION_KEY, API_URL ); 
          
          // создаём класс для информации
          $this->_info = new OKParams(); 
  	}

    public function sendNotification( $toUids, $message )
    {
        return $this->doSendNotification( 
                                          $toUids, 
                                          $message,
                                          OKRequest::METHOD_SEND_NOTIFICATION,
                                          1,
                                          'uid',
                                          'text' 
                                         );     
    }
	
	protected function getAuthKey()
    {       
        //ksort( $this->_info->flashVars );
        $str = '';
        
        $arr = $this->_info->params;
        //unset( $arr['userId'], $arr['sig'], 
        //       $arr['viewer_id'], $arr['files_url'] );
        unset( $arr['sig'] );
        ksort( $arr );
        
        foreach ($arr as $k => $v){
            $str .= $k ."=". $v;
        } 
              
        return md5( $str . SECURE_CODE ) ;
    }
    
    protected function getRequestDelay()
    {        
        return OKRequest::OK_REQUEST_DELAY;
    }   
}
?>