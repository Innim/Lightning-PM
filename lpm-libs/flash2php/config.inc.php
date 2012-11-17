<?php
/**
 * Файл конфигурации для Flash2PHP
 * @author GreyMag <greymag@gmail.com>
 * @version 0.5b
 */

/**
 * Директория, где будут размещены файлы с классами сервисов.
 * Файл сервиса должен носить имя ИМЯ_КЛАССА.php<br/>
 * Путь до этой директории указывается от корня F2P 
 */
define( 'F2P_SERVICES_PATH', '../lpm-core/services/');

/**
 * Режим отладки. 
 * При включённом режиме отладки доступны сервисы браузера
 */
define( 'F2P_DEBUG_MODE', true );

/**
 * Использовать gzip сжатие
 */
define( 'F2P_USE_COMPRESS', true );

/**
 * Использовать короткие имена для базовых переменных.
 * service -> s, method -> m, params -> p
 */
define( 'F2P_USE_SHORT_NAMES', false );



?>