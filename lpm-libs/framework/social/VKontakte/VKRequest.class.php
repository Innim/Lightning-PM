<?php
namespace GMFramework;

/**
 * Защищённый запрос к api ВКонтакте
 * @package ru.vbinc.gm.framework.social.VKontakte
 * @author GreyMag
 * @copyright 2009
 * @version 1.6
 */
class VKRequest extends SocRequest
{
	/**
	 * Метод отправляет уведомление пользователю
	 */
	const METHOD_SEND_NOTIFICATION = 'secure.sendNotification';
	/**
	 * Метод сохраняет строку статуса приложения
	 * для последующего вывода в общем списке приложений на странице пользоваетеля
	 */
	const METHOD_SAVE_APP_STATUS = 'secure.saveAppStatus';
	/**
	 * Метод возвращает платежный баланс приложения
	 */
	const METHOD_GET_APP_BALANCE = 'secure.getAppBalance';
	/**
	 * Метод возвращает баланс пользователя на счету приложения
	 */
	const METHOD_GET_BALANCE = 'secure.getBalance';
	/**
	 * Метод списывает голоса со счета пользователя на счет приложения
	 */
	const METHOD_WITHDRAW_VOTES = 'secure.withdrawVotes';
	/**
	 * Метод возвращает историю транзакций внутри приложения
	 */
	const METHOD_GET_TRANSACTIONS_HISTORY = 'secure.getTransactionsHistory';
	/**
	 * Таймаут между запросами в миллисекундах
	 * ( не больше трех в секунду )
	 */
	const KONTAKT_REQUEST_DELAY = 300;
	

    /**
     * Посланные поля
     * Нужны при ошибке
     */
    public $requestFields;
    
    //private $_v = '2.0'; // версия API - необязательный параметр
    public function __construct( $apiId, $secureCode, $apiUrl = 'http://api.vkontakte.ru/api.php', $testMode = false )
	{
		parent::__construct( $apiId, $secureCode, $apiUrl, $testMode );

		$this->_parameters['api_id'] = $this->_apiId;
		$this->_parameters['v']      = '2.0';
		$this->_parameters['format'] = SocRequest::FORMAT_JSON;

		if( $this->_testMode )
		{
			$this->_parameters['test_mode'] = '1';
		}
	}

	/**
	 * SecureRequest::request()
	 * Отправка запроса на ВКонтакте
	 * @param mixed $method Название метода
	 * @param array $parameters Массив передаваемых параметров со значениями
	 * @return Объект ответа с ВКонтакте
	 */
	public function request( $method, $parameters = array() )
	{
		$this->_parameters['method'   ] = $method;
		$this->_parameters['timestamp'] = $this->getTimestamp();
        $this->_parameters['random'   ] = $this->getRandom();

		return parent::request( $method, $parameters );	
	}
    
    protected function parseAnswer( SocRequestResult $result )
    {
        $answer = $result->response();
        $answer = @json_decode( $answer );

        if (!isset( $answer->response ))
        {
            if (isset( $answer->error ))
            {
                $result->setFault($answer->error->error_msg, $answer->error->error_code);
                //$this->errno = $answer->error->error_code;
                //$this->error = $answer->error->error_msg;
                //$this->requestFields = $answer->error->request_params;
            }
            else
            {
                $result->setFault('Неопознанная ошибка');
            } 
        }
        else 
        {
            $result->setSuccess($answer->response);
        }
    }

	/**
	 * Формирует строку запроса
	 */
	protected function createQueryString()
	{
		$string = parent::createQueryString();

		$string .= '&sig=' . $this->createSIG();

		return $string;
	}
    
    /**
     * Возвращает случайную строку для обеспечения уникальности запроса
     * @return string
     */
    protected function getRandom()
    {
        return BaseString::randomStr();
    }
}
?>