<?php
namespace GMFramework;

/**
 * Базовый amfphp - cервис для проектов под мой мир@mail.ru 
 * @package ru.vbinc.gm.framework.social.mail
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.1
 */
class MailApi extends SocApi
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
  		 
  		$this->_invoker = new MailRequest( $appId, $this->_secureCode, API_URL );
  		$this->_info    = new MailParams();
  	}         
    
    public function sendNotification( $toUids, $message )
    {
        return $this->doSendNotification( 
                                          $toUids, 
                                          $message,
                                          MailRequest::METHOD_SEND_NOTIFICATION,
                                          200,
                                          'uids',
                                          'text' 
                                         );     
    }

    protected function getAuthKey()
    {                       
        $arr = $this->_info->params;
        ksort( $arr );
        unset( $arr['userId'], $arr['sig'], $arr['viewer_id'], $arr['files_url'], $arr['app_url'] );

        $str = '';
        foreach ($arr as $k => $v) {
            $str .= $k . '=' . $v;
        }
        
        return md5( $str . $this->_secureCode ) ;
    }
    
    protected function getRequestDelay()
    {
        return MailRequest::REQUEST_DELAY;
    }
}
?>