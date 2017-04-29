<?php
namespace GMFramework;

/**
 * Класс для включения многоязычности в проекте
 * @package ru.vbinc.gm.framework.string.lang
 * @author GreyMag
 * @copyright 2011
 * @version 0.1
 */
class Lang
{
	//protected static $_defaultLang = '';
	//protected static $_strings = array();
	protected static $_store;
	
	
	/**
	 * Возвращает хранилище строк 
	 * @return LangStore
	 */
	public static function getStore() {
		if (!self::$_store) self::$_store = new LangStore();
		return self::$_store;
	}
	
	/**
	 * Сохраняет перевод на нужный язык для переданного ключа.
	 * Подразумевается что в качестве ключа будет использована строка на основном языке
	 * (например английском)
	 * @param string $lang ключ, определяющий язык
	 * @param string $key ключевая строка
	 * @param string $translation перевод на нужный язык
	 */
	public static function addString( $lang, $key, $translation )
	{
		self::getStore()->addString( $lang, $key, $translation );
	}
	
	/**
	 * Возвращает строку в нужном переводе. 
	 * Если перевод на этот язык для заданного ключа не задан -
	 * то возвращается ключ
	 * @param string $key ключевая строка
     * @param string $lang язык, если пустая строка - то используется язык по умолчанию
     * @see #setDefaultLang()
	 * @return string
	 */
    public static function getString( $key, $lang = '' )
    {
        return self::getStore()->getString( $key, $lang );
    }
	
    /**
     * Устанавливает язык по умолчанию, который используется при получении строк перевода
     * @param string $lang ключ, определяющий язык
     */
	public static function setDefaultLang( $lang )
	{
		self::getStore()->setDefaultLang( $lang );
	}
	
    /**
     * Определяет язык по умолчанию
     * @return string 
     */
    public static function getDefaultLang()
    {
        return self::getStore()->getDefaultLang();
    }
	
	/**
	 * Загружает переводы для указанного языка из файла
	 * @param string $keysFilePath путь до файла с ключами
	 * @param string $translationsFilePath путь до файла с переводами
	 * @return boolean;
	 */
	public static function loadTranslationFromFile( $lang, $keysFilePath, $translationsFilePath )
	{
		$keysFile = @file_get_contents( $keysFilePath );
		if (!$keysFile) return false;
		$translationsFile = @file_get_contents( $translationsFilePath );
        if (!$translationsFile) return false;
        
        return self::loadTranslation( $lang, $keysFile, $translationsFile );
	}
	
	/**
     * Загружает переводы по содержимому языковых файлов
     * @param string $lang язык, дльк которого загружается перевод
     * @param string $keysContent содержимое файла с ключами
     * @param string $translationsContent сожержимое файла с переводами
     * @return boolean;
     */
	public static function loadTranslation( $lang, $keysContent, $translationsContent )
    {
        //$keysContent = str_replace( "\r\n", "\n", $keysContent );
        //$keysContent = str_replace( "\r", "\n", $keysContent );
        
        //$translationsContent = str_replace( "\r\n", "\n", $translationsContent );
        //$translationsContent = str_replace( "\r", "\n", $translationsContent );
    	
    	$keys = explode( "\n", $keysContent );
        
        if (count( $keys ) == 0) {
            $keys = explode( "\r", $keysContent );
            if (count( $keys ) == 0) return false;
        }
        
        $translations = explode( "\n", $translationsContent );
        if (count( $translations ) == 0) {
            $translations = explode( "\r", $translationsContent );
        }
        
        $count = min( count( $keys ), count( $translations ) );
        
        for ($i = 0; $i < $count; $i++) {
            self::addString( $lang, $keys[$i], $translations[$i] );
        }
        
        return true;
    }
    
	function __construct()
    {
        throw new Exception( 'Класс ' . __CLASS__ . ' является статическим' );
    }
}
?>