<?php
namespace GMFramework;

/**
 * Точка
 * @package ru.vbinc.gm.framework.math.geom
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2010
 * @version 0.2
 * @access public
 */
class Point
{	
	/**
	 * Координата x точки
	 * @var float
	 */ 
	public $x;
	/**
     * Координата y точки
     * @var float
     */
    public $y;

	function __construct( $x, $y )
	{
		$this->x = (float)$x;
		$this->y = (float)$y;
	}

	/**
	 * Проверяет совпадение с другой точкой
	 * @param Point $point
	 * @return bool
	 */
	public function equals( Point $point )
	{
		return Point::check( $this, $point );
	}

	/**
	 * Проверяет несовпадение с другой точкой
	 * @param Point $point
	 * @return bool
	 */
	public function nonEquals( Point $point )
	{
		return !$this->equals( $point );
	}

	/**
	 * Сдвинуть точку
	 * @param float $offsetX сдвиг по x
	 * @param float $offsetY свиг по y
	 * @return Point
	 */
	public function offset( $offsetX, $offsetY )
	{
		$this->x += (float)$offsetX;
		$this->y += (float)$offsetY;

		return $this;
	}

	/**
	 * Проверяет равенство двух точек
	 * @param Point $firstPoint
	 * @param Point $secondPoint
	 * @return bool
	 */
	public static function check( Point $firstPoint, Point $secondPoint )
	{
		return ( $firstPoint->x == $secondPoint->x && $firstPoint->y == $secondPoint->y );
	}

	/********************
	 * магические методы
	 ********************/
	public function __toString()
	{
		return $this->x . ';' . $this->y;
	}
}
?>