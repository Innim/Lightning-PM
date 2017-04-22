<?php
namespace GMFramework;

/**
 * Базовые утилиты работы со строками
 * @package ru.vbinc.gm.framework.string
 * @author GreyMag
 * @version 0.1
 */
class BaseString
{
	/**
	 * Генератор случайно строки
	 * @param int $length длина строки, если передается число меньше либо равное 0, 
	 * то длина строки выбирается случайным образом
	 * @param int $min минимальная длина строки при выборе случайным образом
	 * @param int $max максимальная длина строки при выборе случайным образом
	 * @return string
	 */
	public static function randomStr(  $length = -1, $min = 6, $max = 12 )
	{
		// GMF2DO может использовать uniqid ?
		if( $length <= 0 ) $length = rand( $min, $max );

		$symb = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		$str = '';
		for( $i = 0; $i < $length; $i++ ) $str .= substr( $symb, rand( 0, strlen( $symb ) ), 1 );

		return $str;
	}
	
	/**
	 * Возвращает число с нулями впереди
	 * @param int $num
	 * @param int $minChars
	 * @return String
	 */
	public static function printNum( $num, $minChars = 2 ) 
	{
		return str_pad( $num, $minChars, '0', STR_PAD_LEFT );
	}
	
    /**
	 * Разрезает строку по разделителю.
	 * Если передана пустая строка - возвращает пустой массив
	 * @param string $str
	 * @param string $delimiter
	 * @return array
	 */
	public static function split( $str, $delimiter = ',' ) 
	{
		if (trim( $str ) == '') return array();
		return explode( $delimiter, $str );
	}
	
	/**
	 * Разрезает строку по разделителю,
	 * преобразуя элементы к числам
	 * @param string $str
	 * @param string $delimiter
	 * @return array
	 */
	public static function splitNumber( $str, $delimiter = ',' )
	{
		$arr = self::split( $str, $delimiter );
		foreach ($arr as $i => $val) {
			$arr[$i] = (float)$val;
		}
		return $arr;
	}
}
?>