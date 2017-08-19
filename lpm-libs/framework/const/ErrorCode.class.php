<?php
namespace GMFramework;

/**
 * Константы кодов ошибок фреймворка
 * @package ru.vbinc.gm.framework.const
 * @author greymag
 * @version 0.1
 * @copyright 2013
 */
class ErrorCode
{
    /**
    * Фатальная ошибка сервиса
    */ 
    const FATAL_ERROR  = 50000;
    /**
    * Общая ошибка фреймворка
    */ 
    const COMMON_ERROR = 50001;
    /**
    * Ошибка при загрузке данных
    * @var int
    */
    const LOAD_DATA    = 50002; 
    /**
    * Ошибка при сохранении данных
    * @var int
    */
    const SAVE_DATA    = 50003;
}
?>