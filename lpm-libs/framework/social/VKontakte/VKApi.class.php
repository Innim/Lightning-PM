<?php
namespace GMFramework;

/**
 * Базовый amfphp - cервис для проектов под ВКонтакте
 * @package ru.vbinc.gm.framework.social.VKontakte
 * @author GreyMag
 * @copyright 2009
 * @version 1.2
 */
class VKApi extends SocApi
{	
	function __construct($appId = null, $secureCode = null, $apiUrl = null)
	{
		parent::__construct($secureCode); 

		if ($appId === null) 
		{
			if (!defined('API_ID'))
				throw new Exception('Не установлен идентификатор приложения! Передайте параметр в конструктор VKApi или определите константу API_ID');
			$appId = API_ID;
		}

		if ($apiUrl === null) 
		{
			$apiUrl = defined('API_URL') ? API_URL : null;
		}
			
		// создаем класс для запросов
		$this->_invoker = new VKRequest($appId, $this->_secureCode, $apiUrl); 
		
		// создаём класс для информации
		$this->_info = new VKParams();		

		
		// GMF2DO сделать получение apiurl из параметров	
	}

	/**	
	 * Перевод голосов со счёта пользователя на счёт приложения
	 * @param float $votes количество голосов
	 * @param integer $userId - id пользователя, по умолчанию - текущий
	 * @return boolean
	 */
	/*public function transferVotes( $votes, $userId = 0 )
	{
		if ($votes == 0) return true;
		elseif ($votes < 0) {
			$this->error( 'Некорректная сумма голосов' );
			return false;
		}

		$userId = ( $userId == 0 ) ? $this->_info->vid : (float)$userId;

		$votes = (int)( abs( $votes ) * 100 );

		if ($result = $this->_invoker->request( VKRequest::METHOD_WITHDRAW_VOTES, 
		                                        array( 'uid' => $userId, 'votes' => $votes ) ))
		{
			if ((int)$result == $votes) return true;
			else {
				$this->error( 'Ошибка перевода голосов' );
				return false;
			}
		}
		else {
			$this->error( ( $this->_invoker->errno == 502 ) 
			                 ? 'У Вас на счету нет голосов' 
			                 : $this->_invoker->errno . ': ' . $this->_invoker->error );
			return false;
		}
	}*/

	public function sendNotification( $toUids, $message )
	{
		return $this->doSendNotification( 
		                                  $toUids, 
		                                  $message,
		                                  VKRequest::METHOD_SEND_NOTIFICATION,
		                                  100,
		                                  'uids',
		                                  'message' 
		                                 );		
	}

    // получаем код авторизации
    protected function getAuthKey()
    {
        return md5( $this->_invoker->getApiId() . '_' . $this->_info->vid . '_' . $this->_secureCode );
    }
    
	protected function getRequestDelay()
    {        
        return VKRequest::KONTAKT_REQUEST_DELAY;
    }
}
?>