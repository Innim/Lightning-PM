<?php
namespace GMFramework;

/**
 * Прямоугольник
 * @package ru.vbinc.gm.framework.math.geom
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 *
 */
class Rectangle
{
	/**
	 * Координата x верхнего левого угла прямоугольника
	 * @var float
	 */
	public $x;
	/**
     * Координата y верхнего левого угла прямоугольника
     * @var float
     */
    public $y;
	/**
     * Ширина прямоугольника
     * @var float
     */
    public $width;
	/**
     * Высота прямоугольника
     * @var float
     */
    public $height;
    
    function __construct( $x = 0, $y = 0, $width = 0, $height = 0 )
    {
    	$this->x = $x;
    	$this->y = $y;
    	$this->width = $width;
    	$this->height = $height;
    }
    
    public function addTip( Point $point )
    {
    	$this->x = $point->x;
    	$this->y = $point->y;
    }
    
    /**
     * Определяет, будет ли указанная точка находится в области этого прямоугольника.  
     * Границы включаются
     * @param $point
     * @return boolean
     */
    public function containsPoint( Point $point ) {
    	return $this->containsCoords( $point->x, $point->y );
    }
    
    /**
     * Определяет, будет ли точка с указанными координатами 
     * находится в области этого прямоугольника.  
     * Границы включаются
     * @param $x
     * @param $y
     * @return boolean
     */
    public function containsCoords( $x, $y ) {
        return $x >= $this->x && $x <= $this->x + $this->width
            && $y >= $this->y && $y <= $this->y + $this->height;
    }
      
}
?>