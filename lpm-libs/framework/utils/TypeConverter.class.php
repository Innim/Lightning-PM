<?php
namespace GMFramework;

/**
 * Утилита, позволяющая типизировать свойства объекта
 * @author GreyMag
 *
 */
class TypeConverter
{	
	/**
	 * Булев тип <br/>
	 * Это простейший тип. <b>boolean</b> выражает истинность значения. 
	 * Он может быть либо TRUE либо FALSE. 
	 * @link http://php.net/manual/ru/language.types.boolean.php
	 * @var string
	 */
	const TYPE_BOOLEAN = 'bool';
	/**
     * Целое число <br/>
     * <b>Integer</b> это число из множества Z = {..., -2, -1, 0, 1, 2, ...}. 
     * @link http://www.php.net/manual/ru/language.types.integer.php
     * @var string
     */
    const TYPE_INTEGER = 'int';
    /**
     * Число с плавающей точкой <br/>
     * Числа с плавающей точкой 
     * (также известные как <b>"float"</b>, <b>"double"</b>, или <b>"real"</b>) 
     * могут быть определены следующими синтаксисами:
     * <code>
     * $a = 1.234;
     * $b = 1.2e3;
     * $c = 7E-10;
     * </code>
     * @link http://www.php.net/manual/ru/language.types.float.php
     * @var string
     */
    const TYPE_FLOAT   = 'float';
    /**
     * Строка <br/>
     * Строка - это набор символов, поэтому символ - это то же самое, что и байт
     * @link http://www.php.net/manual/ru/language.types.string.php
     * @var string
     */
    const TYPE_STRING  = 'string';
    /**
     * Массив <br/>
     * На самом деле массив в PHP - это упорядоченное отображение, 
     * которое устанавливает соответствие между значением и ключом. 
     * Этот тип оптимизирован в нескольких направлениях, 
     * поэтому вы можете использовать его как собственно массив, 
     * список (вектор), хэш-таблицу (являющуюся реализацией карты), 
     * словарь, коллекцию, стэк, очередь и, возможно, что-то еще. 
     * Так как значением массива может быть другой массив PHP, 
     * можно также создавать деревья и многомерные массивы.  
     * @link http://www.php.net/manual/ru/language.types.array.php
     * @var string
     */
    const TYPE_ARRAY   = 'array';
    /**
     * Объект 
     * @link http://www.php.net/manual/ru/language.types.object.php
     * @var string
     */
    const TYPE_OBJECT  = 'object';
    /**
     * NULL <br/>
     * Специальное значение <b>NULL</b> представляет собой переменную без значения. 
     * NULL - это единственно возможное значение типа NULL. 
     * @link http://www.php.net/manual/ru/language.types.null.php
     * @var string
     */
    const TYPE_NULL    = 'null';

     /**
      * Приводит значения к автоматически определенному типу. 
      * Если нужно кастить поля в boolean, 
      * то имена таких полей должны быть переданы вторым аргументом.
      * @param array $hash (ассоциативный) массив, значения которого будут автоматически приведены к типам
      * @param array $boolFields массив имен полей, которые надо откастить в boolean
      * @return array
      * @see TypeConverter::autoCastValue()
      */
    public static function autoCast( &$hash, $boolFields = null ) {
        if ($boolFields !== null && !is_array( $boolFields )) $boolFields = null;

        foreach ($hash as $var=>$value) {
            if (is_object( $value )) continue;
            if ($boolFields !== null && in_array( $var, $boolFields)) 
                $hash[$var] = (boolean)$value;
            else $hash[$var] = TypeConverter::autoCastValue( $value );
        }
        return $hash;
    }

    /**
     * Приводит значение к автоматически определенному типу.
     * На данный момент автоматически определяются только числа (int, float).
     * @param mixed $value
     * @return mixed Откастенное значение
     */ 
    public static function autoCastValue( $value ) {
        if (is_object( $value )) return $value;
        if (is_int( $value ) || (string)((int)$value) === (string)$value) return (int)$value;
        if (is_float( $value ) || (string)((float)$value) === (string)$value)return (float)$value;
        return $value;
    }
    
    /**
     * Переменные по типам
     * @var array <code>Array of String</code>
     */
    private $_varTypes = array();
    /**
     * Массив названий перечисляемых типов, заданных пользователем
     * @var array ассоциативный массив 
     * <code>string имя_типа => array of mixed массив_значений</code>
     */
    private $_enumTypes = array();
    
