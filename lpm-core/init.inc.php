<?php
require_once(__DIR__ . '/../lpm-config.inc.php');
require_once(__DIR__ . '/version.inc.php');
require_once(__DIR__ . '/consts.inc.php');
require_once(__DIR__ . '/aliases.inc.php');

date_default_timezone_set('Etc/GMT-3');

// подключаем фреймворк
require_once(ROOT . LIBS_DIR . 'gm-framework-v1.1.1.phar');

// Подключаем фреймворк
// if (!class_exists( 'GMFramework', false ))
require_once(ROOT . LIBS_DIR . 'framework/GMFramework.class.php');

/**
 * Задаёт параметры сессии.
 *
 * Вызывается до старта сессии - после session_start() эти настройки
 * уже не применятся.
 */
function initSessionParams()
{
    // Идентификатор сессии, который клиент придумал сам, приниматься не должен:
    // без строгого режима PHP заведёт сессию под любым присланным
    // идентификатором, а значит его можно заранее подсунуть жертве.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    // Несколько стендов на одном хосте (разных портах) не различаются
    // куками сессии, если у них общее имя, - авторизация на одном
    // затирает сессию на другом. SESSION_COOKIE_NAME позволяет задать
    // своё имя на конкретном развёртывании; если константа не задана,
    // остаётся имя сессии по умолчанию (PHPSESSID), поведение не меняется.
    if (defined('SESSION_COOKIE_NAME') && SESSION_COOKIE_NAME !== '') {
        // Имя куки должно быть простым токеном из буквы/цифры/"_" с хотя бы
        // одной буквой или "_": session_name() сама отвергает чисто числовое
        // имя (например порт стенда "8806") и молча оставляет PHPSESSID, а
        // без этой проверки настройка выглядела бы принятой. Прочий мусор
        // либо ломает сессию тихо и непонятно (PHP отправит некорректный
        // заголовок Set-Cookie), либо облегчает атаку на неё. Невалидное
        // значение просто игнорируется - остаётся имя по умолчанию -
        // а предупреждение уходит в лог ошибок PHP, чтобы опечатку было видно.
        if (preg_match('/^[A-Za-z0-9_]*[A-Za-z_][A-Za-z0-9_]*$/', SESSION_COOKIE_NAME)) {
            session_name(SESSION_COOKIE_NAME);
        } else {
            trigger_error(
                'Некорректное значение SESSION_COOKIE_NAME "' . SESSION_COOKIE_NAME . '", '
                . 'используется имя сессионной куки по умолчанию',
                E_USER_WARNING
            );
        }
    }

    $siteUrl = parse_url(SITE_URL);

    session_set_cookie_params([
        'path' => empty($siteUrl['path']) ? '/' : $siteUrl['path'],
        // По HTTP куку слать нельзя, но и требовать этого от установки
        // на http нельзя - иначе сессия просто не заведётся
        'secure' => isset($siteUrl['scheme']) && $siteUrl['scheme'] === 'https',
        // Куку не должен читать JavaScript
        'httponly' => true,
        // Куку не должны слать запросы со сторонних сайтов
        'samesite' => 'Lax',
    ]);
}

/**
 * Функция инициализации сервера
 */
function init()
{
    initSessionParams();

    // подключаем фреймворк
    GMFramework::useFramework();
    // инициализация времени
    DateTimeUtils::setTimeAdjust(TIMEADJUST * 3600);

    // Будем потихоньку выпиливать старый фреймворк,
    // так что подключаем новую версию вместе со старой
    GMFramework\GMFramework::useFramework();
    // инициализируем логи
    //GMFramework\Log::getInstance()->init(LOGS_PATH);
    // инициализация времени
    GMFramework\DateTimeUtils::setTimeAdjust(TIMEADJUST * 3600);
    
    // автозагрузка
    $importer = GMFramework\ImportClasses::createInstance(ROOT . CORE_DIR, '', false);
    $importer->enableUseAutoSearch(ROOT . CORE_DIR . 'classes.dump');
    $importer->import('PHPExcel', ROOT . LIBS_DIR . 'PHPExcel.php');

    GMFramework::addAutoload('ImportClasses::load');

    require_once ROOT . LIBS_DIR . '/vendor/autoload.php';
}

init();
