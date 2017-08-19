<?php
namespace GMFramework;

/**
 * "Продвинутый" массив
 * @package ru.vbinc.gm.framework.array
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 */
class ArrayList implements \Iterator, \JsonSerializable
{
	/**
	 * Массив данных
	 * @var array
	 */
	protected $_data;

	public function __construct() 
	{
		if (func_num_args() > 0) $this->_data = func_get_args();
		else $this->_data = array();
	}

	public function rewind()
    {
        reset($this->_data);
    }
  
    public function current()
    {
        return current($this->_data);
    }
  
    public function key() 
    {
        return key($this->_data);
    }
  
    public function next() 
    {
        return next($this->_data);
    }
  
    public function valid()
    {
        $key = key($this->_data);
        return $key !== null && $key !== false;
    }
	
	public function setSource( $array )
	{
		$this->_data = $array;
	}

	/**
	 * Получить обыкновенный массив
	 * @return array
	 */
	public function getArray()
	{
		return $this->_data;
	}

	/**
	 * Получить элемент по индексу
	 * @param int $index
	 * @return object
	 */
	public function get( $index )
	{
		return $this->_data[$index];
	}

	/**
	 * Добавить объект в конец
	 */
	public function push( $object )
	{
		$this->_data[] = $object;
	}

	/**
	 * Добавить объект в начало
	 */
	public function unshift( $object )
	{
		array_unshift($this->_data, $object);
	}

	/**
	 * Добавить объект в конец, только если такого еще нет
	 * @param mixed $object
	 */
	public function pushUniquely( $object )
	{
		if (!$this->inArray( $object )) $this->_data[] = $object;
	}

	/**
	 * Проверяет, есть ли уже такой объект в массиве или нет
	 * @param mixed $object
	 * @return bool
	 */
	public function inArray( $object )
	{
		return in_array( $object, $this->_data );
	}

	/**
	 * Получит объект из начала, при этом убрав его из массива
	 * @return mixed
	 */
	public function shift()
	{
		return array_shift( $this->_data );
	}

	/**
	 * Получить индекс объекта
	 * @param $object
	 * @return int
	 */
	public function getIndex( $object )
	{
		$index = array_search( $object, $this->_data );
		if( $index === false ) return -1;
		else return $index;
	}

	/**
	 * Удалить из массива
	 * @param $object
	 * @return mixed
	 */
	public function remove( $object )
	{
		$index = $this->getIndex( $object );
		if( $index < 0 ) return null;
		else return $this->removeByIndex( $index );
	}

	/**
	 * Удалить из массива по индексу
	 * @param $index
	 * @return mixed удаляемый элемент массива
	 */
	public function removeByIndex( $index )
	{
		return array_splice( $this->_data, $index, 1 );
	}

	/**
	 * Возвращает срез списка в виде массива
	 * @return array
	 */
	public function getSlice($start, $length)
	{
	    return array_slice($this->_data, $start, $length);
	}

	/**
	 * Размер массива
	 * @return int
	 */
	public function size()
	{
		return count( $this->_data );
	}

	/**
	 * Объединить элементы массива в строку
	 * @param string $delimiter объединяющая элементы строка
	 * @return string
	 */
	public function join( $delimiter = ',' )
	{
		return implode( $delimiter, $this->_data );
	}
	
	/**
	 * Очищает массив полностью 
	 */
	public function reset()
	{
		$this->_data = array();
	}
	
	/**
	 * @return ArrayList 
	 */
	public function copy()
	{
		$al = new ArrayList();
		$al->setSource( ArrayUtils::copy( $this->_data ) );
		return $al;
	}

    public function jsonSerialize() 
    {
        return $this->_data;
    }

    /**
     * Выполняет сортировку
     * @param  callable $compareFunc <code>int function ( mixed $a, mixed $b )</code>
     * @return boolean Возвращает true, если сортировка была успешно выполнена
     */
    public function sort($compareFunc)
    {
        return usort($this->_data, $compareFunc);
    }
}
?>