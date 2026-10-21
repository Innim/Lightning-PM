<?php
/**
 * Объект, загружаемый из БД или просто ассоциативного массива<br/>
 * При реализации в рамках проекта рекомендуется перопределить метод <code>getGlobals()</code>
 * @author GreyMag
 * @see GMFBase::getGlobals()
 */
abstract class StreamObject extends GMFBase
{
	/**
	 * Загружает список объектов из БД.
	 * @throws DBException Порождает исключение при ошибке выполнения запроса к БД
	 * @throws Exception Если $class не является наследником StreamObject
     * @param DBConnect $db Инстанция соединения с базой данных.
	 * @param string|array $sqlQuery строка sql запроса или массив,
	 * в котором первым элементом идет запрос в формате для DBConnect::queryt(),
	 * а следующими - имена таблиц для передачи в DBConnect::queryt()
	 * @param string $table название таблицы без префикса
	 * @param string $class название класса, инстанции которого должны быть в списке
	 * @param boolean $forClient получать объекты для клиента
	 * @param array|string $func 
	 * @return array <code>Array of StreamObject</code>
	 */
	protected static function loadObjList( $db, $sqlQuery, $class, $forClient = false, $func = null )
	{
        //if ($class === null) $class = $class = get_called_class();
        if (!is_subclass_of( $class, __CLASS__ )) 
          throw new Exception( 'Need subclass of ' . __CLASS__ ) ;
		
		if (!is_array( $sqlQuery )) {
			$qFunc = 'query';
			$sqlQuery = array( $sqlQuery );
		} else $qFunc = 'queryt';
		
		if (!$query = call_user_func_array( array( $db, $qFunc ), $sqlQuery )) {
			throw new DBException( $db, 'Database request error' );
		}
	
		return self::parseListResult( $query, $class, $forClient, $func );
	}
	
	/**
	 * Загрузить список объектов из БД.
	 * @throws DBException Порождает исключение при ошибке выполнения запроса к БД
	 * @throws Exception Если $class не является наследником StreamObject
     * @param DBConnect $db Инстанция соединения с базой данных. 
	 * @param string|array $where условие для sql запроса
	 * @param string | array $table название таблицы без префикса
	 * @param string $class название класса, инстанции которого должны быть в списке
	 * @param boolean $forClient получать объекты для клиента
	 * @param array|string $func
	 * @return array <code>Array of StreamObject</code>
	 */
	protected static function loadListDefault( $db, $where, $table, $class, $forClient = false,
											   $order = null, $limitCount = 0, $limitFrom = -1, 
											   $fields4Select = null, $func = null )
	{
		$sql = "SELECT ";
		if (is_array( $fields4Select )) {
			$sql .= implode( ', ', $fields4Select );
		} else {
			$sql .= '*';
		}
		
		if (is_array( $where )) $where = implode( ' AND ', $where );
		
		if (!is_array( $table )) $sqlQuery = array( $table );
		else $sqlQuery = $table;
		 
		$sql .= " FROM ";
		foreach ($sqlQuery as $i => $tmpTable) {
			if ($i > 0) $sql .= ', ';
			$sql .= "`%" . ( $i + 1 ) . "\$s`";
		}
		if ($where != null && $where != '') $sql .= ' WHERE ' . $where;
		if ($order != null && $order != '') $sql .= ' ORDER BY ' . $order;
		if ($limitCount > 0) {
			$sql .= ' LIMIT ';
			if ($limitFrom > -1) $sql .= $limitFrom . ',';
			$sql .= $limitCount; 
		}
					
		array_unshift( $sqlQuery, $sql );
		
		return self::loadObjList( $db, $sqlQuery, $class, $forClient, $func );
	}
	