    /**
     * Объект, для которого типизируются переменные
     * @var object
     */
    private $_object;
    
    /**
     * 
     * @param $object объект, свойства которого будут типизироваться
     */
    function __construct( $object )
    {
    	$this->_object = $object;
    }
    
    /**
     * Сохраняет заданный тип для для полей, имеющих переданные имена. 
     * Количество переменных не ограничено
     * @param string $type Тип
     * @param string $var Имя поля
     * @param string $var,... Неограниченное количество дополнительных имён полей
     */
    public function addType4Vars( $type, $var )
    {    
    	$args = func_get_args();
    	array_shift( $args );
    	foreach( $args as $var ) 
    	{
    		$this->_varTypes[$var] = $type;
    	}
    } 
    
    /**
     * Устанавливает тип int для полей, имеющих переданные имена
     * @param string $var Имя поля
     * @param string $var,... Неограниченное количество дополнительных имён полей
     */
    public function addIntVars( $var )
    {
    	$args = func_get_args();
    	$this->callAdd4Type( self::TYPE_INTEGER, $args );
    } 
    
    /**
     * Устанавливает тип длинного целого для полей, имеющих переданные имена
     * @param string $var Имя поля
     * @param string $var,... Неограниченное количество дополнительных имён полей
     */
    public function addLongVars( $var )
    {
        $args = func_get_args();
        $this->callAdd4Type( self::TYPE_FLOAT, $args );
    } 
    
    /**
     * Устанавливает тип float для полей, имеющих переданные имена
     * @param string $var Имя поля
     * @param string $var,... Неограниченное количество дополнительных имён полей
     */
    public function addFloatVars( $var )
    {
        $args = func_get_args();
        $this->callAdd4Type( self::TYPE_FLOAT, $args );
    } 
    
    /**
     * Устанавливает тип boolean для полей, имеющих переданные имена
     * @param string $var Имя поля
     * @param string $var,... Неограниченное количество дополнительных имён полей
     */
    public function addBoolVars( $var )
    {
        $args = func_get_args();
    	$this->callAdd4Type( self::TYPE_BOOLEAN, $args );
    }
    
    /**
     * Устанавливает тип поля, если он сохранён
     * @param string $var Имя поля
     * @param mixed $value Значение
     */
    public function setType( $var, $value )
    {		
    	if (isset( $this->_varTypes[$var] )) 
        {
    		$type = $this->_varTypes[$var];
    		if (isset( $this->_enumTypes[$type] )) 
            {
    			$index = array_search( $value, $this->_enumTypes[$type] );
    			if ($index === false) return null;
    			else return $this->_enumTypes[$type][$index];
    		} 
            else
            {
				// GMF2DO проверку на то что это тип
				settype( $value, $type );
            }
    	} 
    	   
    	return $value;
    }
    
    /**
     * Добавляет пользовательский перечисляемый тип.
     * Если тип с таким именем уже определён - то старый будет перезаписан.
     * Если название пользовательского типа совпадает с названием типов данных в PHP
     * (см. <code>TypeConverter::TYPE_*</code>), то будет использован он,
     * т.к. приотритет имеют пользовательские типы 
     * @param string $typeName название типа
     * @param array $values <code>Array of mixed</code> массив значений
     * @return string|false Название типа
     */
    public function createEnumType( $typeName, $values ) {
    	if (is_array( $values )) {
    		$this->_enumTypes[$typeName] = $values;
    		return $typeName;
    	} else return null;
    }

    /**
     * Возвращает тип, сохраненный для переменной, или null,
     * если тип переменной не был указан
     * @param  string $var имя переменной
     * @return string|null Тип <code>TypeConverter::TYPE_*</code> или null,
     * если тип не сохранен
     */
    public function getType($var)
    {
        if (isset($this->_varTypes[$var]))
        {
            return $this->_varTypes[$var];
        }
        else 
        {
            return null;
        }
    }
    
    protected function callAdd4Type( $type, $vars )
    {
    	array_unshift( $vars, $type );
        //call_user_method_array( 'addType4Vars', $this, $vars );
        call_user_func_array( array( $this, 'addType4Vars' ),  $vars );        
    }
}