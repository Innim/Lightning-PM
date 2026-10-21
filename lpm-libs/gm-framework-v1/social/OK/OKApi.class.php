<?php
/**
 * Базовый amfphp - cервис для проектов под ВКонтакте
 * @package ru.vbinc.gm.framework.social.OK
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.1
 */

class OKApi extends SocApi
{	
    function __construct()
	{
        parent::__construct(); 
        
		// создаем класс для запросов
		$this->_invoker = new OKRequest( API_ID, $this->_secureCode, APPLICATION_KEY, API_URL ); 
        
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