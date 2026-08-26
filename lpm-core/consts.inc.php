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
 * Максимальный суммарный размер вложений (файлов) в одном запросе, в мегабайтах.
 * Ограничения на размер отдельного файла нет — учитывается только общий объём.
 * @var int
 */
define('MAX_ATTACHMENTS_TOTAL_SIZE_MB', 128);

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
 * Начиная со скольких дней без активности у задачи в тесте
 * показывается пометка о простое. Задачи, которыми занимались
 * недавно, пометкой не помечаются.
 * @var int
 */
define('ISSUE_TEST_AGE_MIN_DAYS', 5);

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
 * Минимальная длина пароля.
 * @var int
 */
define('PASSWORD_MIN_LENGTH', 8);

/**
 * Максимальная длина пароля. Ограничение алгоритма хэширования:
 * всё, что длиннее 72 байт, bcrypt всё равно не учитывает.
 * @var int
 */
define('PASSWORD_MAX_LENGTH', 72);

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

/**
 * Политика Content-Security-Policy для страниц приложения.
 * Пустая строка отключает заголовок.
 *
 * `unsafe-inline` и `unsafe-eval` пока нужны: в шаблонах есть инлайновые
 * обработчики и стили, а Vue 2 компилирует шаблоны в рантайме.
 *
 * `object-src 'none'` сочетается с тем, что YouTube-вставка
 * (`entity-video-item.html`) сейчас не строится - см. комментарий в шаблоне.
 * @var string
 */
if (!defined('SECURITY_CSP_POLICY')) {
    define('SECURITY_CSP_POLICY', implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https: http:",
        "media-src 'self' data: blob: https: http:",
        "font-src 'self' data:",
        "connect-src 'self'",
    ]));
}

/**
 * Слать политику CSP только для отчёта, ничего не блокируя.
 * Нужно, чтобы обкатать более строгую политику на живом трафике.
 * @var bool
 */
if (!defined('SECURITY_CSP_REPORT_ONLY')) {
    define('SECURITY_CSP_REPORT_ONLY', false);
}

/**
 * Время жизни заголовка Strict-Transport-Security, в секундах.
 * 0 отключает заголовок. Отправляется только если SITE_URL - https.
 * @var int
 */
if (!defined('SECURITY_HSTS_MAX_AGE')) {
    define('SECURITY_HSTS_MAX_AGE', 15552000); // 180 дней
}

// -- Вспомогательные константы --

define('MAX_ATTACHMENTS_TOTAL_SIZE_BYTES', MAX_ATTACHMENTS_TOTAL_SIZE_MB * 1024 * 1024);
define('MAX_IMAGE_SIZE_BYTES', MAX_IMAGE_SIZE_MB * 1024 * 1024);