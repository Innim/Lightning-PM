<?php
/**
 * Глобальный класс по-умолчанию. <br/>
 * Не рекомендуется использовать эту реализацию в проектах - 
 * рекомендуемым поведением является создание собственной реализации класса 
 * <code>Globals</code> в рамках проекта. <br/>
 * При использовании также необходимо учитывать что создается только одна инстанция этого класса
 * @package ru.vbinc.gm.framework.utils
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2012
 */
class DefaultGlobals extends Globals
{
    protected static $_instance = null;
    /**
     * @return DefaultGlobals
     */ 
    /*public static function getInstance() {
        if (self::$_instance === null) self::$_instance = new DefaultGlobals();
        return self::$_instance;
    }*/
}
?>