<?php
/**
 * DBConnect
 * Класс работы с базой данных
 * @package ru.vbinc.gm.framework.db
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2009
 * @version 0.2
 * @access public
 * 
 * @property-read string $error Текст ошибки
 * @property-read int $errno Номер ошибки
 * @property-read int $affected_rows Количество строк, затронутых последним INSERT, UPDATE, REPLACE или DELETE запросом
 * @property-read string $client_info Returns a string that represents the MySQL client library version
 * @property-read int $client_version Returns client version number as an integer
 * @property-read string $connect_errno Returns the last error code number from the last call to mysqli_connect().
 * @property-read string $connect_error Returns the last error message string from the last call to mysqli_connect(). 
 * @property-read int $field_count Returns the number of columns for the most recent query on the connection represented by the link parameter. This function can be useful when using the mysqli_store_result() function to determine if the query should have produced a non-empty result set or not without knowing the nature of the query. 
 * @property-read int $client_version Returns client version number as an integer. 
 * @property-read string $host_info Returns a string describing the connection represented by the link parameter (including the server host name). 
 * @property-read string $protocol_version Returns an integer representing the MySQL protocol version used by the connection represented by the link parameter
 * @property-read string $server_info Returns a string representing the version of the MySQL server that the MySQLi extension is connected to
 * @property-read int $server_version The mysqli_get_server_version() function returns the version of the server connected to (represented by the link parameter) as an integer
 * @property-read string $info The mysqli_info() function returns a string providing information about the last query executed.
 * @property-read mixed $insert_id ID, сгенерированный запросом к таблице с полем, имеющим AUTO_INCREMENT аттрибут. 
 * Если последний запрос был не INSERT или UPDATE  или таблица не имеет поля с аттрибутом AUTO_INCREMENT, возвращается 0
 * @property-read string $sqlstate Returns a string containing the SQLSTATE error code for the last error. The error code consists of five characters. '00000' means no error. The values are specified by ANSI SQL and ODBC. For a list of possible values, see » http://dev.mysql.com/doc/mysql/en/error-handling.html. 
 * @property-read int $thread_id The mysqli_thread_id() function returns the thread ID for the current connection which can then be killed using the mysqli_kill() function. If the connection is lost and you reconnect with mysqli_ping(), the thread ID will be other. Therefore you should get the thread ID only when you need it
 * @property-read int $warning_count Returns the number of warnings from the last query in the connection
 */
class DBConnect extends mysqli
{
	/**
	 * Префикс таблиц, использующихся в текущем проекте
	 */
	public $prefix;
	/**
	 * Последний выполненный запрос
	 * @var string
	 */
	public $lastQuery;

	/**
	 * Вместо параметров, передаваемых в конструктор,
	 * можно определить в рамках проекта соответствующие константы:
	 * MYSQL_SERVER, MYSQL_USER, MYSQL_PASS, DB_NAME, PREFIX
	 * @param string $host Адрес сервера баз данных
	 * @param string $username Имя пользователя баз данных
	 * @param string $passwd Пароль
	 * @param string $dbname Имя базы данных
	 * @param string $prefix Префикс таблиц, использующихся в текущем проекте
	 */
	function __construct( $host = '', $username = '', $passwd = '', $dbname = '', $prefix = '' )
	{
		if (empty( $host     ) && defined( 'MYSQL_SERVER' )) $host         = MYSQL_SERVER;
		if (empty( $username ) && defined( 'MYSQL_USER'   )) $username     = MYSQL_USER;
		if (empty( $passwd   ) && defined( 'MYSQL_PASS'   )) $passwd       = MYSQL_PASS;
		if (empty( $dbname   ) && defined( 'DB_NAME'      )) $dbname       = DB_NAME;
		if (empty( $prefix   ) && defined( 'PREFIX'       )) $this->prefix = PREFIX;
		else $this->prefix = $prefix;

		// Начиная с PHP 8.1 mysqli по умолчанию бросает mysqli_sql_exception
		// вместо того, чтобы возвращать false. Весь код вокруг построен на
		// проверке результата (и на своих ProviderLoadException /
		// ProviderSaveException поверх неё), поэтому режим отчётов возвращаем
		// к прежнему: об ошибке говорит результат запроса и $this->error.
		mysqli_report(MYSQLI_REPORT_OFF);

		parent::__construct( $host, $username, $passwd, $dbname );
		$this->set_charset( "utf8" );
	}

