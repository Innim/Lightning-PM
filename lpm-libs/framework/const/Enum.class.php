<?php
namespace GMFramework;

/**
 * Перечисляемый тип (почти) без создания инстанций, 
 * с использованием скалярных значений
 * @package ru.vbinc.gm.framework.const
 * @author greymag <greymag@gmail.com>
 * @version 0.1
 * @copyright 2013
 */
class Enum 
{
    private static $_keysCache   = array();
    private static $_valuesCache = array();
    private static $_dataCache   = array();

    /**
     * Возвращает список доступных значений перечисляемого типа
     * @return array <code>Array of mixed</code>
     */ 
    public static function values() 
    {
        $enum = get_called_class();

        if (!isset(self::$_dataCache[$enum]))
        {
            self::createDataCacheByEnum($enum);
        }

        return self::$_valuesCache[$enum];
    }

    /**
     * Определяет, существует ли такое значение среди определенных
     */
    public static function validateValue($value)
    {
        $values = static::values();
        return in_array($value, $values);
    }

    /**
     * Возвращает количество доступных значений типа
     * @return int
     */
    public static function count()
    {
        $values = self::values();
        return count($values);
    }

    private static function createDataCacheByEnum($enum)
    {
        $refl = new \ReflectionClass($enum);
        $data =$refl->getConstants();
        self::$_dataCache  [$enum] = $data;
        self::$_valuesCache[$enum] = array_values($data);
        self::$_keysCache  [$enum] = array_keys($data);
    }
}
?>