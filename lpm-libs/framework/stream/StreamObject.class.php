<?php
namespace GMFramework;

/**
 * Объект данных, загружаемый из нетипизированного или просто ассоциативного массива
 * @author GreyMag
 * @see  StreamObject#initDefaultValues()
 * @see  StreamObject#initTypes()
 * @see  StreamObject#initClientFields()
 */
abstract class StreamObject implements IStreamObject
{
	/**
	 * Поля для записи/обновления в БД
	 * @var array
	 */
	//private $_dbFields               = array();	
	/**
	 * Поля, для которых используется перевод.
	 * Используется в приложениях с поддержкой мультиязычности Lang 
	 * @var array
	 */
	//private $_translationVars        = array();
	/**
	 * Ассоциативный массив алиасов полей.
	 * Ключ - название свойства в stream объекте,
	 * значение - название поля класса
	 * @var array
	 */
    private $_aliases                = array();
    /**
     * Кэшированный список публичных свойств объекта
     * @var array|null
     */
    private $_cachedProps            = null;
	
	/**
	 * Хранилище переводов, загженных из БД
	 * @var LangStore
	 */
	//private $_translation;
	/**
	 * Имя поля с переводами. 
	 * В этом поле переводы сохраняются в следующем формате:
	 * // GMF2DO тут все переиграл - нужно переделать, теперь имя_поля вместо keyword
	 * keyword1|lang1::translation11&&lang2::translation12;;keyword2|lang1::translation21
	 * @var string
	 */
	//private $_trFieldName = 'translation';
	
	/**
	 * Преобразователь типов
	 * @var TypeConverter
	 */
	protected $_typeConverter;
    /**
     * Использовать поддержку нескольких языков или нет
     * @var boolean
     */
    //protected $_useMultiLang = false;

    /**
     * Поля для отправки на клиент
     * @var StreamFieldsSet
     */ 
    protected $_clientFields;

    /**
     * Список изменившихся полей
     * @var StreamFieldsSet
     */
    protected $_changedFields;
	
    /**
     * 
     */
	function __construct()// $useMultilang = false )
	{
        //$this->_useMultiLang  = $useMultilang;

        $this->initDefaultValues();
        $this->init();
	}

    function __clone()
    {
        /*if ($this->_useMultiLang === true) 
        {
            $lang = $this->_translation->getDefaultLang();
        }*/

        $this->init();

        /*if ($this->_useMultiLang === true) 
        {
            $this->_translation->setDefaultLang($lang);
        }*/
    }

    private function init()
    {
        $this->_typeConverter = new TypeConverter( $this );
        
        /*if ($this->_useMultiLang === true) {
            $this->_translation = new LangStore();
            $this->_translation->setDefaultLang( Lang::getDefaultLang() );
        }*/

        $this->_clientFields  = new StreamFieldsSet($this);
        $this->_changedFields = new StreamFieldsSet($this);

        $this->initTypes();
        $this->initClientFields();

        $this->_clientFields->setAllowEmpty(!$this->_clientFields->isEmpty());
    }

    /**
     * Устанавливает значения по умолчанию для полей.
     * Рекомендуется к переопределению в наследниках
     */
    protected function initDefaultValues()
    {

    }

    /**
     * Устанавливает типы для полей объекта.
     * Рекомендуется к переопределению в наследниках
     * @see  StreamObject::_int()
     * @see  StreamObject::_long()
     * @see  StreamObject::_float()
     * @see  StreamObject::_bool()
     */
    protected function initTypes()
    {

    }

    /**
     * Устанавливает поля для объекта, пересылаемого на клиент.
     * Рекомендуется к переопределению в наследниках
     */
    protected function initClientFields()
    {

    }

    /**
     * Возвращает тип поля
     * @param  string $field Название поля
     * @return string|null
     * @see TypeConverter::getType()
     */
    public function getType($field)
    {
        return $this->_typeConverter->getType($field);
    }

    /**
     * Изменяет значение указанного поля
     * @param  string $field название поля
     * @param  mixed $value новое значение
     */
    public function change($field, $value)
    {
        if (!property_exists($this, $field))
            throw new Exception('Попытка изменить несуществующее свойство ' . $field);

        if ($this->$field instanceof Date) 
        {
            // Если пытаемся изменить поле даты
            if ($this->$field->change($value)) 
                $this->changed($field);
        }
        elseif ($this->$field !== $value)
        {
            $this->$field = $value;
            $this->changed($field);
        }
    }

