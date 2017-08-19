<?php
namespace GMFramework;

/**
 * Многоугольник. 
 * @package ru.vbinc.gm.framework.math.geom
 * @author GreyMag <greymag@gmail.com>
 * @version 0.1
 *
 */
class Polygon {
	/**
	 * Набор наборов точек. Каждый набор точек определяет замкнутую фигуру.
	 * Наличие нескольких наборов означает, что многоугольник имеет отверствия
	 * @var array
	 */
	public $points = array();
	protected $_pointsCount = 0;
	
	/**
	 * Определяет, является многоугольник выпуклым или нет
	 * @return boolean
	 */
	/*public function isConvex()
	{
		
	} */
	
	/**
	 * Добавляет вершину в описание многоугольника
	 * (в набор последней добавленной фигуры)
	 * @param int $x
	 * @param int $y
	 * @return Point
	 */
	public function addTip( $x, $y )
	{
		if (count( $this->points ) == 0) $this->createSubFigure();
        $point = new Point( $x, $y );
		array_push( $this->points[count( $this->points ) - 1], new Point( $x, $y ) );
		$this->_pointsCount++;
		
		return $point;
	}
    
	/**
	 * Создаёт новую подфигуру в многоугольнике
	 */
	public function createSubFigure()
	{
		array_push( $this->points, array() );
	}
    
    /**
     * Возвращает последнюю добавленную вершину
     * @return Point || false
     */
    public function getLastTip()
    {
        $count = count( $this->points );
    	if ($count == 0) return false;
    	$count2 =  count( $this->points[$count - 1] );
    	if ($count2 == 0) return false;

        return $this->points[$count - 1][$count2 - 1];
    }
    
    /**
     * Возвращает первую вершину подфигуры
     * @param $subFigureIndex индекс подфигуры
     * @return Point
     */
    public function getFirstTip( $subFigureIndex = 0 )
    {
    	return $this->getPoint( 0, $subFigureIndex );
    }
    
    /**
     * Определяет число вершин 
     * @return int
     */
    public function getPointsCount()
    {
    	return $this->_pointsCount;
    }
    
    /**
     * Возвращает вершину по индексу  
     * @param int $index
     * @param int $subFigure
     * @return Point
     */
    public function getPoint( $index, $subFigure = 0 )
    {
        return $this->points[$subFigure][$index];
    }
}
?>