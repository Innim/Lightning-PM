<?php
namespace GMFramework;

/**
 * Билдер SQL запросов для работы с базой данных.
 */
class DBQueryBuilder {
    /**
     * @var \mysqli
     */
    private $db;

	/**
	 * Префикс таблиц, использующихся в текущем проекте
	 */
	private $prefix;

    function __construct(\mysqli $db, $prefix)
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }
	
	/**
	 * Отформатировать строку (первым элементом строка), 
	 * подставляя имена таблиц с префиксами 
	 * @param array $args первый элемент - форматируемая строка запроса, дальше - имена таблиц
	 */
	public function sprintft($args)
	{
		for ($i = 1; $i < count($args); $i++) {
            $args[$i] = $this->prefix . $args[$i];
        } 

        return call_user_func_array('sprintf', $args);
	}

	/**
     * @see mysqli::escape_string()
	 */
	public function escape_string($value)
	{
		//$value = stripcslashes( $value );
		return $this->db->real_escape_string($value);
	}

    /**
     * Конструктор запросов. Создает запрос по ассоциативному массиву заданного формата
     * @param Array $sqlHash Aссоциативный массив с допустимыми полями:<br/>
     * <ul>
     *  <li><b>SELECT</b> - определяет операцию выборки, значение - поля для выборки, 
     *   может быть массивом, тогда поля оборачиваются в магические кавычки, 
     *   за исключением случаев:
     *   <ul>
     *   <li>Переданное значение является числом</li>
     *   <li>Переданное значение начинается с `</li>
     *   <li>Переданное значение начинается с '|"</li>
     *   <li>Переданное значение - пустая строка (будет обернуто в одинарные кавычки)</li>
     *   </ul></li> 
     *   Также может быть ассоциативным массивом вида имя поля => алиас
     *  <li><b>FROM</b> - таблицы выборки (будут обёрнуты в магические кавычки и,
     *   в зависимости от второго параметра, к этим названиям могут подставляться префиксы), 
     *   может быть массив - если таблиц выборки несколько;</li>
     *  <li><b>AS</b> - псевдонимы таблиц выборки (будут обёрнуты в магические кавычки), 
     *   тип и размерность массива должны совпадать с <b>FROM</b>;</li>
     *  <li><b>JOINS</b> - массив массивов вида 
     *  <code>array('join_operator'=>table_reference,
     *  'ON'=>join_condition'|'USING'=>column_list,['AS'=>table_alias])</code>, 
     *  где <code>table_reference</code> вида <b>FROM</b>, 
     *  <code>table_alias</code> вида <b>AS</b>
     *  <code>join_condition</code> вида <b>WHERE</b>, 
     *  <code>column_list</code> вида <b>SELECT</b>;</li>
     *  <li><b>WHERE</b> - условие, может быть массивом 
     *  (в таком случае объединяется через <code>AND</code>),
     *  если это ассоциативный массив - то ключ считается названием поля (см. <b>INSERT</b>).
     *  Если в качестве значения в ассоциативном массиве передается массив значений, 
     *  то будет использована конструкция IN или же это может быть ассоциативный массив,
     *  в котором ключами служат некоторые или все из следующих операторов: 
     *  &gt;, &gt;=, &lt;, &lt;=, &lt;&gt;, =, в таком случае будут добавлены сравнения для поля
     *  с использованием указанных операторов. Также можно группировать условия, 
     *  передав в качеcтве значения неассоциативного массива массив формата WHERE, 
     *  при этом первым значением может быть оператор AND или OR, 
     *  в таком случае учловие будет связяно с его помощью;</li>
     *  <li><b>GROUP BY</b>;</li> 
     *  <li><b>HAVING</b>;</li> 
     *  <li><b>ORDER BY</b> - порядок, может быть массивом;</li>
     *  <li><b>LIMIT</b>;</li>
     *  <li><b>INSERT|REPLACE</b> - определяет операцию вставки, значение - строка полей, 
     *  в которые будет происходить вставка (может быть пустым), массив полей  
     *  или ассоциативный массив <code>{название поля=>зачение|массив значений}</code>,
     *  если значение является строкой и не начинается с <code>`</code> - 
     *  оно будет пропущено через escape функцию и обёрнуто в <code>''</code>;</li>
     *  <li><b>IGNORE</b> - добавляет ключевое слово IGNORE для INSERT запроса 
     *  <li><b>DELAYED</b> - добавляет опцию DELAYED для INSERT или REPLACE запроса
     * (значение поля при этом игнорируется), работает только для MyISAM таблиц;</li>
     *  <li><b>INTO</b> - имя таблицы (будут добавлены <code>`</code> 
     *  и по необходимости префикс);</li>
     *  <li><b>VALUES</b> - строка значений, массив строк значений или массив массивов значений, 
     *  в котором элементы расположены в нужном порядке 
     *  (свойство игнорируется, если значения заданы в <b>INSERT</b>);</li>
     *  <li><b>ON DUPLICATE KEY UPDATE</b> (или <b>ODKU</b>) - строка значений или массив полей, 
     *  если передана пустая строка и для вставки был передан массив - 
     *  то обновлены будут все поля для вставки, 
     *  если массив полей - то только переданные поля, 
     *  при этом в качестве поля может выступать ассоциативный массив вида
     *  <code>array(имя_поля=>значение)</code> или строка, 
     *  если нужно использовать значение из <b>INSERT</b>.
     *  если был передан ассоциативный массив - ключи будут считаться названиями полей, 
     *  а значения их новыми значениями;</li>
     *  <li><b>UPDATE</b> - определяет операцию обновления, значение - см. <b>FROM</b>;</li>
     *  <li><b>SET</b> - строка или ассоциативный массив <code>поле=>значение</code>, 
     *  см. <b>INSERT</b>;</li>
     *  <li><b>DELETE</b> - определяет операцию удаления, значение - см. <b>FROM</b>.</li>
     * @param array $tables = null Если передан этот параметр, 
     * то вернется результат выполнения <code>DBConnect::sprintft()</code> 
     * от построенного запроса. 
     * Т.е. вместо имен таблиц в передаваемых параметрах 
     * следует использовать <code>%s</code> или <code>%1$s</code>, <code>%2$s</code> и т.д. 
     * А также следует это учитывать при передаче параметров 
     * (использовать <code>DBConnect::escape_string_t()</code> вместо 
     * <code>DBConnect::escape_string()</code>, заменять <code>%</code> на <code>%%</code>). 
     * Значение третьего параметра при этом игнорируется.
     * @param boolean $usePrefix = true Определяет, будут ли подставляться префиксы в имена таблиц
     * @return string Сформированный запрос, готовый к исполнению
     * @throws Exception При ошибке формирования запроса
     * @see DBConnect::escape_string_t()
     */
    public function buildQuery( $sqlHash, $tables = null, $usePrefix = true ) {
    	$sql = '';
    	
    	if ($tables != null && ( !is_array( $tables ) || count( $tables ) == 0)) {
    		$tables = null;
    	}
    	
    	$prefix = $usePrefix && $tables == null ? $this->prefix : '';
    	
    	// если запрос выборки
    	if (isset( $sqlHash['SELECT'] )) {
    		$sql = 'SELECT ';
            $sql .= $this->getFieldsListForSQL( $sqlHash['SELECT'] );
    		$sql .= ' FROM ';
    		
    		if (!isset( $sqlHash['FROM'] )) 
    		  throw new Exception( 'Not defined FROM into the query' );

            $aliases = isset($sqlHash['AS']) ? $sqlHash['AS'] : null;

    		$sql = $this->addTablesFromHash( $sql, $sqlHash['FROM'], $prefix, $aliases );
            
    		// объединения
    		$sql = $this->addJoinsFromHash( $sql, $sqlHash, $prefix );

            // условие
            $sql = $this->addWhereFromHash( $sql, $sqlHash );
            
            // группировка
            if (!empty( $sqlHash['GROUP BY'] ))
                $sql .= ' GROUP BY ' . $sqlHash['GROUP BY'];
                
            if (!empty( $sqlHash['HAVING'] ))
                $sql .= ' HAVING ' . $sqlHash['HAVING'];

            // порядок
            $sql = $this->addOrderFromHash( $sql, $sqlHash );
            
            // ограничения
            if (!empty( $sqlHash['LIMIT'] ))
                $sql .= ' LIMIT '.$sqlHash['LIMIT'];
    	}
    	// если вставка
    	else if (isset( $sqlHash['INSERT'] ) || isset( $sqlHash['REPLACE'] )) {
    		if (isset($sqlHash['REPLACE'])) 
            {
                $replace = true;
    			$inputData = $sqlHash['REPLACE'];
    			$sql  = 'REPLACE';
    		} 
            else 
            {
                $replace = false;
    			$inputData = $sqlHash['INSERT'];
    			$sql  = 'INSERT';
    		}

            // доп опции
            if (isset($sqlHash['DELAYED'])) $sql .= ' DELAYED';
            if (isset($sqlHash['IGNORE']) && !$replace) $sql .= ' IGNORE';
    			
            $sql .= ' INTO ';
            
            if (!isset( $sqlHash['INTO'] )) 
              throw new Exception( 'You must to define INTO parameter' );
              
            $sql .= '`' . $prefix . $sqlHash['INTO'] . '`';

            $values = isset( $sqlHash['VALUES'] ) ? $sqlHash['VALUES'] : '';
            if (!empty( $inputData ))
            {
            	if (is_array( $inputData )) {
            		if (ArrayUtils::isAssoc( $inputData )) {
	            		$fields = array_keys( $inputData );
	            		$sql .= ' (`' . implode( '`, `', $fields ). '`)'; 
	            		$values = array();           		
	            		foreach ($inputData as $value) {
	            			if (is_array( $value )) {            				
	            				foreach ($value as $i=>$tmpVal) {
	            					if (!isset( $values[$i] )) $values[$i] = '';
	            					else $values[$i] .= ', ';
	            					$values[$i] .= $this->getValueForSQL( $tmpVal ); 
	            				}
	            			} else {
	            				if (is_array( $values )) $values = '';
	                            else $values .= ', ';
	                            $values .= $this->getValueForSQL( $value ); 
	            			}
	            		}
            		} else {
            			$sql .= ' (' . $this->getFieldsListForSQL( $inputData ) . ')';
            		}
            	} else $sql .= ' (' . $inputData . ')';
            }

            $sql .= ' VALUES ';
            if (is_array( $values )) {
            	foreach ($values as $i => $value) {
            		if ($i > 0) $sql .= ',';
            		$sql .= '(';
            		if (is_array( $value )) {
            			foreach ($value as $j => $val) {
            				if ($j > 0) $sql .= ',';
            				$sql .= $this->getValueForSQL( $val );
            			}
            		} else 
            			$sql .= $value;
            		$sql .= ')';
            	}
            } else
	            $sql .= '(' . $values . ')';
	            
	        if (isset($sqlHash['ON DUPLICATE KEY UPDATE']) || isset($sqlHash['ODKU'])) 
            {
	        	$uValues = '';	    
                if (!isset($sqlHash['ON DUPLICATE KEY UPDATE'])) 
                    $sqlHash['ON DUPLICATE KEY UPDATE'] = $sqlHash['ODKU'];
                
	        	$dupl = $sqlHash['ON DUPLICATE KEY UPDATE'] === '' 
	        	          ? array() : $sqlHash['ON DUPLICATE KEY UPDATE']; 
	        	
	        	if (is_array( $dupl )) 
	        	{
	        		if (ArrayUtils::isAssoc( $dupl ))
	        			foreach ($dupl as $field => $value) {
	        				if ($uValues != '') $uValues .= ', ';
	        				if ($field{0} != '`') $field = '`' . $field . '`';
	        				$uValues .= $field . ' = ' . $this->getValueForSQL( $value );
	        			}
	        		else 
	        		{
	                    $fields = count( $dupl ) > 0 ? $dupl : array_keys( $inputData );	           
	                    foreach ($fields as $tmpField) {
	                        if ($uValues != '') $uValues .= ', ';
	                        
	                        if (is_array( $tmpField ))
	                            $uValues .= '`' . key( $tmpField ) . '` = ' . 
	                                        $this->getValueForSQL( current( $tmpField ) );
	                        else 
	                            $uValues .= '`' . $tmpField . '` = VALUES(`' . $tmpField . '`)';
	                    } 	
	        		}        	
	            } else {
	        		$uValues = $dupl;
	        	} 
	        	
	        	if ($uValues != '')
	        	  $sql .= ' ON DUPLICATE KEY UPDATE ' . $uValues;
	        }
        }
        // обновление 
        else if (isset($sqlHash['UPDATE']))
        {
            $sql  = 'UPDATE ';
            // таблицы
            $sql  = $this->addTablesFromHash( $sql, $sqlHash['UPDATE'], $prefix );
    		// объединения
    		$sql  = $this->addJoinsFromHash( $sql, $sqlHash, $prefix );
            $sql .= ' SET ';
            
            if (!isset( $sqlHash['SET'] ))
                throw new Exception( 'You must to define SET parameter' );
            
            if (is_array( $sqlHash['SET'] )) {
            	$set = '';
            	foreach ($sqlHash['SET'] as $field => $value) {
            		if ($set != '') $set .= ', ';
            		$set .= '`' . $field . '` = ' . $this->getValueForSQL( $value );
            	}
            	$sql .= $set;
            } else $sql .= $sqlHash['SET'];
            
            // условие
            $sql = $this->addWhereFromHash( $sql, $sqlHash );
			
            // порядок
            $sql = $this->addOrderFromHash( $sql, $sqlHash );
			
            // ограничения
            if (!empty( $sqlHash['LIMIT'] ))
                $sql .= ' LIMIT '.$sqlHash['LIMIT'];
        }
        // удаление
        else if (isset($sqlHash['DELETE']))
        {
            $sql = 'DELETE FROM ';
            
            if (empty( $sqlHash['DELETE'] )) {
            	if (!isset( $sqlHash['FROM'] )) 
            	   throw new Exception( 'You must to define tables for delete query' );
            	$tList= $sqlHash['FROM'];
            } else $tList = $sqlHash['DELETE'];
            
            $sql = $this->addTablesFromHash( $sql, $tList, $prefix );
            
            // условие
            $sql = $this->addWhereFromHash( $sql, $sqlHash );
			
            // порядок
            $sql = $this->addOrderFromHash( $sql, $sqlHash );
            
            // ограничения
            if (!empty( $sqlHash['LIMIT'] ))
                $sql .= ' LIMIT '.$sqlHash['LIMIT'];
        }
        else throw new Exception( 'Unknown query type' );
                
        if ($tables != null) {
        	$params = ArrayUtils::copy( $tables );
        	array_unshift( $params, $sql ); 
        	$sql = $this->sprintft( $params );
        }
    	
    	return $sql;
    }

    /**
     * Возвращает список полей через запятую. 
     * Поля оборачиваются в магические кавычки, за исключением случаев:
     * <ul>
     * <li>Переданное значение является числом</li>
     * <li>Переданное значение начинается с `</li>
     * <li>Переданное значение начинается с '|"</li>
     * <li>Переданное значение - пустая строка (будет обернуто в одинарные кавычки)</li>
     * </ul><br/>
     * Если передана строка - то она вернется без изменений.
     * @param array|string $list
     * @return string
     */
    private function getFieldsListForSQL($list) 
    {
        if (is_array($list)) 
        {
            $str = '';
            foreach ($list as $field => $alias) 
            {
                if ($str != '') $str .= ', ';

                if (is_int($field))
                {
                    $field = $alias;
                    $alias = null;
                }

                if (!is_int($field) && !is_float($field)) 
                {
                    $field = (string)$field;
                    if ($field === '') $field = "'" . $field . "'";
                    else
                    {
                        $s = $field{0};

                        if ($s !== '`' && $s !== '"' && $s !== "'") $field = '`' . $field . '`';
                    }

                    if (null !== $alias) $field .= ' AS `' . $alias . '`';
                }
                $str .= $field;
            }
            return $str;
        } 
        else return $list; 
    }
    
    /**
     * Парсит и добавляет join'ы таблиц
     * @param string $sql
     * @param array $sqlHash
     * @param string $prefix
     * @throws Exception
     * @return string
     */
    private function addJoinsFromHash( $sql, $sqlHash, $prefix ) {
        if (isset( $sqlHash['JOINS'] ))
        {
            foreach ($sqlHash['JOINS'] as $curJoin) {
                if (!is_array( $curJoin ))
                    throw new Exception( 'JOINS elements must be an array' );
                if (!isset( $curJoin['ON']) && !isset( $curJoin['USING'] ))
                    throw new Exception( 'Not defined ON or USING into the join query' );
                 
                $sql .= ' ' . key( $curJoin ) . ' ';

                $table = current( $curJoin );
                $alias = isset($curJoin['AS']) ? $curJoin['AS'] : null;

                $sql  = $this->addTablesFromHash( $sql, $table, $prefix, $alias );
                if (isset( $curJoin['ON'] ))
                    $sql = $this->addWhereFromHash( $sql, $curJoin, 'ON' );
                else
                    $sql .= ' USING (' . $this->getFieldsListForSQL( $curJoin['USING'] ) . ') ';
            }
        }
        
        return $sql;
    }
    
    private function addTablesFromHash( $sql, $tables, $prefix, $aliases = null ) {
        if (is_array( $tables )) 
        {
            if ($aliases !== null)
            {
                if (!is_array($aliases) || count($tables) != count($aliases))
                    throw new Exception( 'Count of the aliases must be equal to count of the tables' );
            }

            foreach ($tables as $i => $table) 
            {
                if ($i > 0) $sql .= ', ';
                $sql .= $this->getTableName(
                    $table, $prefix, $aliases !== null ? $aliases[$i] : null);
            }
        } 
        else if (strlen( $tables ) == 0) throw new Exception( 'Field FROM must be not empty' );
        else 
        {
            $sql .= $this->getTableName($tables, $prefix, $aliases);
        }
            
        return $sql;
    }

    private function getTableName($table, $prefix, $alias = null)
    {
        $res = $prefix . $table;
        if ($table{0} !== '`') $res = '`' . $res . '`';

        if ($alias !== null)
        {
            if (is_array($alias)) $alias = $alias[0];

            $alias = (string)$alias;
            if ($alias !== '')
            {
                if ($alias{0} !== '`') $alias = '`' . $alias . '`';
                $res .= ' AS ' . $alias;
            }
        }

        return $res;
    }
    
    private function getValueForSQL($val) 
    {
        if ($val !== 'NULL') 
        {
            if (is_string($val) && ($val === '' || $val{0} !== '`')) 
                $val = "'" . $this->escape_string($val) . "'";
            elseif (is_bool($val))
                $val = (int)$val;
            elseif (is_null($val))
                $val = 'NULL';
        }
            
        return $val;
    }

    private function where2Str($array, $join = 'AND')
    {
        $whereStr = '';
        foreach ($array as $field => $value) 
        {
            if ($whereStr != '') $whereStr .= ' ' . $join . ' ';

            $whereStr .= '(';
            if (is_int($field))
            {
                if (null === $value) $whereStr .= '1';
                else if (is_array($value))
                {
                    $cur = current($value);
                    $subJoin = 'AND';
                    if (($cur === 'OR' || 'AND' === $cur))
                    {
                        unset($value[key($value)]);
                        $subJoin = $cur;
                    }
                    $whereStr .= '(' . $this->where2Str($value, $subJoin) . ')';
                }
                else $whereStr .= $value;
            }
            else 
            {
                if ($field{0} != '`') $field = '`' . $field . '`';
                $whereStr .= $field;

                if (is_array($value))
                {
                    $operators = array('>', '>=', '<', '<=', '<>', '=');
                    $opFound = 0;
                    foreach ($operators as $operator) 
                    {
                        if (array_key_exists($operator, $value)) 
                        {
                            if ($opFound > 0) $whereStr .= ' ' . $join . ' ' . $field;
                            $subValue = $value[$operator];
                            if (is_null($subValue) && $operator === '=') $whereStr .= ' IS NULL';
                            else 
                            {
                                if (is_array($subValue) && ($operator === '=' || $operator === '<>'))
                                {
                                    if ($operator === '<>') $whereStr .= ' NOT';
                                    $whereStr .= ' IN (';
                                    $vi = 0;
                                    foreach ($subValue as $vv) 
                                    {
                                        if ($vi++ > 0) $whereStr .= ',';
                                        $whereStr .= $this->getValueForSQL($vv);
                                    }
                                    $whereStr .= ')';
                                }
                                else 
                                {
                                    $whereStr .= ' ' . $operator . ' ' . $this->getValueForSQL($subValue);    
                                }
                            }

                            $opFound++;
                        }
                    }

                    if ($opFound === 0)
                    {
                        $whereStr .= ' IN (';
                        $vi = 0;
                        foreach ($value as $vv) 
                        {
                            if ($vi++ > 0) $whereStr .= ',';
                            $whereStr .= $this->getValueForSQL($vv);
                        }
                        $whereStr .= ')';
                    }
                }
                else if (is_null($value))
                {
                    $whereStr .= ' IS NULL';
                }
                else 
                {
                    $whereStr .= ' = ' . $this->getValueForSQL( $value );
                }
            }
            $whereStr .= ')';
        }

        return $whereStr;
    }
    
    private function addWhereFromHash( $sql, $sqlHash, $condWord = 'WHERE' ) {
        if (!isset( $sqlHash[$condWord] )) return $sql;
        $where = $sqlHash[$condWord];

        if (!empty( $where )) 
        {
            $condWord = ' ' . trim($condWord) . ' ';
            if (is_array( $where )) $whereStr = $this->where2Str($where);
            else $whereStr = $where;
            
            if (!empty($whereStr)) $sql .= $condWord . $whereStr;
        }
        return $sql;
    } 
    
    private function addOrderFromHash( $sql, $sqlHash ) {
        if (!empty( $sqlHash['ORDER BY'] )){
            if (is_array( $sqlHash['ORDER BY'] )) {
                if (count( $sqlHash['ORDER BY'] ) > 0)
                  $sql .= ' ORDER BY ' . implode( ', ', $sqlHash['ORDER BY'] );
            } else $sql .= ' ORDER BY ' . $sqlHash['ORDER BY'];
        }
        return $sql;
    } 
    
}