	/**
	 * Парсит результат выполнения запроса к БД
	 * @param mysqli_result $queryResult
	 * @param string $class
	 * @param boolean $forClient
	 * @param string|array $func <code>function($class obj)</code>
	 * @return array <code>Array of StreamObject</code>
	 * @throws Exception Если $class не является наследником StreamObject
     * @see DefaultGlobals 
	 */
	protected static function parseListResult( $queryResult, $class, 
	                                           $forClient = false, $func = null )
	{
		$list = array();
		if (!is_subclass_of( $class, __CLASS__ )) 
		  throw new Exception( 'Need subclass of ' . __CLASS__ ) ;
		
		while( $mysqlRow = $queryResult->fetch_assoc() ) {
			/* @var $obj StreamObject */
			$obj = new $class();
	
			$obj->loadStream( $mysqlRow );
			$list[] = ($forClient) ? $obj->getClientObject() : $obj;
			if ($func != null) call_user_func_array( $func, array( $obj ) );
		}	
	
		return $list;
	}
	
	/**
	 * Загружает один объект по идентификатору. 
	 * Требуемый класс должен иметь статический метод <code>loadList($where);</code>
	 * @param int $id Значение идентификатора объекта
	 * @param String $class Имя класса, в классе должен быть определён статический метод
	 * <code>loadList( $where )</code>, который загружает по условию
	 * @param String $where = null Дополнительное условие
	 * @param String $idField = 'id' Название поля с идентифкатором в базе
	 * @return StreamObject|false
	 */
	protected static function singleLoad( $id, $class, $where = null, $idField = 'id' )
	{
		if ($where != null && $where != '') $where = ' AND (' . $where . ')';
		 
		$where = "`" . $idField . "` = '" . $id . "'" . $where;
		 
		if (!$list = call_user_func_array( array( $class, 'loadList' ),  array( $where ) )) 
		  return false;
	
		return $list[0];
	}
	
	/**
	 * Поля, которые используются при отправке облегчённого объекта на клиент
	 * @var array <code>Array of string</code>
	 */
	private $_clientFields           = array();
	/**
	 * Поля, которые используются при отправке облегчённого объекта на клиент
	 * только если они не равны своему дефолтному значению 
	 * @var array <code>Array of string</code>
	 */
	private $_clientNotDefaultFields = array();
	/**
	 * Поля для записи/обновления в БД
	 * @var array
	 */
	private $_dbFields               = array();
	/**
	 * Поля, которые надо преобразовывать из mysql datetime в unixtime
	 * при загрузке из базы данных 
	 * @var array
	 */
	private $_dateTimeVars           = array();	
	/**
	 * Поля, для которых используется перевод.
	 * Используется в приложениях с поддержкой мультиязычности Lang 
	 * @var array
	 */
	private $_translationVars        = array();
	/**
	 * Ассоциативный массив алиасов полей.
	 * Ключ - название свойства в stream объекте,
	 * значени - название поля класса
	 * @var array
	 */
    private $_aliases                = array();
	
	/**
	 * Хранилище переводов, загженных из БД
	 * @var LangStore
	 */
	private $_translation;
	/**
	 * Имя поля с переводами. 
	 * В этом поле переводы сохраняются в следующем формате:
	 * // GMF2DO тут все переиграл - нужно переделать, теперь имя_поля вместо keyword
	 * keyword1|lang1::translation11&&lang2::translation12;;keyword2|lang1::translation21
	 * @var string
	 */
	private $_trFieldName = 'translation';
	
	/**
	 * Преобразователь типов
	 * @var TypeConverter
	 */
	protected $_typeConverter;
	
	/**
	 * Разрешить возвращать пустые спец. объекты (например для клиента),
	 * а не пытаться использовать все публичные поля 
	 * @var boolean
	 */
	protected $_allowEmptySpecObject = false;
	
    /**
     * 
     * @param boolean $createDBConnect Определяет, будет ли создан класс для обащений к БД
     * @param boolean $useMultilang Определяет, будет ли класс поддерживать мультиязычность
     * @see StreamObject::createDBConnect()
     * @see StreamObject::$_db
     * @see StreamObject::$_translation
     */
	function __construct( $createDBConnect = true, $useMultilang = false )
	{
        parent::__construct( $createDBConnect, $useMultilang );

		$this->_typeConverter = new TypeConverter( $this );
		
		if ($useMultilang === true) {
			$this->_translation = new LangStore();
			$this->_translation->setDefaultLang( Lang::getDefaultLang() );
		}
	}

