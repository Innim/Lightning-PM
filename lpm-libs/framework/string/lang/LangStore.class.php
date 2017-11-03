<?php
namespace GMFramework;

/**
 * Хранилище переводов на различные языки
 * @package ru.vbinc.gm.framework.string.lang
 * @author GreyMag
 * @copyright 2011
 * @version 0.1
 */
class LangStore 
{
	protected $_strings = array();
    protected $_defaultLang = '';
    
    /**
     * Сохраняет перевод на нужный язык для переданного ключа.
     * Подразумевается что в качестве ключа будет использована строка на основном языке
     * (например английском)
     * @param string $lang ключ, определяющий язык
     * @param string $key ключевая строка
     * @param string $translation перевод на нужный язык
     */
    public function addString( $lang, $key, $translation )
    {
    	$key = trim( $key );
    	if ($lang == '' || $key == ''/* || $translation == ''*/) return;
        
        $this->_strings[$lang][$key] = trim( $translation );
    }
    
    /**
     * Возвращает строку в нужном переводе. 
     * Если перевод на этот язык для заданного ключа не задан -
     * то возвращается ключ
     * @param string $key ключевая строка
     * @param string $lang язык, если пустая строка - то используется язык по умолчанию
     * @param boolean $useDefaultLang использовать язык по-умолчанию, 
     * если перевод для заданного языка не найден. 
     * Если этот параметр false - то вернется ключ
     * @return string
     */
    public function getString( $key, $lang = null, $useDefaultLang = false )
    {
        if ($lang == null || $lang == '') $lang = $this->_defaultLang;
        $key = trim( $key );
        if ($this->translateExist( $key, $lang )) return $this->_strings[$lang][$key];
        return $useDefaultLang ? $this->getString( $key, $this->_defaultLang, false ) : $key;
    }
    
    public function translateExist( $key, $lang = null ) {
    	if ($lang == null || $lang == '') $lang = $this->_defaultLang;
    	return isset( $this->_strings[$lang] ) 
              && isset( $this->_strings[$lang][$key] ) 
                && $this->_strings[$lang][$key] != '';
    }
    
    /**
     * Устанавливает язык по умолчанию, который используется при получении строк перевода
     * @param string $lang ключ, определяющий язык
     */
    public function setDefaultLang( $lang )
    {
        $this->_defaultLang = $lang;
    }
    
    /**
     * Определяет язык по умолчанию
     * @return string 
     */
    public function getDefaultLang()
    {
        return $this->_defaultLang;
    }
    
    public function getStrings()
    {
    	return $this->_strings;
    }
    
    public function resetStrings() {
    	$this->_strings = array();
    }
}
?>