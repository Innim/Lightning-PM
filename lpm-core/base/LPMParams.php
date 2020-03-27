<?php
/**
 *  
 * @author GreyMag
 * 
 * @property-read string $uid основной идентификатор страницы
 * @property-read string $suid идентификатор второго уровня
 */
class LPMParams extends LPMBaseObject {
	const QUERY_ARG_SID = 'sid';
	const USER_ID = 'userId';
	/*public $uid  = '';
	public $suid = '';
	public $puid = '';*/
	
	private $_args = array();
	private $_queryArgs = [];
		
	function __construct() {
		parent::__construct();	

		$args = [];

		// TODO: это бы перенести в роутер
		if (isset($_GET['route'])) {
			$path = explode('/', $_GET['route']);
			foreach ($path as $i => $value) {
				if (!empty($value))
					$args[$i] = $this->_db->escape_string($value);
			}
			unset($_GET['route']);
		}

		// Некоторые аргументы могут быть переданы напрямую,
		// но только те, которые мы ждем
		$queryArgNames = [self::QUERY_ARG_SID, self::USER_ID];
		$queryArgs = [];
		foreach ($queryArgNames as $name) {
			if (isset($_GET[$name]))
				$queryArgs[$name] = $this->_db->escape_string($_GET[$name]);
		}

		$this->_args = $args;
		$this->_queryArgs = $queryArgs;
		
		//$this->parseData( $_GET );
		// if (isset( $_GET['args'] ) && is_array( $_GET['args'] )) {
		// 	$this->_args = $_GET['args'];
		// 	foreach ($this->_args as $i => $value) {
		// 		$this->_args[$i] = $this->_db->escape_string( $value );
		// 	}
		// }
		unset( $_GET );
	}
	
	function __get( $var ) {
		switch ($var) {
			case 'uid'  : return $this->getArg( 0 );
			case 'suid' : return $this->getArg( 1 );
		}
	}
	
	public function getArg( $num ) {
		if (!isset( $this->_args[$num] )) return '';
		else return $this->_args[$num];
	}

	/**
	 * Возвращает индекс аргумента по значению
	 * @param  mixed $arg Значение
	 * @return int Индекс (-1 - если не найдено)
	 */
	public function getArgIndex($arg) {
		$argVal = (string)$arg;
		foreach ($this->_args as $key => $value) {
			if ($value === $argVal) 
				return $key;
		}
		return -1;	    
	}

	public function getArgs() {
		return $this->_args;
	}

	public function getQueryArg($name) {
		return isset($this->_queryArgs[$name]) ? $this->_queryArgs[$name] : null;
	}

	/**
	 * Удаляет и возвращает первый аргумент.
	 * @return string
	 */
	public function shiftArg() {
		return array_shift($this->_args);
	}
	
	/*public function setVar( $var, $value ) {
		$value = $this->_db->escape_string( $value );
		return parent::setVar($var, $value);
	}*/
}
?>