    /**
     * Возвращает "лёгкий" объект для отправки клиенту. 
     * Поля задаются при помощи метода <code>self::addClientFields()</code>
     * @param array|string $addfields = null Массив имён или имя дополнительного полей
     * @param string $addfields,... Неограниченное количество дополнительных имён полей
     * @return object 
     */
    public function getClientObject( $addfields = null )
    {
        // Наследник передаёт сюда свой $addfields как есть, и при вызове без
        // аргументов это явный null - func_get_args() вернула бы [null],
        // то есть поле с именем null.
        $additional = ( $addfields === null )
                        ? array()
                        : ( is_array( $addfields ) ? $addfields : func_get_args() );
        
        // добавляем поля, значения которых не совпадают со значениями по умолчанию
        foreach ($this->_clientNotDefaultFields as $field => $value) {
        	if ($value != $this->$field) $additional[] = $field;
        }
        
        $obj = $this->getSpecLigthObj( $this->_clientFields, $additional, 'getClientObject' );

        return $this->clientObjectCreated( $obj );
    }
    
    /**
     * Получить "легкий" объект. Например для обновления некоторых свойств на клиенте
     * @param array|string $fields Массив имён или имя поля
     * @param string $fields,... Неограниченное количество имён полей 
     * @return object объект
     */
    public function getSimpleObject( $fields )
    {
    	if (func_num_args() > 1 || !is_array( $fields )) {
    		$fields = array();
    		foreach (func_get_args() as $fieldName) array_push( $fields, $fieldName );
    	}
    
    	return $this->getLightObj( $fields );
    }
    
    /**
     * @see LangStore::getString()
     */
    public function getTranslations()
    {
    	return $this->_translation->getStrings();
    }
    
    /**
    * Парсит данные из объекта 
    * (например результат запроса к БД)
    * Метод использует переводы для указанных полей, 
    * а для установки значений вызывается метод <code>StreamObject::setVar()</code>.
    * По окончании выполнения будет вызван метод <code>StreamObject::onLoadStream()</code>,
    * если нужно совершить дополнительные действия после основной загрузки - 
    * рекомендуется переопределять именно его
    * @param array $data ассоциативный массив или объект
    * @return boolean
    * @see StreamObject::onLoadStream()
    * @see StreamObject::setVar()
    */
    public function loadStream( $data )
    {
    	if (!is_array( $data )){
    		if (is_object( $data )) $data = get_object_vars( $data );
    		else return false;
    	}
    	// сначала проверим - нет ли поля с переводом
    	if (isset( $data[$this->_trFieldName] )) {
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
    	}
    
    	foreach ($data as $var => $value) $this->setVar( $var, $value );
    	
    	$this->onLoadStream( $data );
    	
    	return true;
    }
 
    /**
     * Возвращает перевод на нужны язык для указанного поля,
     * только если это поле в списке переводимых  
     * @param string $field
     * @param string $lang = null
     */
    public function getFieldTranslate( $field, $lang = null ) {
        if (!isset( $this->$field )) {
            return false;
        } elseif (!in_array( $field, $this->_translationVars ))
            return $this->$field;
        
        // GMF2DO опять английски по-умолчанию, надо что-то делать
        return $this->_translation->translateExist( $field, $lang ) 
                  ? $this->_translation->getString( $field, $lang )
                  : $this->_translation->getString( $field, LangLocale::EN );
    }
    
    /**
     * Метод, который вызывается в конце выполнения <code>loadStream()</code>.
     * Рекомендуется переопределять для дополнительной обработки входных данных
     * @param $data
     */
    protected function onLoadStream( $data ) {
    	// реализация в наследнике, при необходимости
    }
    
