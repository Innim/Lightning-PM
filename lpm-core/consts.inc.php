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

/**
 * Сколько меток показывать в форме задачи сразу; если меток заметно больше —
 * остальные сворачиваются под ссылку «ещё N». Настройка отображения.
 * @var int
 */
define('ISSUE_LABELS_DISPLAY_LIMIT', 10);

/**
 * Как часто (в секундах) обновлять время последнего визита пользователя.
 * В пределах этого интервала повторные запросы визит не перезаписывают,
 * чтобы не писать в базу на каждый запрос.
 * @var int
 */
define('VISIT_THROTTLE_SECONDS', 5 * 60);

/**
 * Предельный размер одного файла лога (в мегабайтах). При достижении
 * файл уходит в архив (см. LOG_ARCHIVE_COUNT), запись продолжается в новый.
 * @var int
 */
define('LOG_FILE_MAX_SIZE_MB', 5);

/**
 * Сколько архивных файлов лога хранить при ротации.
 * @var int
 */
define('LOG_ARCHIVE_COUNT', 3);

/**
 * Начиная со скольких дней без активности задача в тесте считается
 * застоявшейся и подсвечивается в списке.
 * @var int
 */
define('ISSUE_TEST_STALE_DAYS', 14);

/**
 * Сколько дней без активности по задаче в тесте прибавляют
 * один пункт к её приоритету при сортировке списка задач.
 * @var int
 */
define('ISSUE_TEST_AGING_DAYS_PER_POINT', 2);

/**
 * Максимальная прибавка к приоритету за простой задачи в тесте.
 * Ограничивает старение, чтобы задача не могла обогнать задачи,
 * которые важнее её больше чем на эту величину.
 * @var int
 */
define('ISSUE_TEST_AGING_MAX_BONUS', 20);

/**
 * Максимальная длина названия проекта (ограничение колонки в БД).
 * @var int
 */
define('PROJECT_NAME_MAX_LENGTH', 255);

/**
 * Максимальная длина описания проекта (ограничение колонки в БД).
 * @var int
 */
define('PROJECT_DESC_MAX_LENGTH', 65535);

// -- Вспомогательные константы --

define('MAX_FILE_SIZE_BYTES', MAX_FILE_SIZE_MB * 1024 * 1024);
define('MAX_IMAGE_SIZE_BYTES', MAX_IMAGE_SIZE_MB * 1024 * 1024);