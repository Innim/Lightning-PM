<?php
namespace GMFramework;

/**
 * Защищённый запрос к api Мой мир@mail.ru
 * @package ru.vbinc.gm.framework.social.mail
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.2
 */
class MailRequest extends SocRequest
{	
    /**
     * Метод отправляет уведомление пользователю
     */
    const METHOD_SEND_NOTIFICATION = 'notifications.send';
    /**
     * Таймаут между запросами в миллисекундах 
     * ( не больше трех в секунду )
     */
    const REQUEST_DELAY = 300;

	function __construct( $apiId, $secureCode, $apiUrl = 'http://www.appsmail.ru/platform/api', $testMode = false )
	{
		parent::__construct( $apiId, $secureCode, $apiUrl, $testMode );
		     
        $this->_parameters['format'] = SocRequest::FORMAT_JSON;
        $this->_parameters['app_id'] = $this->_apiId;
        $this->_parameters['secure'] = 1;
        
        $this->_usePost = true;						
	}
	
    public function request( $method, $parameters = array() )
    {       
        $this->_parameters['method'] = $method;        

        return parent::request( $method, $parameters ); 
    }
	
	protected function parseAnswer( $answer )
    {
        if (!$answer = @json_decode( $answer )) {
        	$this->error = 'Answer parsing error';
        	return false;
        }
        
        if (isset( $answer->error )) {
        	$this->errno = $answer->error->error_code;
            $this->error = $answer->error->error_msg;
            $this->requestFields = $this->_parameters;
            return false;
        }
        
        return $answer;
    }

    /**
     * Формирует строку запроса
     */
    protected function createQueryString()
    {
        $string = parent::createQueryString();

        $string .= 'sig=' . $this->createSIG();

        return $string;
    }	
}
?>