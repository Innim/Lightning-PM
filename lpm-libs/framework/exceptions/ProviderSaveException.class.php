<?php
namespace GMFramework;

/**
 * Исключение провайдера при сохранении данных
 * @package ru.vbinc.gm.framework.exceptions
 * @author greymag <greymag@gmail.com>
 * @version 0.1
 * @copyright 2013
 */
class ProviderSaveException extends ProviderException 
{
    function __construct($message = "Ошибка при сохранении данных", $code = 0 ) 
    {
        parent::__construct($message, $code == 0 ? ErrorCode::SAVE_DATA : $code);
    }
}
?>