<?php
namespace GMFramework;

/**
 * Провайдер данных, работающий с MySQL базами данных
 * @author greymag
 * @see GMFBase::getGlobals()
 * 
 * @property-read string $error Ошибка
 * @property-read int $errno Номер ошибки
 */
abstract class MySQLDataProvider
{
    /**
     * Cоединение с БД
     * @var DBConnect
     */
    protected $_db;
    /**
     * Номер ошибки
     * @var int
     */
    protected $_errno = 0;
    /**
     * Текст ошибки
     * @var string
     */
    protected $_error = '';

    function __construct(DBConnect $db)
    {
        $this->_db = $db;
    }
    
    /**
     * @return the $error
     * @return the $errno
     */
    public function __get( $var )
    {
        switch( $var )
        {
            case 'error' : return $this->_error; break;
            case 'errno' : return $this->_errno; break;
            default : return;
        }
    }

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
    protected function loadObjList( $sqlQuery, $class, $forClient = false, $func = null )
    {
        //if ($class === null) $class = $class = get_called_class();
        if (!is_subclass_of( $class,  __NAMESPACE__ . '\IStreamObject' )) 
          throw new Exception( $class . ' in not implement IStreamObject' ) ;

        $db = $this->_db;
        
        if (!is_array( $sqlQuery )) {
            $qFunc = 'query';
            $sqlQuery = array( $sqlQuery );
        } else $qFunc = 'queryt';
        
        if (!$query = call_user_func_array( array( $db, $qFunc ), $sqlQuery )) {
            throw new DBException( $db, 'Database request error' );
        }
    
        return $this->parseListResult( $query, $class, $forClient, $func );
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
    protected function loadListDefault( $where, $table, $class, $forClient = false,
                                               $order = null, $limitCount = 0, $limitFrom = -1, 
                                               $fields4Select = null, $func = null )
    {
        $db = $this->_db;
        $sql = "SELECT ";
        if (is_array( $fields4Select )) {
            $sql .= implode( ', ', $fields4Select );
        } else {
            $sql .= '*';
        }
        
        if (is_array( $where )) $where = '(' . implode( ') AND (', $where ) . ')';
        
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
        
        return $this->loadObjList( $sqlQuery, $class, $forClient, $func );
    }

    protected function loadObjectsByDBBuild($sqlHash, $class = null, $tables = null, $func = null, $errMessage = null)
    {
      try 
      {
        $query = $this->_db->buildQuery($sqlHash, $tables);
        return $this->loadObjList($query, $class, false, $func);
      }
      catch (DBException $e)
      {
        if ($errMessage === null)
        {
          $errMessage = 'Ошибка при загрузке списка';
        }

        $this->exception(false, $errMessage);
      }
    }

    protected function saveChangesAtTableDefault(
        $ids, $idField, $data, $table, $exceptionMess = 'Ошибка при сохранении данных'
    )
    {
        if (!$data) throw new Exception( 'Данные не определены' );
    
        $db = $this->_db;

        $where = array();
        if (is_array($idField))
        {
          foreach ($idField as $i => $field)
          {
            $where[] = $this->getCondition($field, is_array($ids) ? $ids[$i] : $ids);
          }
        }
        else
        {
          $where[] = $this->getCondition($idField, $ids);
        }
    
        if (!$db->queryb(array(
            'UPDATE' => $table,
            'SET'    => $data,
            'WHERE'  => $where
        ))) $this->exception(true, $exceptionMess);
    }

    /**
     * Обновляет сразу список данных, используя подготовленные запросы
     * @param  string $idField Название поля, содержащего идентификатор
     * @param  array $array <code>Array of StreamObject</code> Список данных для сохранения
     * @param  string $table Название таблицы без префикса
     * @param  string $exceptionMess Сообщение об ошибке, если не удалось сохранить
     * @return int Количество сохраненных записей
     */
    protected function saveListStreamChangesAtTableDefault($idField, $array, $table, 
      $exceptionMess = 'Ошибка при обновлении измененных данных')
    {
        if (!$array) throw new Exception( 'Данные не определены' );

        // Выбираем набор полей, который будет использоваться 
        // в качестве набора обновляемых 
        $fields = array();
        $raws   = array();
        $list   = array();

        foreach ($array as $data) 
        {
            if ($data->hasChanged())
            {
                $raw    = $this->getChangedData4Save($data, false, true);
                $keys   = array_keys($raw);

                $fields = array_unique(array_merge($fields, $keys));
                $raws[] = $raw;
                $list[] = $data;
            }
        }

        $count  = 0;

        if (count($fields) > 0)
        {
            $db  = $this->_db;

            $sql = 'UPDATE `%s` SET `' . implode('` = ?, `', $fields) . '` = ? WHERE ';// .
                    //' WHERE `' . $idField . '` = ?';

            if (is_array($idField))
            {
              foreach ($idField as $i => $field) 
              {
                if ($i > 0) $sql .= ' AND ';
                $sql .= '`' . $field . '` = ?';
                $fields[] = $field;
              }
            }
            else 
            {
              $sql .= '`' . $idField . '` = ?';
              $fields[] = $idField;
            }


            $prepare = $db->preparet($sql, $table);

            if (!$prepare) $this->exception(true, $exceptionMess);
        
            $paramTypes = '';
            foreach ($list as $i => $data) 
            {
                $raw = $raws[$i];

                // Формируем массив аргументов, 
                // добивая данные полями, которых не хватает
                $args  = array();
                foreach ($fields as $field) 
                {
                    if (!isset($raw[$field]))
                    {
                        $raw[$field] = $this->getDataField4Save($data, $field, false, true);
                    }

                    $args[] = &$raw[$field];

                    if ($i == 0)
                    {
                        // На первой итерации делаем строку типов
                        $type = $data->getType($field);

                        if ($type == TypeConverter::TYPE_BOOLEAN
                                    || $type == TypeConverter::TYPE_INTEGER)
                        {
                            $paramTypes .= 'i';
                        }
                        else if ($type == TypeConverter::TYPE_FLOAT) 
                        {
                            $paramTypes .= 'd';
                        }
                        else
                        {
                            $paramTypes .= 's';
                        }
                    }
                }

                // Добавляем строку типов
                array_unshift($args, $paramTypes);

                // Передаем параметры
                call_user_func_array(array($prepare, 'bind_param'), $args);
                // Выполняем запрос обновления
                $prepare->execute();

                // Помечаем как сохраненный
                $data->resetChanged();

                $count++;
            }

            $prepare->close();
        }

        return $count;
    }

    protected function replaceListStreamAtTableDefault($array, $table, 
      $exceptionMess = 'Ошибка при перезаписи данных', $onInsertHandler = null, $notNullFields = null)
    {
        $this->saveListStreamAtTableDefault($array, $table, $exceptionMess, 'replace', $onInsertHandler, $notNullFields);
    }

    protected function saveListStreamAtTableDefault($array, $table, 
      $exceptionMess = 'Ошибка при сохранении данных', $type = 'insert', $onInsertHandler = null, $notNullFields = null)
    {
        if (!$array) throw new Exception('Данные не определены');

        // Выбираем набор полей
        $fields = array_keys(get_object_vars($array[0]));

        if (count($fields) > 0)
        {
            $db  = $this->_db;

            $sql = mb_strtolower($type) == 'replace' ? 'REPLACE' : 'INSERT';
            $sql .= ' `%s` SET `' . implode('` = ?, `', $fields) . '` = ?';

            $prepare = $db->preparet($sql, $table);

            if (!$prepare) $this->exception(true, $exceptionMess);
        
            $paramTypes = '';
            foreach ($array as $i => $data) 
            {
                // Формируем массив аргументов
                $args  = array();
                $raw   = array();
                foreach ($fields as $field) 
                {
                    $notNull     = null !== $notNullFields && in_array($field, $notNullFields);
                    $raw[$field] = $this->getDataField4Save($data, $field, $notNull, true);
                    $args[]      = &$raw[$field];

                    if ($i == 0)
                    {
                        // На первой итерации делаем строку типов
                        $type = $data->getType($field);

                        if ($type == TypeConverter::TYPE_BOOLEAN
                                    || $type == TypeConverter::TYPE_INTEGER)
                        {
                            $paramTypes .= 'i';
                        }
                        else if ($type == TypeConverter::TYPE_FLOAT) 
                        {
                            $paramTypes .= 'd';
                        }
                        else
                        {
                            $paramTypes .= 's';
                        }
                    }
                }

                // Добавляем строку типов
                array_unshift($args, $paramTypes);

                // Передаем параметры
                call_user_func_array(array($prepare, 'bind_param'), $args);
                if ($db instanceof DBConnectWithLog) $db->logBindParam($args);
                // Выполняем запрос обновления
                if (!$prepare->execute()) $this->exception(true, $exceptionMess);

                if (null !== $onInsertHandler) 
                  call_user_func_array($onInsertHandler, array($data, $prepare));
            }

            $prepare->close();
        }
    }

    protected function saveStreamChangesAtTableDefault(
        $ids, $idField, StreamObject $data, $table, $exceptionMess = 'Ошибка при сохранении данных'
    )
    {
      if ($data->hasChanged())
      {
        $raw = $this->getChangedData4Save($data);
        $this->saveChangesAtTableDefault($ids, $idField, $raw, $table, $exceptionMess);
        $data->resetChanged();
        return 1;
      }
      else
      {
        return 0;
      }
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
    protected function parseListResult( $queryResult, $class, 
                                               $forClient = false, $func = null )
    {
        $list = array();
        if (!is_subclass_of( $class, __NAMESPACE__ . '\IStreamObject' )) 
          throw new Exception( $class . ' are not implementation of IStreamObject' ) ;
        
        while( $mysqlRow = $queryResult->fetch_assoc() ) {
            /* @var $obj StreamObject */
            $obj = new $class();
    
            $obj->loadStream( $mysqlRow );
            $list[] = ($forClient) ? $obj->getClientObject() : $obj;
            if ($func != null) call_user_func_array( $func, array( $obj ) );
        }   
    
        return $list;
    }

    protected function parseListData( $dataList, $class, 
                                      $func = null, $filterFunc = null, 
                                      $forClient = false )
    {
        if ($dataList == null || !is_array($dataList))
          throw new Exception( 'Данные не загружены' ) ;
            
        $list = array();
        if (!is_subclass_of( $class,  __NAMESPACE__ . '\IStreamObject' )) 
          throw new Exception( 'Need impleentation of IStreamObject' ) ;
        
        foreach ($dataList as $raw) {
            /* @var $obj StreamObject */
            $obj = new $class();
    
            $obj->loadStream( $raw );

            if ($filterFunc === null || call_user_func_array( $filterFunc, array( $obj ) )) {
                $list[] = ($forClient) ? $obj->getClientObject() : $obj;
                if ($func != null) call_user_func_array( $func, array( $obj ) );
            }
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
    protected function singleLoad($id, $class, $where = null, $idField = 'id')
    {
        if ($where != null && $where != '') $where = ' AND (' . $where . ')';

        if (is_array($idField))
        {
          foreach ($idField as $i => $field) 
          {
            if ($i > 0)  $where = ' AND ' . $where;

            $where = "`" . $field . "` = '" . (is_array($id) ? $id[$i] : $id) . "'" . $where;
          }
        }
        else 
        {
          $where = "`" . $idField . "` = '" . $id . "'" . $where;
        }
         
        if (!$list = call_user_func_array(array($this, 'loadList'),  array($where))) 
          return false;
    
        return $list[0];
    }

    protected function getChangedData4Save(StreamObject $data, $notNull = false, $forPrepare = false)
    {
        $fields = $data->getChangedFields();
        $raw = array();

        foreach ($fields as $field) 
        {
            $raw[$field] = $this->getDataField4Save($data, $field, $notNull, $forPrepare);
        }

        return $raw;
    }

    protected function getDataField4Save(StreamObject $data, $field, $notNull = false, $forPrepare = false)
    {
        $value = $data->$field;
        if ($value instanceof Date) 
        {
            $value = $value->isUndefined()
                        ? ($notNull ? ('0000-00-00' . ($value->isIgnoreTime() ? '' : ' 00:00:00')) : 
                          ($forPrepare ? null : 'NULL')) 
                        : $this->date($value->getUnixtime(), !$value->isIgnoreTime());
        }
        else if (/*$value instanceof IStreamObject || */is_object($value))
        {
            if (!($value instanceof \JsonSerializable)) $value = get_object_vars($value);
            $value = json_encode($value);
            //$value = addcslashes($value, '\\'); это по ходу какая-то лажа, она наоборот ломает все
        }
        else if (is_array($value))
        {
            $arr = $this->getArrValue4Save($value);
            if ($arr === null) $value = '';
            else 
            {
              $value = json_encode($arr);
              //$value = addcslashes($value, '\\');
            }
        }
        else if ($value === null)
        {
            $value = $notNull ? '' : ($forPrepare ? null : 'NULL');
        }

        return $value;
    }

    private function getArrValue4Save($value)
    {
        $arr = array();
        foreach ($value as $item) 
        {
           if (/*$item instanceof IStreamObject || */is_object($item)) $arr[] = get_object_vars($item);
           else
           {
              $type = gettype($item);
              switch ($type) 
              {
                case 'boolean':
                case 'integer':
                case 'double':
                case 'string':
                case 'NULL': $arr[] = $item; break;
                case 'array': 
                {
                  $item = $this->getArrValue4Save($item);
                  if ($item === null) return null;
                  $arr[] = $item;
                } break;
                case 'object':
                case 'resource':
                case 'NULL':
                case 'unknown type':
                default: return null; break;
              }
           }
        }

        return $arr;
    }

    protected function getDataFromStream(StreamObject $data)
    {
        $raw = get_object_vars($data);

        foreach ($raw as $field => $value) 
        {
            $raw[$field] = $this->getDataField4Save($data, $field);
        }

        return $raw;
    }

    protected function date($unixtime = null, $datetime = true)
    {
        return DateTimeUtils::mysqlDate($unixtime, $datetime);
    }

    /**
     * Приводит поля объектов к автоматически определенному типу. 
     * На данный момент автоматически определяются только числа (int, float), 
     * поля boolean должны буть переданы вторым аргументом, остальные поля остаются прежнего типа.
     * @param array $hash (ассоциативный) массив, поля которого будут автоматически приведены к типам
     * @param array $boolFields массив имен полей, которые надо откастить в boolean
     * @return array
     */
    protected function autoCast( &$hash, $boolFields = null ) 
    {
        foreach ($hash as $var=>$value) {
            if (is_object( $value )) continue;
            if ((string)((int)$value) === $value) $hash[$var] = (int)$value;
            if ((string)((float)$value) === $value) $hash[$var] = (float)$value;
            if ($boolFields != null && in_array( $var, $boolFields)) 
                $hash[$var] = (boolean)$value;
        }
        return $hash;
    }
    
    /**
     * Формирует сроку условия на совпадение с элементом или группой элементов
     * (чисел)
     * @param string $field Название поля
     * @param scalar|array $values <code>Array of scalar</code> Значение или массив значений 
     * @return string
     */
    protected function getCondition($field, $values)
    {
        if (empty($values)) return '1';

        if ($field{0} != '`') $field = '`' . $field . '`';
        $str = $field . ' ';
        if (is_array($values) && count($values) == 1) $values = $values[0];

        if (is_array($values))
        {
          $isString = is_string($values[0]);

          $valStr = implode($isString ? "','" : ',', $values);
          if ($isString) $valStr = "'" . $valStr . "'";
          $str .= 'IN (' . $valStr . ')';
        }
        else if (is_string($values)) $str .= '= \'' . $values . '\'';
        else if (is_int($values) || is_float($values)) $str .= '= ' . $values;
        else if (is_bool($values)) $str .= '= ' . (int)$values;
        else if (is_null($values)) $str .= 'IS NULL';
        // объекты игнорим

        /*if (is_numeric( $values )) $str .= '= ' . $values;
        else 
        {
            if (is_array( $values )) 
            {
              $valStr = implode( ',', $values );
              $str .= 'IN (' . $valStr . ')';
            }
            else if (is_numeric( $values )) $str .= '= ' . $values;
            else $str .= '= \'' . $values . '\'';
        }*/

        return $str;
    }

    /**
     * Удаляет строки из всех таблиц
     * @param  string $field Название поля
     * @param  scalar|array $values <code>Array of scalar</code> Значение или массив значений 
     * @param  string| array $tables <code>Array of float</code> Таблица или массив таблиц 
     * @return boolean
     */
    protected function deleteFromTables($field, $values, $tables)
    {
        $sql = array();
        $sqlHash = array(
            'DELETE' => '',
            'WHERE'  => $this->getCondition($field, $values)
        );

        if (is_array($tables))
        {
            foreach ($tables as $table) 
            {
                $sqlHash['FROM'] = $table;
                $sql[] = $this->_db->buildQuery($sqlHash);
            }

            return $this->_db->multiQuery($sql);
        }
        else 
        {
            $sqlHash['FROM'] = $tables;
            return (boolean)$this->_db->queryb($sqlHash);
        }
    }

    protected function exception($save, $message = '', $code = 0)
    {
        if (empty($message)) $message = $save ? 
            'Ошибка при сохранении данных' : 'Ошибка при загрузке данных';

        $exception = $save ? new ProviderSaveException($message, $code) : 
            new ProviderLoadException($message, $code);

        throw $exception;
    }
}
?>