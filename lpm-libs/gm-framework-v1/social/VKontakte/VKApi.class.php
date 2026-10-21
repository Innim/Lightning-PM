<?php
/**
 * Базовый amfphp - cервис для проектов под ВКонтакте
 * @package ru.vbinc.gm.framework.social.VKontakte
 * @author GreyMag
 * @copyright 2009
 * @version 1.2
 */
class VKApi extends SocApi
{	
	function __construct()
	{
		parent::__construct(); 
		
		// GMF2DO сделать получение apiurl из параметров		
		// создаем класс для запросов
		$this->_invoker = new VKRequest( API_ID, $this->_secureCode, API_URL ); 
		
		// создаём класс для информации
		$this->_info = new VKParams();		
	}

	/**	
	 * Перевод голосов со счёта пользователя на счёт приложения
	 * @param float $votes количество голосов
	 * @param integer $userId - id пользователя, по умолчанию - текущий
	 * @return boolean
	 */
	public function transferVotes( $votes, $userId = 0 )
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
	}

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
        return md5( $this->_info->apiId . '_' . $this->_info->vid . '_' . $this->_secureCode );
    }
    
	protected function getRequestDelay()
    {        
        return VKRequest::KONTAKT_REQUEST_DELAY;
    }
}
?>