    protected function getSpecLigthObj( $fields, $additional, $callMethod ) {        
        if ($additional && is_array( $additional ))
            foreach ($additional as $fieldName) {
                if (in_array( $fieldName, $fields )) continue;
                array_push( $fields, $fieldName );
            }
        
        // нужно только публичные свойства
        if (count( $fields ) == 0 && !$this->_allowEmptySpecObject){
            $ref = new ReflectionObject( $this );
            $pros = $ref->getProperties( ReflectionProperty::IS_PUBLIC );

            foreach ($pros as $pro) {
                //false && $pro = new ReflectionProperty();
                //$result[$pro->getName()] = $pro->getValue($obj);
                if ($pro) array_push( $fields, $pro->getName() );
            }
  
            //$fields = array_keys( get_object_vars( $this ) );
        }                

        return $this->getLightObj( $fields, $callMethod );
    }
	
	/**
	 * Возвращает хэш, используя поля, переданные первым аргументов. 
	 * Если значение поля объект и имеет метод, переданный вторым параметром -
	 * то в свойство облегчённого объекта записывается результат выполнения метода
	 * @param array $fields Массив имён требуемых полей
	 * @param string $callMethod = null Имя метода
     * @return object объект
	 */
	protected function getLightObj( $fields, $callMethod = null ) 
	{
		$object = array();

        foreach ($fields as $field) {
            if (!property_exists( $this, $field )) continue;
            $value = $this->$field;
            if ($callMethod != null) {
            	if (is_object( $value ) && method_exists( $value, $callMethod )) 
            	   $value = $value->$callMethod();
            	elseif (is_array( $value )) {
            		$arr = array();
            		foreach ($value as $key => $obj) {
            			if (is_object( $obj ) && method_exists( $obj, $callMethod )) {
            				$arr[$key] = $obj->$callMethod();
            			} else {
            				$arr[$key] = $obj;
            			}
            		}
            		
            		$value = $arr;
            	}
            }
            
            $object[$field] = $value;
        }

        return (object)$object;
	}
	
	/**
	 * Метод вызывается после создания объекта для клиента 
	 * и даёт возможность дополнительно обработать объект для клиента 
	 * после его создания
	 * @param object $obj
	 * @return object
	 */
	protected function clientObjectCreated( $obj ) {		
		return $obj;
	}
	
	/**
	 * Возвращает ассоциативный массив,
	 * в котором ключами являются названия полей,
	 * а значения уже подготовлены для записи.
	 * Указать какие именно поля нужны можно с помощью <code>self::addDBFields()</code>
	 * @param array|string $fields Массив имён или имя поля
     * @param string $fields,... Неограниченное количество имён полей 
	 * @return array <code>Array of string=>string</code>
	 * @see StreamObject::addDBField()
	 */
	protected function getFields4DB( $fields = null )
	{
		$props = array();
		if ($fields === null || !is_array( $fields )) $fields = func_get_args();
		$fields = array_merge( $fields, $this->_dbFields );
		
		foreach ($fields as $field) {
			$props[$field] = $this->getVar( $field );
		}
		 
		return $props;
	}
	
	/**
	 * Возвращает значение поля, подготовленное для записи в БД
	 * @param string $field
	 * @return string
	 */
	protected function getVar( $field )
	{
		$value = $this->$field;
		if (in_array( $field, $this->_dateTimeVars )) {
			$value = $value == 0 ? '0000-00-00 00:00:00' : DateTimeUtils::mysqlDate( $value );
		}
		else if (is_bool( $value )) $value = (int)$value;
		else if (!is_int( $value ) && !is_float( $value )) $value = (string)$value;
		
		if (is_string( $value )) $value = $this->_db->escape_string( $value );
		return $value;
	}
	
