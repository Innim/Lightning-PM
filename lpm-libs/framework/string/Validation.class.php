<?php
namespace GMFramework;

/**
 * Набросок валидатора стандартных полей
 * @package ru.vbinc.gm.framework.string
 * @author GreyMag
 * @version 0.1
 */
class Validation
{
	
	/**
	 * Проверка e-mail на валидность
	 * Warning! Не учитывает возможность создания email с русскими быквами!
	 * @param string $email проверяемый адрес
	 * @return string
	 */
	public static function checkEmail( $email )
	{
		//return ( preg_match( "/^[-a-zA-Z0-9_\.]+@[-a-zA-Z0-9_\.]+\.[-a-zA-Z0-9_\.]+$/", $email ) > 0 ) ? true : false;		
		return ( strlen( $email ) <= 255 && preg_match( "/^([a-z0-9_\-]+\.)*[a-z0-9_\-]+@([a-z0-9][a-z0-9\-]*[a-z0-9]\.)+[a-z]{2,4}$/i", $email ) > 0 ) ? true : false;
	}

	public static function checkUrl( $url )
	{
		return ( !preg_match( "/^(http\:\/\/([-a-zA-Z0-9_\.])+)\.(([-a-zA-Z0-9\/_\.])+)$/", $url ) > 0 ) ? true : false;
	}

	/**
	 * Проверка строки
	 * - должна начинаться с буквы или цифры
	 * - может содержать буквы анг и рус и цифры
	 * @param string $str
	 * @param int $maxLength
	 * @param int $minLength
	 */
	public static function checkStr( $str, $maxLength, $minLength = 1, $ru = true, $spaces = false, $hyphen = false, $addExpr = '' )
	{
        $allowEmpty = $minLength === 0;
		return $str === '' && $allowEmpty === true 
            || self::check( $str, $maxLength, $minLength, true, true, $ru, $spaces, $hyphen, $addExpr );
		/*
		$str = trim( $str );
		$baseExpr = "a-zA-Z0-9";		
		$expr = $baseExpr;
		if ($ru) {
			$expr     .= "a-яА-ЯёЁ";//"а-рА-Рс-яС-ЯёЁ";
			$baseExpr .= "a-яА-ЯёЁ";
		}
		if ($spaces) $expr .= " ";
		if ($hyphen) $expr .= "\-";
		$expr .= $addExpr;

		$minLength = max( $minLength, 1 );
		//return ( !preg_match( "/^(([a-zA-Z0-9]){1}([".$expr."]){" . ( $minLength - 1 ) . ", " . ( $maxLength - 1 ) . "})$/", $str ) > 0 ) ? true : false;
		
		// модификатор /u для того чтобы русские символы работали в правильной кодировке 		
		return ( preg_match( "/^(([" . $baseExpr . "]){1}([" . $expr . "]){" . ( $minLength - 1 ) . "," . ( $maxLength - 1 ) . "})$/u", $str ) > 0 ) ? true : false;
		*/
	}

    /**
     * Проверка 
     * @param string $str
     * @param int $maxLength
     * @param int $minLength
     */
    public static function check( $str, $maxLength, $minLength = 1, $en = true, $digits = true, $ru = true, $spaces = false, $hyphen = false, $addExpr = '' )
    {
        $str = trim( $str );
        $baseExpr = "";        
        
        if ($en) {
        	$baseExpr .= "a-zA-Z";
        }
        if ($digits) {
            $baseExpr .= "0-9";
        }
        if ($ru) {
            //$expr     .= "a-яА-ЯёЁ";//"а-рА-Рс-яС-ЯёЁ";
            $baseExpr .= "a-яА-ЯёЁ";
        }
        $expr = $baseExpr;
        if ($spaces) $expr .= " ";
        if ($hyphen) $expr .= "\-";
        $expr .= $addExpr;

        $minLength = max( $minLength, 1 );
        //return ( !preg_match( "/^(([a-zA-Z0-9]){1}([".$expr."]){" . ( $minLength - 1 ) . ", " . ( $maxLength - 1 ) . "})$/", $str ) > 0 ) ? true : false;
        
        // модификатор /u для того чтобы русские символы работали в правильной кодировке        
        return self::check4Regexp( 
                $str, 
                "/^(([" . $baseExpr . "]){1}([" . $expr . "]){" . 
                        ( $minLength - 1 ) . "," . ( $maxLength - 1 ) . "})$/u" 
               );
    }
    
    /**
     * Проверка на то, что строка удовлетворяет переданному регуляру
     * @param string $str
     * @param string $regExp
     */
    public static function check4Regexp( $str, $regExp )
    {
        return ( preg_match( $regExp, $str ) > 0 ) ? true : false;
    }

	/**
	 * Проверка пароля
	 * - по-умолчанию может содержать буквы анг и цифры
	 * @param string $str
	 * @param int $maxLength
	 * @param int $minLength
	 * @param boolean $characters можно использовать знаки в пароле
	 */
	public static function checkPass( $str, $maxLength, $minLength = 1, $characters = false )
	{
		$str = trim( $str );
		$baseExpr = "a-z0-9";		
		$expr = $baseExpr;
		//if ($characters) $expr .= "!\"№;%:?*()_\+=\-~\/\\\\<{}\[\]";
		if ($characters) $expr .= '!"№;%:?*()_\+=\-~\/\\\\<>\{\}\[\]#&@$\^&|\.,\'';
		//if( $spaces ) $expr .= " ";
		//if( $hyphen ) $expr .= "\-";
		// $expr .= $addExpr;

		$minLength = max( $minLength, 1 );
		$maxLength = max( $maxLength, $minLength );
		//return ( !preg_match( "/^(([a-zA-Z0-9]){1}([".$expr."]){" . ( $minLength - 1 ) . ", " . ( $maxLength - 1 ) . "})$/", $str ) > 0 ) ? true : false;

		//return ( preg_match( "/^([" . $expr . "]){" . $minLength . "," . $maxLength . "}$/i", $str ) > 0 ) ? true : false;
		return self::check4Regexp( 
		          $str, 
		          "/^([" . $expr . "]){" . $minLength . "," . $maxLength . "}$/i" 
		       );
	}
}
?>