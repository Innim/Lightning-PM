<?php
namespace GMFramework;

/**
 * Защищённый запрос к api Одноклассников
 * @package ru.vbinc.gm.framework.social.OK
 * @author GreyMag & Antonio
 * @copyright 2011
 * @version 0.1
 */
class OKRequest extends SocRequest
{
	/**
	 * Метод отправляет уведомление пользователю
	 * @var String
	 */
	const METHOD_SEND_NOTIFICATION = 'notifications/sendSimple';
    /**
	 * Таймаут между запросами в миллисекундах
	 * (вообще ограничения нет, но на всякий случай небольшую ставим)
	 * @var int
	 */
	const OK_REQUEST_DELAY = 3;
    
    function __construct( $apiId, $secureCode, $appKey, $apiUrl = null, $testMode = false )
    {
        parent::__construct( $apiId, $secureCode, $apiUrl, $testMode );  
        $this->_usePost = false;     
        $this->_parameters['format'] = SocRequest::FORMAT_JSON;
        $this->_parameters['application_key'] = $appKey;   
    }
        
	public function request( $method, $parameters = array() )
	{		
		$this->_parameters['application_key'] = $this->_application_key;        

        $this->_requestUrl = $this->_apiUrl;
        if (substr( $this->_apiUrl, -1, 1) != '/') $this->_requestUrl .= '/';
        $this->_requestUrl .= $this->_method;
		        
        return parent::request( $method, $parameters ); 
	}
    
    protected function parseAnswer( $answer )
    {
        $jsonAnswer = @json_decode( $answer );
    	if (isset( $jsonAnswer ))
        {
            if (isset( $jsonAnswer->error_code )) {
        	   $this->errno = $jsonAnswer->error_code;
               $this->error = $jsonAnswer->error_msg;
               return false;
            } else return $jsonAnswer;
        }
        elseif ($answer == 'true')
        {
            return true;
        }       
        
        $this->error = 'Wrong answer: ' . $answer;
        return false;
    }
    
    protected function createQueryString()
	{
		$string = 'sig=' . $this->createSIG(). '&';
        
        $string .= parent::createQueryString();
        
        return $string;
	}  
}
?>