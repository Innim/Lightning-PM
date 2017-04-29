<?php
namespace GMFramework;

/**
 * Класс, сокращающий и расшифровывающий названия полей объектов. <br/>
 * Принятый массив полей сортируется по алфавиту, 
 * далее все поля заменяются по порядку на: a, b, c, ..., z, aa, ab, ac, ...,az, ba, bb, ...<br/>  
 * @author greymag
 *
 */
class Cutdowner 
{
	const LETTERS = 'abcdefghijklmnopqrstuvwxyz';
	private $_fields = array();
	private $_lcount;
	
	function __construct() {
		$this->_lcount = strlen( self::LETTERS );
	}
	
	/**
	 * Поля, использующиеся для сокращаения по умолчанию 
	 * @param array $fields
	 */
	public function setFields( $fields ) {
		$this->sortFields( $fields );
		$this->_fields = $fields;
	} 
	
	/**
	 * Сокращает названия свойств объекта в соответствии с набором переданных полей. 
	 * Если свойство есть в объекте, но нет в списке полей для сокращения - 
	 * оно не будет записано в возвращаемый объект. 
	 * Аналогично если название свойства есть в списке полей, но в объекте такого свойства нет
	 * (при этом сокращение все равно будет выполняеться с его учетом)
	 * @param object|array $object объект или ассоциативный массив
	 * @param array $fields
	 * @return object новый объект с сокращенными названиями полей
	 */
	public function encode( $object, $fields = null ) {
		$object = (object)$object;
		$cut = (object)array();
		
		if ($fields != null && is_array( $fields )) {
			$this->sortFields( $fields );
		} else {
			$fields = $this->_fields;
		}
		
		$index = 0;
		foreach ($fields as $field) {
			if (isset( $object->$field )) {
				$letter = $this->getDigit( $index );
				$cut->$letter = $object->$field;
			}
			
			$index++;
		}
		
		return $cut;
	}
	
	private function sortFields( &$array ) {
		sort( $array, SORT_STRING );
	}

	/**
	 * Преобразует число в нужный формат (a,...,z,aa,...,az,ba,...)
	 * @param int $val
	 * @return string
	 */
	private function getDigit( $val ) {
		$letter  = '';
		$letters = self::LETTERS;
		$gno = floor( $val / $this->_lcount );
		if ($gno > 0) $letter .= $this->getDigit( $gno - 1 );
		
		$letter .= $letters{$val % $this->_lcount};
		return $letter;
	}
}
?>