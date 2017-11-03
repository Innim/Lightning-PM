<?php
namespace GMFramework;

/**
 * Исключение фреймворка
 * @package ru.vbinc.gm.framework.exceptions
 * @author greymag
 * @version 0.1
 * @copyright 2013
 */
class Exception extends \Exception 
{
    function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code);
    }
}
?>