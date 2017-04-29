<?php
namespace GMFramework;

/**
 * Утилиты для аботы с массивом
 * @package ru.vbinc.gm.framework.array
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 */
class ArrayUtils
{
    /**
     * 
     * @param Array $array
     * @return Array
     */
	public static function copy( &$array )
    {
    	return array_merge( $array, array() );
    }
    
    /**
     * Добавить объект в конец, только если такого еще нет
     * @param Array $array
     * @param $object
     * @return int|false
     */
    public static function pushUniquely( &$array, $object )
    {
        if (!self::inArray( $array, $object )) return array_push( $array, $object );
        return false;
    }    
    
    /**
     * Аналог стандартной функции in_array().
     * Проблема в том, что когда in_array вызывается для поиска объекта - 
     * то иногда выдается warning. Метод проверяет, является ли $needle объектом
     * и, если нет, то использует in_array(), а если да - ищет сам 
     * @param array $array
     * @param mixed $needle
     * @param boolean $strict = false Использовать строгое сравнение 
     * (для объектов этот параметр игнорируется, объекты всегда сравниваются строго)
     * @see http://tr.php.net/manual/ru/function.in-array.php
     */
    public static function inArray( &$array, $needle, $strict = false ) {
    	if (is_object( $needle )) {
    		foreach ($array as $item) {
    			if ($item === $needle) return true;    			
    		}
    		return false;
    	} else return in_array( $needle, $array, $strict );
    }

    /**
     * Получить индекс объекта
     * @param Array $array
     * @param $object
     * @param boolean $strict = false Использовать строгое сравнение 
     * @return int
     */
    public static function getIndex( &$array, $object, $strict = false )
    {
        $index = array_search( $object, $array, $strict || is_object( $object ) );
        if ($index === false) return -1;
        else return $index;
    }

    /**
     * Удалить из массива
     * @param Array $array
     * @param mixed $object
     * @return mixed удаляемый элемент массива или null, если такой элемент не найден
     */
    public static function remove( &$array, $object )
    {
        $index = self::getIndex( $array, $object );
        if ($index < 0) return null;
        else return self::removeByIndex( $array, $index );
    }

    /**
     * Удалить из массива по индексу
     * @param Array $array
     * @param int $index
     * @return mixed удаляемый элемент массива
     */
    public static function removeByIndex( &$array, $index )
    {
        $arr = array_splice( $array, $index, 1 );
        return isset( $arr[0] ) ? $arr[0] : null;
    }
	
	/**
	 * Определяет, является ли массив ассоциативным. 
	 * Может быть довольно ресурсоемкой!
	 * @param Array $array
	 * @return boolean
	 */
	public static function isAssoc( $array ) {
		return count( $array ) > 0 && array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

    /**
     * Перемещает элемент массива
     * @param  array    &$array    Массив
     * @param  int      $fromIndex Исходный индекс
     * @param  int      $toIndex   Целевой индекс, именно на этой позиции окажется элемент после операции. 
     *                             Может быть отрицательным, тогда считается с конца массива)
     * @return boolean  Успех операции. Перенос может быть неудачным, 
     *                  если указанный индекс выходит за диапазон размера массива
     */
    public static function move(&$array, $fromIndex, $toIndex)
    {
        if ($fromIndex === $toIndex) return true;

        $len = count($array);
        if ($fromIndex < 0 || $fromIndex >= $len) return false;
        if ($toIndex < 0) $toIndex = $len + $toIndex;
        if ($toIndex < 0 || $toIndex >= $len) return false;

        $splice = array_splice($array, $fromIndex, 1);
        array_splice($array, $toIndex, 0, $splice);

        return true;
    }
    
    function __construct()
    {
        throw new Exception( 'Класс ' . __CLASS__ . ' является статическим' );
    }
}
?>