    /**
     * Изменяет значение указанного поля используя приведение типов
     * @param  string $field название поля
     * @param  mixed $value новое значение
     */
    public function changeWithType($field, $value)
    {
        $value = $this->_typeConverter->setType($field, $value);
        $this->change($field, $value);
    }

    /**
     * Определяет помечено ли указанное поле как измененное
     * @param  string  $field Название поля
     * @return boolean true, если поле помечено как измененное
     */
    public function isChanged($field)
    {
        return $this->_changedFields->contain($field);
    }

    /**
     * Прибавляет указанную величину к текущему значению поля
     * @param  string $field название поля
     * @param  float $value значение, которое будет прибавлено к текущему
     * @return flaot новое значение поля 
     */
    public function inc($field, $value)
    {
        $this->change($field, $this->$field + $value);
        return $this->$field;
    }

    /**
     * Помечает указанные поля как измененные
     * @param  string $field, ... Неограниченное количество имен полей
     */
    public function changed($field, $_ = '')
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_changedFields, 'add'), $args);
    }

    /**
     * Удаляет указанные поля из списка измененных
     * @param  string $field, ... Неограниченное количество имен полей
     */
    public function unchanged($field, $_ = '')
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_changedFields, 'remove'), $args);
    }

    public function resetChanged()
    {
        $this->_changedFields->clear();
    }

    public function hasChanged()
    {
        return !$this->_changedFields->isEmpty();
    }

    public function getChangedFields()
    {
        return $this->_changedFields->getFields();
    }

    /**
     * Возвращает простой объект для отправки на клиент
     * @param  array|string $addfields массив (или сколько угодно параметров) 
     * имен дополнительных полей, которые будут добавлены к заданному набору перед отправкой
     * @return object
     * @see StreamObject::clientObjectCreated()
     * @see StreamObject::_client()
     * @see StreamObject::_clientDefault()
     */
    public function getClientObject( $addfields = null )
    {
        $addfields = is_array($addfields) ? $addfields : func_get_args();
        $this->clientObjectAddFields($addfields);
        
        $obj = $this->_clientFields->createObject($addfields, 'getClientObject');

        return $this->clientObjectCreated($obj);
    }

    /**
     * Обрабатывает список дополнительных полей для клиентского объекта.
     * Можно добавить дополнительные поля или убрать их, непосредственно перед созданием объекта
     * @param  array &$addfields 
     */
    protected function clientObjectAddFields(&$addfields)
    {
    }
    
    /**
     * Получить "легкий" объект. Например для обновления некоторых свойств на клиенте
     * @param array|string $fields Массив имён или имя поля
     * @param string $fields,... Неограниченное количество имён полей 
     * @return object объект
     */
    /*public function getSimpleObject( $fields )
    {
    	if (func_num_args() > 1 || !is_array( $fields )) {
    		$fields = array();
    		foreach (func_get_args() as $fieldName) array_push( $fields, $fieldName );
    	}
    
    	return $this->getLightObj( $fields );
    }*/
    
    /**
     * @see LangStore::getString()
     */
    /*public function getTranslations()
    {
    	return $this->_translation->getStrings();
    }*/
    
    /**
    * Парсит данные из объекта 
    * (например результат запроса к БД). <br>
    * Метод использует переводы для указанных полей, 
    * а для установки значений вызывается метод <code>StreamObject::setVar()</code>.<br>
    * Данные могут быть вложенными, при этом значение может быть даже не распарсенным.
    * Для изменения формата парсинга таких данных нужно просто переопределить метод
    * <code>decodeStreamString()</code> в наследнике.
    * По окончании выполнения будет вызван метод <code>StreamObject::onLoadStream()</code>,
    * если нужно совершить дополнительные действия после основной загрузки - 
    * рекомендуется переопределять именно его
    * @param array|object $data ассоциативный массив или объект
    * @throws Exception
    * @see StreamObject::onLoadStream()
    * @see StreamObject::setVar()
    * @see StreamObject::decodeStreamString()
    */
    public function loadStream($data) {
    	if (!is_array($data)) {
    		if (is_object($data)) $data = get_object_vars($data);
    		else throw new Exception('Неверный формат входных данных для загрузки');
    	}

        $this->preLoadStream($data);

    	// сначала проверим - нет ли поля с переводом
    	/*if (isset( $data[$this->_trFieldName] )) {
    		$strs = explode( ';;', trim( $data[$this->_trFieldName] ) );
    
    		foreach ($strs as $str) {
    			if (trim( $str) == '') continue;
    			$parts = explode( '|', $str );
    
    			$translations = explode( '&&', $parts[1] );
    			foreach ($translations as $translate) {
    				$trStr = explode( '::', $translate );
    				$this->_translation->addString( $trStr[0], $parts[0], $trStr[1] );
    			}
    		}
    		unset( $data[$this->_trFieldName] );
    	}*/
    
    	foreach ($data as $var => $value) {
            if (isset($this->_aliases[$var]))
                $var = $this->_aliases[$var];
            $this->setVar($var, $value);
        }
    	
    	$this->onLoadStream($data);
    }
 
    /**
     * Возвращает перевод на нужны язык для указанного поля,
     * только если это поле в списке переводимых  
     * @param string $field
     * @param string $lang = null
     */
    /*public function getFieldTranslate( $field, $lang = null ) {
        if (!isset( $this->$field )) {
            return false;
        } elseif (!in_array( $field, $this->_translationVars ))
            return $this->$field;
        
        // GMF2DO опять английски по-умолчанию, надо что-то делать
        return $this->_translation->translateExist( $field, $lang ) 
                  ? $this->_translation->getString( $field, $lang )
                  : $this->_translation->getString( $field, LangLocale::EN );
    }*/

    /**
     * Возвращает массив публичных свойств
     * @return array <code>Array of String</code>
     */
    public function getPublicProps()
    {
        if ($this->_cachedProps === null)
        {
            $reflection = new \ReflectionObject($this);
            $props = $reflection->getProperties(
                \ReflectionProperty::IS_PUBLIC);
            $this->_cachedProps = array();
            foreach ($props as $prop) 
            {
                $this->_cachedProps[] = $prop->name;
            }
            /*call_user_func(function ($obj) {
                return array_keys( get_object_vars( $obj ) );
            }, $this);*/
        }

        return $this->_cachedProps;
    }
    
    /**
     * Метод, который вызывается в начале выполнения <code>loadStream()</code>.
     * Рекомендуется переопределять для предварительной обработки входных данных
     * или подготовке самого объекта
     * @param array $data ассоциативный массив данных
     */
    protected function preLoadStream( $data ) 
    {
        // реализация в наследнике, при необходимости
    }
    
    /**
     * Метод, который вызывается в конце выполнения <code>loadStream()</code>.
     * Рекомендуется переопределять для дополнительной обработки входных данных
     * @param array $data ассоциативный массив данных
     */
    protected function onLoadStream( $data ) 
    {
    	// реализация в наследнике, при необходимости
    }
	
	/**
	 * Метод вызывается после создания объекта для клиента 
	 * и даёт возможность дополнительно обработать объект для клиента 
	 * после его создания
	 * @param object $obj
	 * @return object
	 */
	protected function clientObjectCreated( $obj ) 
    {		
		return $obj;
	}
	
	/**
	 * Устанавливает значение поля при использовании метода <code>StreamObject::loadStream()</code>
	 * @param string $var
	 * @param string $value
	 * @return boolean
	 * @see StreamObject::loadStream()
     * @see StreamObject::decodeStreamString()
	 */
	protected function setVar( $var, $value )
	{
		if (!property_exists($this, $var)) return false;
	
		if ($this->$var instanceof Date)
        { 
            // Обрабатываем дату отдельно, потому что $value здесь может быть строкой, а не объектом
            $this->$var->loadStream($value);
        }
        else if (!isset($value)) return false; // GM2DO не понял, почему я не могу назначать null?
		/*else if (in_array( $var, $this->_translationVars )) 
        { 
            // GMF2DO проверить и сделать как надо
			// по-умолчанию английский
            $this->_translation->addString( LangLocale::EN, $var, $value ); 
            $key = $var;
            
            if (!$this->_translation->translateExist( $key )) {
                $this->$var = $value;
                //$this->_translation->addString( LangLocale::EN, $var, $value ); 
            }
            else $this->$var = $this->_translation->getString( $key );
			//$this->$var = $this->_translation->getString( $value );
		}*/
        else if ($this->$var instanceof IStreamObject) 
        {
            // Если значение - строка, значит ее нужно распарсить
            if (is_string($value)) 
            {
                // Тут нужно подумать - что делать когда $value === 'null'
                if ($value !== '' && $value !== 'null')
                {
                    $value = $this->decodeStreamString($value);
                    if ($value === null) 
                        throw new Exception('Не удалось распарсить значение поля ' . $var);

                    $this->$var->loadStream( $value );
                }
            }
            // Иначе пытаемся загружать как есть
            else
            {
                $this->$var->loadStream($value);
            }
        }
        // Если поле -  массив
        else if (is_array($this->$var)) 
        {
            // Но при этом значение - строка, значит ее нужно распарсить
            if (is_string($value)) 
            {
                if (empty($value))
                {
                    $value = array();
                }
                // Это может быть либо JSON, либо строка через запятую
                else
                {
                    if ($value{0} !== '[' || mb_substr($value, -1) !== ']')
                    {
                        $value = explode(',', $value);
                    }
                    else 
                    {
                        $decoded = @json_decode($value);
                        if (null !== $decoded) $value = $decoded;
                    }

                    if (is_array($value))
                    {
                        foreach ($value as $i => $tmpVal) 
                        {
                            if (is_numeric($tmpVal)) $value[$i] = (float)$tmpVal;
                        }
                    }
                }
            } 

            $this->$var = (array)$value;
        }
		else 
        {
            $this->$var = $this->_typeConverter->setType($var, $value);
        }
			
		return true;
	}

    /**
     * Расшифровывает строку-значение stream поля.<br>
     * По умолчанию пытается распарсить JSON
     * @param string $string 
     * @return mixed
     */
    protected function decodeStreamString($string)
    {
        return @json_decode($string);
    }
	
	/**
	 * Добавляет поля, которые надо обрабатывать переводить
	 * @param String $_ любое количество имён полей
	 */
	/*protected function addTranslateFields( $_ = '' )
	{
		$arr = func_get_args();
		if (count( $arr ) > 0) 
		  $this->_translationVars = array_merge( $this->_translationVars, $arr );
	}*/
    
	/**
	 * Добавляет алиас для поля
	 * @param string $hashAlias название поля в загружаемом объекте
	 * @param string $classProp название поля класса
	 */
    protected function addAlias($hashAlias, $classProp)
    {
        $this->_aliases[$hashAlias] = $classProp;
    }
    
    /**
     * Разрезать строку по разделителю.
     * Если передана пустая строка - вернёт пустой массив
     * @param string $str
     * @param string $delimiter
     * @return array
     */
    protected function split( $str, $delimiter = ',' ) 
    {
        if (trim( $str ) == '') return array();
        return explode( $delimiter, $str );
    }
    
    /**
     * Разрезать строку по разделителю,
     * считая элементы числами
     * @param string $str
     * @param string $delimiter
     * @return array
     */
    protected function splitNumber( $str, $delimiter = ',' ) 
    {
        $arr = $this->split( $str, $delimiter );
        foreach ($arr as $i => $val) {
            $arr[$i] = (float)$val;
        }
        return $arr;
    }

    // ==================================================================
    //
    // Алиасы для функций, связанных с полями
    //
    // ------------------------------------------------------------------
    
    /**
     * Алиас для <code>$this->_clientFields->add()</code>
     * @param type $_ 
     * @return type
     */
    protected function _client($_)
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_clientFields, 'add'), $args);
    }

    /**
     * Алиас для <code>$this->_clientFields->addNotDefault()</code>
     * @param type $_ 
     * @return type
     */
    protected function _clientDefault($_)
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_clientFields, 'addNotDefault'), $args);
    }

    /**
     * Алиас для <code>$this->_typeConverter->addIntVars()</code>
     * @param type $_ 
     * @return type
     */
    protected function _int($_) 
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_typeConverter, 'addIntVars'), $args);
    }

    /**
     * Алиас для <code>$this->_typeConverter->addLongVars()</code>
     * @param type $_ 
     * @return type
     */
    protected function _long($_) 
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_typeConverter, 'addLongVars'), $args);
    }

    /**
     * Алиас для <code>$this->_typeConverter->addFloatVars()</code>
     * @param type $_ 
     * @return type
     */
    protected function _float($_) 
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_typeConverter, 'addFloatVars'), $args);
    }

    /**
     * Алиас для <code>$this->_typeConverter->addBoolVars()</code>
     * @param type $_ 
     * @return type
     */
    protected function _bool($_) 
    {
        $args = func_get_args();
        if (count($args) > 0)
            call_user_func_array(array($this->_typeConverter, 'addBoolVars'), $args);
    }
}
?>