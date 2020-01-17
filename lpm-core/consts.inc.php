<?php
/**
 * Константы, которые пользователю не надо давать менять
 */

/**
 * путь до корневой директории сайта
 * @var string
 */
define('ROOT', dirname( __FILE__ ) . '/../');

/**
 * путь до директории логов
 */
define('LOGS_PATH' , ROOT . '/_private/logs/');

/**
* ядро
* @var string
*/
define('CORE_DIR', 'lpm-core/');

/**
* загруженные файлы
* @var string
*/
define('FILES_DIR', 'lpm-files/');

/**
* Директория загруженных изображений
* @var string
*/
define('UPLOAD_IMGS_DIR', FILES_DIR . 'imgs/');

/**
 * Папка библиотек
 * @var string
 */
define('LIBS_DIR', 'lpm-libs/');

/**
 * Папка Flash2PHP
 * @var string
 */
define('F2P_DIR', LIBS_DIR . 'flash2php/');

/**
* путь до фреймворка
* @var string
*/
define('FRAMEWORK_DIR', LIBS_DIR . 'framework/');

/**
* путь до фреймворка
* @var string
*/
define('SCRIPTS_DIR', 'lpm-scripts/');

/**
* путь до фреймворка
* @var string
*/
define('SERVICES_DIR', 'lpm-services/');

/**
* путь до фреймворка
* @var string
*/
define('THEMES_DIR', 'lpm-themes/');

/**
 * Год разработки
 * @var int
 */
define('COPY_YEAR', 11);