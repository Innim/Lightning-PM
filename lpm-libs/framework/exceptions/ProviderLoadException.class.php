<?php
namespace GMFramework;

/**
 * Исключение провайдера при загрузке данных
 * @package ru.vbinc.gm.framework.exceptions
 * @author greymag <greymag@gmail.com>
 * @version 0.1
 * @copyright 2013
 */
class ProviderLoadException extends ProviderException 
{
    
    function __construct($message = "Ошибка при загрузке данных", $code = 0 ) 
    {
        parent::__construct($message, $code == 0 ? ErrorCode::LOAD_DATA : $code);
    }
}
?>