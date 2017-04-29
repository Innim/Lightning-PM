<?php
namespace GMFramework;

/**
 * Класс, содержащий констнаты основных локалей
 * @package ru.vbinc.gm.framework.string.lang
 * @author GreyMag
 * @copyright 2011
 * @version 0.1
 */
class LangLocale
{
    /**
     * Немецкий
     * @string
     */
    const DE = 'de_DE';
	/**
	 * Английский
	 * @string
	 */
	const EN = 'en_US';
	/**
     * Испанский
     * @string
     */
    const ES = 'es_ES';
    /**
     * Французский
     * @string
     */
    const FR = 'fr_FR';
    /**
     * Итальянский
     * @string
     */
    const IT = 'it_IT';
    /**
     * Русский
     * @string
     */
    const RU = 'ru_RU';
    /**
     * Упрощённый китайский
     * @string
     */
    const CN = 'zh_CN';
    /**
     * Португальский
     */
    const PT = 'pt_PT';
    
    function __construct()
    {
        throw new Exception( 'Класс ' . __CLASS__ . ' является статическим' );
    }
}
?>