<?php
namespace GMFramework;

/**
 * Набор полей, по которому будет возвращен нетипизированный объект
 * @author greymag <greymag@gmail.com>
 * @copyright 2013
 */
class StreamFieldsSet 
{
    private static $_defaultsByClass = array();

    private $_fields            = array();
    private $_notDefaultFields  = array();
    private $_object;
    private $_allowEmpty        = false;
    private $_defaultVals;
    
    function __construct(StreamObject $object) 
    {
        $this->_object = $object;

        $className = get_class($object);
        if (isset(self::$_defaultsByClass[$className])) 
            $this->_defaultVals = self::$_defaultsByClass[$className];
        else 
        {
            $this->_defaultVals = get_class_vars($className);
            self::$_defaultsByClass[$className] = $this->_defaultVals;
        }
    }

    /**
     * Добавляет поля 
     * @param string $_='',... Неограниченное количество количество имён полей
     */
    public function add($_ = '')
    {
        $arr = func_get_args();
        if (count( $arr ) > 0) 
            $this->_fields = array_merge( $this->_fields, $arr );
    }
    
    /**
     * Добавляет поля, которые должны использоваться только если они не совпадают 
     * со значением по умолчанию (значение по умолчанию берется из описания класса)
     * @param string $_ = '',... Неограниченное количество имён полей
     */
    public function addNotDefault( $_ = '' )
    {
        $arr = func_get_args();
        foreach ($arr as $field) {
            // Значение может быть null, поэтому array_key_exists() а не isset
            if (array_key_exists($field, $this->_defaultVals)) {
                $this->_notDefaultFields[$field] = $this->_defaultVals[$field];//$this->_object->$field;
            }
        }
    }

    /**
     * Определяет, если ли переданное поле в наборе
     * @param  string $field
     * @return boolean true, если есть наборе
     */
    public function contain($field)
    {
        return isset($this->_notDefaultFields[$field]) || in_array($field, $this->_fields);
    }

    /**
     * Определяет, пуст ли набор полей
     * @return 
     */ 
    public function isEmpty()
    {
        return count($this->_fields) == 0 && count($this->_notDefaultFields) == 0;
    }

    /**
     * Возвращает сохраненный набор полей 
     * (не включает поля со значениями по умолчанию)
     */ 
    public function getFields()
    {
        return ArrayUtils::copy($this->_fields);
    }

    /**
     * Разрешено ли возвращать пустой объект,
     * или в случае пустых сетов использовать все поля
     * @var boolean
     */
    public function setAllowEmpty($value)
    {
        $this->_allowEmpty = $value;
    }

    public function getAllowEmpty()
    {
        return $this->_allowEmpty;
    }
    
    /**
     * Убирает из массива полей ненужные
     * @param String $_ любое количество имён ненужных полей
     */
    public function remove( $_ = '' )
    {
        $arr = func_get_args();
        while ($field = array_pop( $arr )) {
            ArrayUtils::remove( $this->_fields, $field );
            unset( $this->_notDefaultFields[$field] );
        }
    }
    
    /**
     * Очищает массив полей 
     */
    public function clear()
    {
        $this->_fields           = array();
        $this->_notDefaultFields = array();
    }

    /**
     * Создает нетипизированный объект, руководствуясь переданным набором полей
     * @param array|string $addfields = null Массив имён или имя дополнительного полей
     * @param string $callMethod Название метода, который должен быть вызван у объекта, 
     * в случае если он является значением поля из набора
     * @return object 
     */  
    public function createObject($addfields = null, $callMethod = null)
    {
        $additional = is_array( $addfields ) ? $addfields : array();
        
        // добавляем поля, значения которых не совпадают со значениями по умолчанию
        foreach ($this->_notDefaultFields as $field => $value) 
        {
            $curValue = $this->_object->$field;
            // Для даты проверка усложняется
            if ($curValue instanceof Date) 
            {
                // Если дата добавлена в массив не дефолтных полей, 
                // то она не должна пересылаться если не определена
                if (!$curValue->isUndefined()) $additional[] = $field;
            }
            // Для остальных - достаточно сравнения
            else if ($value !== $curValue) $additional[] = $field;
        }
        
        $obj = $this->getSpecLigthObj( $this->_fields, $additional, $callMethod );

        return $obj;
    }
    
    protected function getSpecLigthObj($fields, $additional, $callMethod)
    {
        if ($additional && is_array($additional))
            foreach ($additional as $fieldName) 
            {
                if (!in_array($fieldName, $fields)) $fields[] = $fieldName;
            }
        
        // нужно только публичные свойства
        if (empty($fields) && !$this->_allowEmpty)
        {
            /*$ref = new ReflectionObject( $this->_object );
            $pros = $ref->getProperties( ReflectionProperty::IS_PUBLIC );
            // тут нужно выкидывать статические свойства
            foreach ($pros as $pro) {
                //false && $pro = new ReflectionProperty();
                //$result[$pro->getName()] = $pro->getValue($obj);
                if ($pro) array_push( $fields, $pro->getName() );
            }*/
  
            // в связи с изменением области видимости теперь можно обойтись этим
            $fields = array_keys(get_object_vars($this->_object));
        }                

        return $this->getLightObj($fields, $callMethod);
    }
    
    /**
     * Возвращает хэш, используя поля, переданные первым аргументов. 
     * Если значение поля объект и имеет метод, переданный вторым параметром -
     * то в свойство облегчённого объекта записывается результат выполнения метода
     * @param array $fields Массив имён требуемых полей
     * @param string $callMethod = null Имя метода
     * @return object объект
     */
    protected function getLightObj($fields, $callMethod = null) 
    {
        $object = array();

        foreach ($fields as $field) 
        {
            if (property_exists($this->_object, $field)) 
            {
                $value = $this->_object->$field;
                if (null !== $callMethod)
                {
                    if (is_object($value) && method_exists($value, $callMethod)) 
                        $value = $value->$callMethod();
                    elseif (is_array($value))
                    {
                        $arr = array();
                        foreach ($value as $key => $obj) 
                        {
                            if (is_object($obj) && method_exists($obj, $callMethod)) 
                                $arr[$key] = $obj->$callMethod();
                            else
                                $arr[$key] = $obj;
                        }
                        
                        $value = $arr;
                    }
                }

                $object[$field] = $value;
            }
        }

        return (object)$object;
    }
    
}
?>