<?php
namespace GMFramework;

/**
 * Класс для создания дампа таблиц
 * @package ru.vbinc.gm.framework.db
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2011
 * @version 0.21
 * @access public
 */ 
class MySqlDump {
	/**
	 * 
	 * @var DBConnect
	 */
	private $_db;
	private $_titleComment = 'Dump';
	private $_dbname       = '';
	private $_insertBlock  = 100;
	
	private $_tables = array();
	
	private $_result = array();
	
	/**
	 * 
	 * @param DBConnect $db 
	 * @param $comment заглавный комментарий к дампу
	 */
	function __construct( DBConnect $db, $comment = '' ) {
		$this->_db = $db;
		if ($comment != '') $this->setTitleComment( $comment );
	}
	
	/**
	 * Устанавливает заглавный комментарий дампа
	 * @param String $value
	 */
	public function setTitleComment( $value ) {
		$this->_titleComment = $value;
	}
    
	/**
	 * Устанавливает базу даных, для которой будет делаться дамп
	 * @param string $value
	 */
    public function setDB4Dump( $value ) {
        $this->_dbname = $value;
        $this->_tables = array();
    }
	
	/**
	 * Возвращает структуру таблиц
	 * @param Boolean $addAutoIncrement добавлять значение AUTO_INCREMENT в дамп структуры или нет
	 * @return String|false
	 */
	public function getStructure( $addAutoIncrement = false ) {			
	    if (!$tbls = $this->getTables()) return $this->error();
	    if (!$this->createTables( $tbls, $addAutoIncrement )) return $this->error();
	    return $this->getResult();
	}
	
	/**
	 * Возвращает данные из таблиц
	 * @param array $tables Массив имён таблиц для экспорта, 
	 * по умолчанию экспортируются все таблицы
     * @return String|false
	 */
	public function getData( $tables = null ) {
		if (!$tbls = $this->getTables()) return $this->error();
		
		if ($tables != null && !is_array( $tables )) $tables = null;
		
		$this->addTitleComment( 'Data' );
		foreach ($tbls as $tableName) {
			if ($tables != null && !in_array( $tableName, $tables )) continue;
			if (!$this->tableData( $tableName )) return false;			
		}
		return $this->getResult();
	}
	
	/**
	 * Получает список таблиц в выбранной БД
	 * return array|false
	 */
	private function getTables() {
	    if (count( $this->_tables ) == 0) {
			if (!$query = $this->_db->query( 'SHOW TABLES' )) return $this->error();
	        $this->_tables = array();
	        $index = ($this->_dbname == '') ? 0 : 'Tables_in_' . $this->_dbname;
	        while ($row = $query->fetch_array())
	        {
	            array_push( $this->_tables, $row[$index] );
	        }
	    }
        return $this->_tables;
	}
	
	/**
	 * Получает запросы для создания таблиц
	 * @param $tables массив имён таблиц
	 * @param $addAutoIncrement добавлять значение AUTO_INCREMENT в дамп структуры или нет
	 * @return boolean
	 */
	private function createTables( $tables, $addAutoIncrement ) {
		$this->addTitleComment( 'Structure' );
			             
		foreach ($tables as $tableName) {
			if (!$query = $this->_db->query( 'SHOW CREATE TABLE ' . $tableName ) ) return $this->error();
			$res = $query->fetch_assoc();
			$this->addStr( 'DROP TABLE IF EXISTS `' . $tableName . '`;' );
			
			$createSql = $res['Create Table'];
			if (!$addAutoIncrement) $createSql = preg_replace( '/AUTO_INCREMENT=[0-9]+ /', '', $createSql );
			$this->addStr( $createSql . ';' );
			
			$this->addStr();
		}
		
		return true;
	}
	
	/**
	 * Получает данные из таблицы
	 * @param String $tableName имя таблицы
	 * @return boolean
	 */
	private function tableData( $tableName ) {				
		if (!$query = $this->_db->query( 'SELECT * FROM `' . $tableName . '`' )) return $this->error();
		
		$value = '';
		$i = 0;
		$insertStr = '';
		$fields = array();
		$values = array();
		while ($row = $query->fetch_assoc()) {
            $fields = array();
            $values = array();
			foreach ($row as $field => $value) {
                array_push( $fields, $field );
                array_push( $values, $this->_db->escape_string( $value ) );
            }
			
			if ($i % $this->_insertBlock == 0) {				
				$this->addStr( 'INSERT INTO `' . $tableName . '` (`' . implode( '`, `', $fields ) . '`) VALUES ' );
			}
			$i++;
			$this->addStr( "('" . implode( "', '", $values ) . "')" .
			               ( ( $i % $this->_insertBlock == 0 || $i == $query->num_rows ) ? ';' : ',' ) );
		}
		$this->addStr();
		return true;
	}
	
	/**
	 * Добавляет заглавный комментарий
	 * @param string $type
	 */
	private function addTitleComment( $type ) {
		$this->addStr( 
                       '#' . $this->_titleComment . ' [' . 
                       DateTimeUtils::date( 
                          DateTimeFormat::YEAR_NUMBER_4_DIGITS . '-' .
                          DateTimeFormat::MONTH_NUMBER_2_DIGITS . '-' . 
                          DateTimeFormat::DAY_OF_MONTH_2 
                       ) . '] / ' .
                       $type
                     ); 
	}
	
	/**
	 * Возвращает сохранённый результат, при этом очищая его
	 * @return string
	 */
	private function getResult() {
		$res = implode( "\n", $this->_result );
		$this->_result = array();
		return $res;
	}
	
	/**
	 * Обработка ошибки
	 * @return boolean
	 */
	private function error() {
		$this->_result = array();
		return false;
	}
	
	/**
	 * Добавляет строку в сохранённый результат
	 * @param string $string
	 */
	private function addStr( $string = '' ) {
		array_push( $this->_result, $string );
	}
}
?>