	/**
	 * Возвращает массив ассоциативных массивов из подготовленного select запроса
	 * если строка одна - то сразу ассоциативный массив
	 * @param mysqli_stmt $prepare
	 * @return array 
	 */
	public function fetchAssocPrepare( mysqli_stmt $prepare )
	{
		$meta = $prepare->result_metadata();
		$row = array();
		while( $field = $meta->fetch_field() )
		{
			$params[] = &$row[$field->name];
		}

		call_user_func_array( array( $prepare, 'bind_result' ), $params );

		$c = array();
		$result = array();
		while ($prepare->fetch())
		{
			foreach( $row as $key => $val )
			{
				$c[$key] = $val;
			}
			$result[] = $c;
		}
		$count = count( $result );

		switch ($count)
		{
			case 0 : return false;
			//case 1 : return $result[0];
			default : return $result;
		}
	}
	
	#[\ReturnTypeWillChange]
	public function prepare($query) {
		$this->lastQuery = $query;
		return parent::prepare( $query );
	}
	
	#[\ReturnTypeWillChange]
	public function query($query, $resultmode = null) {
		$this->lastQuery = $query;
		// $resultmode по умолчанию null, а mysqli::query() с PHP 8.1 осуждает
		// передачу null в нескалярный параметр - подставляем его собственное
		// значение по умолчанию.
		return parent::query( $query, $resultmode === null ? MYSQLI_STORE_RESULT : $resultmode );
	}

	/**
	 * Выполняет запрос, используя форматированные строки для подставления имён таблиц 
	 * (префикс подставляется атоматически, в аргументах требуется имя таблицы без префикса)
	 * @link http://ru2.php.net/manual/ru/function.sprintf.php
	 * @param string $query строка формата (запрос) 
	 * @param string $table имя таблицы без префикса
	 * @return mysqli_result
	 */
	public function queryt( $query, $table )
	{
		$args = func_get_args();

		return $this->querytByArr( $args );
	}

    /**
     * Выполняет запрос, используя форматированные строки для подставления имён таблиц 
     * (префикс подставляется атоматически, в аргументах требуется имя таблицы без префикса)
     * @link http://ru2.php.net/manual/ru/function.sprintf.php
     * @param array $arr - массив, первым элементов запрос, далее - имена таблиц
     * @return mysqli_result
     */
    public function querytByArr( $arr )
    {
        return $this->checkReturn( $this->query( $this->sprintft( $arr ) ) );
    }
    
    /**
     * Выполняет запрос, используя конструктор запросов
     * @see DBConnect::buildQuery()
     * @param Array $sqlHash
     * @param array $tables
     * @param boolean $usePrefix - подставлять префикс в имена таблиц
     * @throws Exception при ошибке формирования запроса
     * @return mysqli_result || false
     */
    public function queryb( $sqlHash, $tables = null, $usePrefix = true ) {		
    	return $this->query( $this->buildQuery( $sqlHash, $tables, $usePrefix ) );
    }
    
