<?php
namespace GMFramework;

/**
 * Исключение фреймворка
 * @package ru.vbinc.gm.framework.exceptions
 * @author greymag
 * @version 0.1
 * @copyright 2013
 */
class CommonException extends Exception 
{
    function __construct($message = "Общая ошибка") 
    {
        parent::__construct($message, GMErrorCode::COMMON_ERROR);
    }
}
?>