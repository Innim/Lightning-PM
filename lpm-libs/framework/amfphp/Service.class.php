<?php
namespace GMFramework;

/**
 * Service
 * Базовый сервис<br/>
 * При реализации рекомендуется перопределить метод <code>getGlobals()</code>
 * @package ru.vbinc.gm.framework.amfphp
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.7
 * @access public
 * @see GMFBase::getGlobals()
 */
class Service extends GMFBase
{
	
	/**
	 * Успешный результат вызова метода или нет 
	 * @var bool
	 */
	protected $_success = true;
	
	/**
	 * Ассоциативный массив <strong>поле</strong> => <strong>значение</strong>, 
	 * который будет передан в качестве ответа
	 * @var array
	 */
	protected $_answer = array();
	/**
	 * Данные которые отправляются в случае ошибки
	 * @var array
	 */
	protected $_errData = array();

	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Обработка исключения
	 * @param Exception $e
	 * @return array ассоциативный массив ответа на запрос
	 */
	protected function exception( \Exception $e )
	{
		return $this->error( $e->getMessage(), $e->getCode() );
	}

	/**
	 * Ошибка
	 * @param string $message
	 * @param int $errno
	 * @param int $useLang использовать перевод или нет. По умолчанию - используется при условии включении мультиязычности
     * @return array ассоциативный массив ответа на запрос
	 */
	protected function error( $message = '', $errno = 0, $useLang = true )
	{
		if ($this->_useMultiLang && $useLang) {
			$message = $this->__( $message );
		}
		
		if (!empty( $message )) 
		  $this->_error = $message;
		//elseif( !$this->error || $this->error == '' ) $this->error = $this->_db->error;
		// в дебаг режиме добавляем ошибку запроса к БД, если такая была
		/*if ($this->_globals->isDebug() && ($db = $this->_globals->getDBConnect())) {
			if ($db->errno > 0) 
				$this->_error .= ' (DB error #' . $db->errno . ': ' . 
								$db->error . ' [query: ' . $db->lastQuery . '])';
		}*/

		if ($errno != 0) $this->_errno = $errno;

		$this->_success = false;
		
		// а вдруг злобные хацкеры
        sleep( $this->getErrorDelay() );		

		return $this->answer();
	}

    protected function getErrorDelay()
    {
        if (GMFramework::dev()) return 0;
        return 1;
    }

	/**
	 * Добавляет значение в ответ
	 * @param string $name название поля
	 * @param mixed $value добавляемое значение
	 * @param boolean $use4Client Если этот флаг выставлен в true 
	 * и преданное значение является объектом (или массивом объектов), 
	 * которые реализуют метод <code>getClientObject()</code> 
	 * (например инстанция <code>StreamObject</code>),
	 * то для ответа будет добавлен не сам объект, а результат выполнения метода
	 * @see StreamObject::getClientObject()
	 * @todo Переделать на интерфейс видимо, правда универсальность метода getLightObj потеряется
	 */
	protected function add2Answer( $name, $value, $use4Client = true )
	{
		if ($use4Client) $value = $this->get4ClientObj( $value );
		$this->_answer[$name] = $value;
	}
    
    /**
     * Возвращает объект для отправки на клиент (если при вызове <code>add2Answer()</code> 
     * передан параметр <code>use4Client</code> равный <code>true</code>)
     * @param mixed $obj 
     * @return mixed
     * @see Service::add2Answer()
     */
    protected function get4ClientObj( $obj ) {
        return $this->getLightObj( $obj, 'getClientObject' );
    } 

    /**
     * Метод вернет подготовленный объект. Поведение определяется типом персого аргумента:
     * <ul>
     * <li>Если передан объект и он имеет нужный метод переобразования - 
     * то будет возвращен результат выполнения этого метода. 
     * Если метода преобразования нет - 
     * то ко всем свойства объекта будет применен данный метод</li>
     * <li>Если передан массив - то к его элементам будет поочередно применен данный метод</li>
     * <li>В ином случае - значени вернется без изменений</li>
     * @param mixed $obj 
     * @param string $method Название метода преобразования
     * @return mixed
     */
    protected function getLightObj( $obj, $method ) {
        if (is_object( $obj )) 
        {
            if (method_exists( $obj, $method ))
                return call_user_func( array( $obj, $method ) ); 
            else 
            {
                $props = get_object_vars( $obj );
                foreach ($props as $prop => $value) {
                    $obj->$prop = $this->getLightObj( $value, $method );
                }
                return $obj;
            }
        } 
        else if (is_array( $obj )) {
            $arr = array();
            /*$i = 0; 
            foreach ($obj as $index => $value) {
                if ()
            }*/
            for ($i = 0; $i < count( $obj ); $i++) {
                if (!isset( $obj[$i] )) return $obj;
                array_push( $arr, $this->getLightObj( $obj[$i], $method ) );
            }
            return $arr;
        } else return $obj;
    } 
	
	/**
	 * Устанавливает данные, которые будет отправлены на клиент в случае ошибки
	 * @param array $data ассоциативный массив
	 * @param boolean $reset Уже установленные данные будут сброшены
	 */
	protected function setErrorData($data, $reset = false) 
	{
		if (empty($this->_errData) || $reset) $this->_errData = $data;
		else $this->_errData = array_merge($this->_errData, $data);
	}
	
	/**
	 * Добавляет значение в данные при ошибке
	 * @param string $name название поля
	 * @param mixed $value добавляемое значение
	 */
	protected function add2ErrorData( $name, $value )
	{
		$this->_errData[$name] = $value;
	}

	/**
	 * Формирует ответ
     * @return array ассоциативный массив ответа на запрос
	 */
	protected function answer()
	{
		if (!$this->_success)
		{
			$this->_answer = array();
            if ($this->isDebugMode()) $this->add2Answer( 'error', $this->_error );
			$this->add2Answer( 'errno', $this->_errno );

			if ($this->_errData != null && is_array( $this->_errData )) {
				// Удаляем служеблые поля
				// Раньше было просто сначала назначение данных, потом служебных полей, 
				// но при таком подходе данные всегда оказываются выше в объекте
				// и их часто неудобно читать (код ошибки сдвигается слишком низко)
				unset($this->_errData['errno'], $this->_errData['error']);
				foreach ($this->_errData as $key => $value) 
				{
					$this->add2Answer($key, $value);
				}
			} 
		}

		//$this->add2Answer( 'success', $this->_success );

		return (object)$this->_answer;
	}

    protected function answerWith( $returnData = null ) {
        if ($returnData != null)
        {
            if (is_object($returnData)) $returnData = get_object_vars($returnData);
            if (is_array($returnData))
                foreach ($returnData as $key => $value) {
                    $this->add2Answer( $key, $value );
                }
        }

        return $this->answer();
    }

    protected function isDebugMode()
    {
        return GMFramework::isDebugMode();
    }
}
?>