<?php
namespace GMFramework;

/**
 * Защищённый запрос к api соц. сети
 * @package ru.vbinc.gm.framework.social
 * @author GreyMag
 * @copyright 2011
 * @version 0.5
 */
abstract class SocRequest
{	
    /**
     * формат возвращаемых данных - xml
     * @var String
     */
    const FORMAT_XML = 'XML';
    /**
     * формат возвращаемых данных - json
     * @var String
     */
    const FORMAT_JSON = 'JSON';
	
	/**
	 * Посланные поля
	 */
	public $requestFields;
	 
	/**
	 * Секретный ключ для подписи запросов
	 * @var string
	 */
	protected $_secureCode; 	
	/**
	 * Идентификатор приложения
	 * @var float
	 */
	protected $_apiId; 
	/**
	 * Тестовый режим
	 * @var boolean
	 */
	protected $_testMode = false; 
	/**
	 * Массив передаваемых параметров
	 * @var array
	 */
	protected $_parameters = array();
	/**
	 * Адрес, к которому необходимо делать запрос
	 * @var String
	 */
	protected $_apiUrl;
	/**
	 * Полный utl для запросов к API 
	 * (по умолчанию равен $_apiUrl)
	 * @var unknown_type
	 */
	protected $_requestUrl;
	/**
	 * Вызываемый у API метод
	 * @var string
	 */
	protected $_method;
    /**
     * Использовать метод POST или GET для запросов к API
     * @var boolean
     */
	protected $_usePost = true;
	//private $_format; // формат возвращаемых данных – XML или JSON. По умолчанию JSON (на ВК по умолчанию XML) - необязательный параметр

	public function __construct( $apiId, $secureCode, $apiUrl = null, $testMode = false )
	{
		$this->_apiId  = $apiId;		
		$this->setApiUrl( $apiUrl );
		
		$this->_secureCode = $secureCode;
		
		//$this->_format = self::FORMAT_JSON;

		//$this->_parameters['api_id'] = $this->_apiId;
		//$this->_parameters['v']      = $this->_v;
		//$this->_parameters['format'] = $this->_format;

		$this->_testMode = $testMode;
	}

	public function getApiId()
	{
		return $this->_apiId;
	}

	public function setApiUrl($url)
	{
		$this->_apiUrl = $url;
		$this->_requestUrl = $this->_apiUrl;
	}

	/**
	 * Отправка запроса к API
	 * @param mixed $method Название метода
	 * @param array $parameters Массив передаваемых параметров со значениями
	 * @return SocRequestResult
	 */
	public function request( $method, $parameters = array() )
	{
		$this->_method = $method;
		
		if (is_array( $parameters ))
		  $this->_parameters = array_merge( $this->_parameters, $parameters );

		$queryString = $this->createQueryString();

		$curl = $this->initCurl( $queryString );

		$result = curl_exec( $curl );
		$error  = curl_getinfo( $curl );

		curl_close( $curl );
				
		if ( $error['http_code'] != "200" )
		{
			$response = new SocRequestResult($result);
			$response->setFault('Ошибка соединения', 0);
		}
		else
		{			
			$response = new SocRequestResult($result);
			$this->parseAnswer( $response );
		}

		return $response;
	}
	
	/**
	 * Обработка ответа от API-сервиса 
	 * @param string $answer
	 */
	abstract protected function parseAnswer( SocRequestResult $result );
	
	/**
	 * Инициализирует curl	
	 * @param $queryString строка запроса
	 * @return 
	 */
	protected function initCurl( $queryString )
	{
		$curl = curl_init();

		if (empty($this->_requestUrl))
			throw new GMSocException('Не определен URL для запросов к API');
			

		curl_setopt( $curl, CURLOPT_URL, $this->_requestUrl . ( $this->_usePost ? '' : '?' . $queryString ) );       
        if ($this->_usePost) {
        	curl_setopt( $curl, CURLOPT_POST, 1 );
        	curl_setopt( $curl, CURLOPT_POSTFIELDS, $queryString );
        }
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1);
        
        return $curl;
	}

	/**
	 * Формирует строку запроса
	 */
	protected function createQueryString()
	{
		$string = '';

		foreach ($this->_parameters as $name => $value) {
			if ($string != '') $string .= '&';
			$string .= $name . '=' . urlencode( $value );
		}
		
		return $string;
	}

	/**
	 * Создаёт подпись запроса
	 */
	protected function createSIG()
	{
		$string = '';

		ksort( $this->_parameters, SORT_STRING );

		foreach ($this->_parameters as $name => $value) $string .= $name . '=' . $value;

		$string .= $this->_secureCode;

		return md5( $string );
	}

	/**
	 * Возвращает текущее значение unixtime на сервере
	 */
	protected function getTimestamp()
	{
		return DateTimeUtils::date();
	}
}
?>