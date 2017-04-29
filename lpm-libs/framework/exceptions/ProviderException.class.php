<?php
namespace GMFramework;

/**
 * Исключение провайдера (при работа с данными)
 * @package ru.vbinc.gm.framework.exceptions
 * @author greymag <greymag@gmail.com>
 * @version 0.1
 * @copyright 2013
 */
class ProviderException extends Exception 
{
    function __construct($message = "", $code = 0 ) 
    {
        parent::__construct($message, $code);
    }
}
?>