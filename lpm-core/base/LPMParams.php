<?php
/**
 *  
 * @author GreyMag
 * 
 * @property-read string $uid основной идентификатор страницы
 * @property-read string $suid идентификатор второго уровня
 */
class LPMParams extends LPMBaseObject
{
	/*public $uid  = '';
	public $suid = '';
	public $puid = '';*/
	
	private $_args = array();
		
	function __construct() 
	{
		parent::__construct();	
		
		//$this->parseData( $_GET );
		if (isset( $_GET['args'] ) && is_array( $_GET['args'] )) {
			$this->_args = $_GET['args'];
			foreach ($this->_args as $i => $value) {
				$this->_args[$i] = $this->_db->escape_string( $value );
			}
		}
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
	
	/*public function setVar( $var, $value ) {
		$value = $this->_db->escape_string( $value );
		return parent::setVar($var, $value);
	}*/
}
?>