    /**
     * Выполняет запрос, используя конструктор запросов 
     * и возвращает распарсенную в ассоциативный массив первую строку результата 
     * @see mysqli_result::fetch_assoc()
     * @see DBConnect::buildQuery()
     * @param Array $sqlHash
     * @param array $tables
     * @param boolean $usePrefix - подставлять префикс в имена таблиц
     * @throws Exception при ошибке формирования или выполнения запроса
     * @return array || false
     */
    public function querybSingle( $sqlHash, $tables = null, $usePrefix = true ) {
        $result = $this->queryb( $sqlHash, $tables, $usePrefix );
        if (!$result) throw new Exception( 'Execute query error' );
        return $result->fetch_assoc();
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
     *  <li><b>FROM</b> - таблицы выборки (будут обёрнуты в магические кавычки и,
     *   в зависимости от второго параметра, к этим названиям могут подставляться префиксы), 
     *   может быть массив - если таблиц выборки несколько;</li>
     *  <li><b>JOINS</b> - массив массивов вида 
     *  <code>array('join_operator'=>table_reference,
     *  'ON'=>join_condition'|'USING'=>column_list)</code>, 
     *  где <code>table_reference</code> вида <b>FROM</b>, 
     *  <code>join_condition</code> вида <b>WHERE</b>, 
     *  <code>column_list</code> вида <b>SELECT</b>;</li>
     *  <li><b>WHERE</b> - условие, может быть массивом 
     *  (в таком случае объединяется через <code>AND</code>),
     *  если это ассоциативный массив - то ключ считается названием поля (см. <b>INSERT</b>);</li>
     *  <li><b>GROUP BY</b>;</li> 
     *  <li><b>HAVING</b>;</li> 
     *  <li><b>ORDER BY</b> - порядок, может быть массивом;</li>
     *  <li><b>LIMIT</b>;</li>
     *  <li><b>INSERT|REPLACE</b> - определяет операцию вставки, значение - строка полей, 
     *  в которые будет происходить вставка (может быть пустым), массив полей  
     *  или ассоциативный массив <code>{название поля=>зачение|массив значений}</code>,
     *  если значение является строкой и не начинается с <code>`</code> - 
     *  оно будет пропущено через escape функцию и обёрнуто в <code>''</code>;</li>
     *  <li><b>INGORE</b> - добавляет ключевое слово IGNORE для INSERT запроса 
     * (значение поля при этом игнорируется);</li>
     *  <li><b>INTO</b> - имя таблицы (будут добавлены <code>`</code> 
     *  и по необходимости префикс);</li>
     *  <li><b>VALUES</b> - строка значений, массив строк значений или массив массивов значений, 
     *  в котором элементы расположены в нужном порядке 
     *  (свойство игнорируется, если значения заданы в <b>INSERT</b>);</li>
     *  <li><b>ON DUPLICATE KEY UPDATE</b> - строка значений или массив полей, 
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

    		$sql = $this->addTablesFromHash( $sql, $sqlHash['FROM'], $prefix );  
            
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
    		if (isset( $sqlHash['REPLACE'] )) {
    			$inputData = $sqlHash['REPLACE'];
    			$sql  = 'REPLACE';
    		} else {
    			$inputData = $sqlHash['INSERT'];
    			$sql  = 'INSERT';

                if (isset( $sqlHash['IGNORE'] ))
                    $sql .= ' IGNORE';
    		}
    			
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
	            
	        if (isset( $sqlHash['ON DUPLICATE KEY UPDATE'] )) {
	        	$uValues = '';	    

	        	$dupl = $sqlHash['ON DUPLICATE KEY UPDATE'] === '' 
	        	          ? array() : $sqlHash['ON DUPLICATE KEY UPDATE']; 
	        	
	        	if (is_array( $dupl )) 
	        	{
	        		if (ArrayUtils::isAssoc( $dupl ))
	        			foreach ($dupl as $field => $value) {
	        				if ($uValues != '') $uValues .= ', ';
	        				if ($field[0] != '`') $field = '`' . $field . '`';
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
    		$sql = $this->addJoinsFromHash( $sql, $sqlHash, $prefix );
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
	 * Готовит запрос, используя форматированные строки для подставления имён таблиц
	 * (префикс подставляется атоматически, в аргументах требуется имя таблицы без префикса)
     * @link http://ru2.php.net/manual/ru/function.sprintf.php
     * @param string $query строка формата (запрос) 
	 * @param string $table имя таблицы без префикса
	 * @return mysqli_stmt
	 */
	public function preparet( $query, $table )
	{
		$args = func_get_args();
        
        return $this->checkReturn( $this->prepare( $this->sprintft( $args ) ) );
	}

	/**
	 * Делает запрос для апдейта заданных полей
	 * @param array $fields4Update поле => значение
	 * @return mysqli_result
	 */
	public function updateQueryt( $table, $fields4Update, $where = '' )
	{
		$update = '';
		foreach ($fields4Update as $var => $value) {
			if ($update != '') $update .= ', ';
			$update .= "`" . $var . "` = '" . $this->escape_string( $value ) . "'";
		}

		$sql = "UPDATE `%s` ";
		if ($update != '')
			$sql .= "SET " . $update;
		if ($where != '')
			$sql .= " WHERE " . $where;

		return $this->queryt( $sql, $table );
	}

	/**
	 * Делает запрос вставки заданных полей
	 * @param string $table таблица
	 * @param array $fields4Insert поле => значение
     * @return mysqli_result
	 */
	public function insertQueryt( $table, $fields4Insert )
	{
		return $this->multiInsertQueryt( $table, array( $fields4Insert ) );
	}

    /**
     * 
     * @param string $table таблица
     * @param array $fields4Insert поле => значение
     * @return mysqli_result
     */
    /*public function insertOrUpdateQueryt( $table, $fields4Insert )
    {
        return $this->multiInsertQueryt( $table, array( $fields4Insert ) );
    }*/

	/**
	 * Делает запрос вставки нескольких записей с заданными полями заданных полей
	 * @param string $table таблица
	 * @param array $fields4Insert массив массивов поле => значение
     * @return mysqli_result
	 */
	public function multiInsertQueryt( $table, $fields4InsertArray )
	{
		if (count( $fields4InsertArray ) == 0) return false;
		 
		$fields       = '';
		$valuesTotal  = '';

		for ($i = 0; $i < count( $fields4InsertArray ); $i++) {
			if (count( $fields4InsertArray[$i] ) == 0) {
				//continue;
				// нехер всякую фигню сувать мне
				return false;
			}
			 
			$values = '';
			foreach ($fields4InsertArray[$i] as $var => $value) {
				if ($values != '') {
					if ($i == 0) $fields .= ', ';
					$values .= ', ';
				}

				if ($i == 0) $fields .= '`' . $var . '`';
				$values .= "'" . $this->escape_string( $value ) . "'"; 
			}
			 
			if ($valuesTotal != '') $valuesTotal .= ', ';
			$valuesTotal .= '(' . $values . ')';
		}

		$sql = 'INSERT INTO `%s` (' . $fields . ') VALUES ' . $valuesTotal;
		return $this->queryt( $sql, $table );
	}
    
    /**
     * @see mysqli::escape_string()
     */
    public function escape_string_t( $value )
    {
    	$value = $this->escape_string( $value );
        $value = $this->escape_t($value);
        return $value;
    }
	
	/**
     * @see mysqli::escape_string()
	 */
	#[\ReturnTypeWillChange]
	public function escape_string( $value )
	{
		$value = stripcslashes( $value );
		return parent::real_escape_string( $value );
	}
	
	/**
	 * Экранирует значение для использовании в выражении LIKE
	 * @param string $value
	 * @return string
	 */
	public function escape4Search( $value ) {
		$value = addcslashes( $value, '%_' );
		return $this->escape_string( $value );
	}
	
	/**
	 * Экранирует значение для использовании в выражении LIKE 
	 * в запросе с подстановкой имен таблиц
	 * @param string $value
	 * @return string
	 */
	public function escape4Search_t( $value ) {
		$value = $this->escape4Search( $value );
		return $this->escape_t( $value );
	}

	/**
	 * @see mysqli::multi_query()
	 */
	#[\ReturnTypeWillChange]
	public function multi_query( $query )
	{
		$this->lastQuery = $query;
		if (!parent::multi_query( $query )) return false;
		// это мы избавляемся от ошибки
		// Commands out of sync; you can't run this command now
		// при выполнении следующего запроса после multi_query
		while (@$this->next_result());
		return true;
	}

    /**
     * Выполняет запрос к базе данных
     * @param array $queriesArr Массив запросов (будут объеденены через ';').
     * Для пустого массива будет возвращено true
     * @return boolean
     * @see DBConnect::multi_query()
     */
    public function multiQuery( $queriesArr ) 
    {
        if (count( $queriesArr ) > 0) {
            $query = implode( '; ', $queriesArr ); 
            return $this->multi_query( $query );
        } else {
            return true;
        }
    }

    /**
     * @see mysqli::multi_query()
     */
    public function multi_queryt( $query )
    {
        $args = func_get_args();
        
        return $this->multi_query( $this->sprintft( $args ) );
    }
	
	/**
	 * Отформатировать строку (первым элементом строка), 
	 * подставляя имена таблиц с префиксами 
	 * @param array $args первый элемент - форматируемая строка запроса, дальше - имена таблиц
	 */
	public function sprintft( $args )
	{
		for ($i = 1; $i < count( $args ); $i++) {
            $args[$i] = $this->prefix . $args[$i];
        } 

        return call_user_func_array( 'sprintf', $args );
	}

    // ==================================================================
    //
    // Методы, доступные для переопределения
    //
    // ------------------------------------------------------------------
    
	/**
	 * Проверяет либой из результатов, 
	 * чтобы он не был равен false или null,
	 * а также не являлся пустым массивом
	 * @param mixed $smt
	 * @return bool
	 */
	protected function checkReturn( $smt )
	{
		if (!$smt) return $this->error();
		return $smt;
	}
    
	/**
	 * Обработка ошибки при выполнении запроса
	 * @return bool
	 */
	protected function error()
	{
		//throw new DBException( $this );
		return false;
	}

    // ==================================================================
    //
    // Приватные методы
    //
    // ------------------------------------------------------------------
    
	private function escape_t( $value ) {
		return str_replace( '%', '%%', $value );
	}

    // ----------- вспомогательные методы для конструктора запросов ----------- 

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
    private function getFieldsListForSQL( $list ) {
        if (is_array( $list )) {
            $str = '';
            foreach ($list as $field) {
                if ($str != '') $str .= ', ';
                if (!is_int( $field ) && !is_float( $field )) {
                    $field = (string)$field;
                    if ($field == '') $field = "'" . $field . "'";
                    else if ($field[0] != '`' && $field[0] != '"' && $field[0] != "'")
                        $field = "`" . $field . "`";
                }
                $str .= $field;
            }
            return $str;
        } else return $list; 
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
                $sql  = $this->addTablesFromHash( $sql, current( $curJoin ), $prefix );
                if (isset( $curJoin['ON'] ))
                    $sql = $this->addWhereFromHash( $sql, $curJoin, 'ON' );
                else
                    $sql .= ' USING (' . $this->getFieldsListForSQL( $curJoin['USING'] ) . ') ';
            }
        }
        
        return $sql;
    }
    
    private function addTablesFromHash( $sql, $tables, $prefix ) {
        if (is_array( $tables )) {
            $sql .= '`' . $prefix . implode( '`, `' . $prefix, $tables ) . '`';
        } 
        else if (strlen( $tables ) == 0) throw new Exception( 'Field FROM must bu not empty' );
        else {
            $table = $prefix . $tables;
            if ($tables[0] != '`') $table = '`' . $table . '`';
            $sql .= $table;
        }
            
        return $sql;
    }
    
    private function getValueForSQL( $val ) {
        if (is_string( $val ) && (strlen( $val ) == 0 || $val[0] != '`')) 
            $val = "'" . $this->escape_string( $val ) . "'";
        elseif (is_bool( $val ))
            $val = (int)$val;
            
        return $val;
    }
    
    private function addWhereFromHash( $sql, $sqlHash, $condWord = 'WHERE' ) {
        if (!isset( $sqlHash[$condWord] )) return $sql;
        $where = $sqlHash[$condWord];
        $condWord = ' ' . $condWord . ' ';
        if (!empty( $where )) { 
            $whereStr = '';
            if (is_array( $where )) {   
                if (ArrayUtils::isAssoc( $where ))
                    foreach ($where as $field => $value) {
                        if ($whereStr != '') $whereStr .= ' AND ';
                        if ($field[0] != '`') $field = '`' . $field . '`';
                        $whereStr .= $field . ' = ' . $this->getValueForSQL( $value );
                    }
                else $whereStr .= implode( ' AND ', $where );               
            } else $whereStr = $where;
            
            $sql .= $condWord . $whereStr;
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

?>