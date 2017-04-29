<?php
namespace GMFramework;

/**
 * Провайдер данных для опций
 * @author greymag
 */
interface IOptionsDataProvider
{
    /**
     * Загружает опции 
     * @param array $options <code>Array of string</code> Массив имен опций для загрузки
     * @param string $_, ... Неограниченное количество дополнительных имен опций
     * @return array <code>Array of string => mixed</code> Возвращает ассоциативный массив загруженны опций
     * @throws Exception
     */
    function loadOptions($options);
}
?>