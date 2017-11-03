<?php
namespace GMFramework;

/**
 * Объект, загружаемый из ассоциативного массива 
 * @author GreyMag
 */
interface IStreamObject
{
    /**
     * Возвращает "лёгкий" объект для отправки клиенту. 
     * Поля задаются при помощи метода <code>self::addClientFields()</code>
     * @param array|string $addfields = null Массив имён или имя дополнительного полей
     * @param string $addfields,... Неограниченное количество дополнительных имён полей
     * @return object 
     */
    function getClientObject($addfields = null);
    
    /**
     * Парсит исходные данные.
     * @param array|object $data (ассоциативный) массив или объект
     * @throws Exception
     */
    function loadStream($data);
}
?>