	/**
	 * Устанавливает значение поля при использовании метода <code>StreamObject::loadStream()</code>
	 * @param string $var
	 * @param string $value
	 * @return boolean
	 * @see StreamObject::loadStream()
	 */
	protected function setVar( $var, $value )
	{
		if (!property_exists( $this, $var )) return false;
	
		if (in_array( $var, $this->_dateTimeVars)) 
		    $this->$var = ( $value === '0000-00-00 00:00:00' || $value === '0000-00-00' || !$value ) 
		                      ? 0 : (float)DateTimeUtils::convertMysqlDate( $value );
		else if (!isset( $value )) return false;
		else if (in_array( $var, $this->_translationVars )) { 
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
		}
		else 
		      $this->$var = $this->_typeConverter->setType( $var, $value );
			
		return true;
	}
	
	/**
	 * Добавляет поля для отсылки на клиент
	 * @param string $_='',... Неограниченное количество количество имён полей
	 */
	protected function addClientFields( $_ = '' )
	{
		$arr = func_get_args();
		if (count( $arr ) > 0) 
			$this->_clientFields = array_merge( $this->_clientFields, $arr );
	}
	
	/**
	 * Добавляет поля, которые должны отсылаться на клиент только если они не совпадают 
	 * со значением по умолчанию (значение по умолчанию берется в момент вызова метода)
	 * @param string $_ = '',... Неограниченное количество имён полей
	 */
	protected function addClientNotDefaultFields( $_ = '' )
	{
		$arr = func_get_args();
		foreach ($arr as $field) {
			if (isset( $this->$field )) {
				$this->_clientNotDefaultFields[$field] = $this->$field;
			}
		}
	}
	
	/**
	 * Очищает массив полей для отсылки на клиент
	 */
	protected function clearClientField()
	{
		$this->_clientFields           = array();
		$this->_clientNotDefaultFields = array();
	}
	
	/**
	 * Убирает из массива полей для отсылки на клиент ненужные
	 * @param String $_ любое количество имён ненужных полей
	 */
	protected function removeClientField( $_ = '' )
	{
		$arr = func_get_args();
		while ($field = array_pop( $arr )) {
			ArrayUtils::remove( $this->_clientFields, $field );
			unset( $this->_clientNotDefaultFields[$field] );
		}
	}

    /**
     * Возвращает сохранённые поля для отслыки на клиент, 
     * которые отсылаются только если текущее значение не совпадает со значением по умолчанию
     * @return array ассоциативный массив array( название поля => значение по умолчанию )
     */
    protected function getClientNotDefaultFields() {
        return $this->_clientNotDefaultFields;
    }
	
	/**
	 * Добавляет поля для записи в базу
	 * @param String $_ любое количество имён полей
	 */
	protected function addDBFields( $_ = '' )
	{
		$arr = func_get_args();
		if (count( $arr ) > 0) $this->_dbFields = array_merge( $this->_dbFields, $arr );
	}
	
	/**
	 * Добавляет поля, которые надо обрабатывать как datetime
	 * @param String $_ любое количество имён полей
	 */
	protected function addDateTimeFields( $_ = '' )
	{
		$arr = func_get_args();
		if (count( $arr ) > 0) $this->_dateTimeVars = array_merge( $this->_dateTimeVars, $arr );
	}
    
    /**
     * Удаляет поле, которые было записано для обрабатки как datetime
     * @param String $field название поля
     */
    protected function removeDateTimeField( $field )
    {
    	ArrayUtils::remove( $this->_dateTimeVars, $field );
    }
	
	/**
	 * Добавляет поля, которые надо обрабатывать переводить
	 * @param String $_ любое количество имён полей
	 */
	protected function addTranslateFields( $_ = '' )
	{
		$arr = func_get_args();
		if (count( $arr ) > 0) 
		  $this->_translationVars = array_merge( $this->_translationVars, $arr );
	}
    
	/**
	 * Добавляет алиас для поля
	 * @param string $hashAlias название поля в загружаемом объекте
	 * @param string $classProp название поля класса
	 */
    protected function addAlias( $hashAlias, $classProp ) {
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
    
    /**
     * Записывает переменную в лог
     * @param mixed $var переменная, для записи в лог
     * @param string $comment комментарий
     * @return void
     */
    protected function writeLog( $var, $comment = 'undefined' )
    {
    	GMLog::writeLog( $var, $comment, $this );
    }
}
?>