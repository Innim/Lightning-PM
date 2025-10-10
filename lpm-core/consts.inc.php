<?php
/**
 * Константы, которые пользователю не надо давать менять
 */

/**
 * путь до корневой директории сайта
 * @var string
 */
define('ROOT', dirname(__FILE__) . '/../');

/**
 * путь до директории логов
 */
define('LOGS_PATH', ROOT . '/_private/logs/');

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
 * Директория загруженных файлов
 * @var string
 */
define('UPLOAD_FILES_DIR', FILES_DIR . 'files/');

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


/**
 * Максимальный размер загружаемого файла в мегабайтах
 * @var int
 */
define('MAX_FILE_SIZE_MB', 50);

/**
 * Максимальный размер загружаемого изображения в мегабайтах
 * @var int
 */
define('MAX_IMAGE_SIZE_MB', 10);

// -- Вспомогательные константы --

define('MAX_FILE_SIZE_BYTES', MAX_FILE_SIZE_MB * 1024 * 1024);
define('MAX_IMAGE_SIZE_BYTES', MAX_IMAGE_SIZE_MB * 1024 * 1024);