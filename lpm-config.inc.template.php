<?php

/**
 * Шаблон файла конфигурации.
 * Необходимо заполнить значения констант
 * и переименовать его в lpm-config.inc.php
 * @author GreyMag
 * @copyright 2011
 */

// настройки БД
/**
 * Сервер БД mysql
 *
 * Если используете Docker окружение из проекта,
 * то будет: mysql-db
 * @var string
 */
define('MYSQL_SERVER', '');
/**
 * Пользователь mysql
 * @var string
 */
define('MYSQL_USER', '');
/**
 * Пароль пользователя
 * @var string
 */
define('MYSQL_PASS', '');

/**
 * Имя базы данных
 * @var string
 */
define('DB_NAME', '');
/**
 * Префикс таблиц проекта
 * @var string
 */
define('PREFIX', 'lpm_');

// да-да, это значит включен режим дебага
define('DEBUG', false);


// пути
/**
 * url сайта
 * @var string
 */
define('SITE_URL', '');
                                                  

// настройки времени
/**
 * Сдвиг времени сервера в часах
 * @var int
 */
define('TIMEADJUST', 0);

// Имя сессионной куки. Задавать, только если на одном хосте поднято
// несколько стендов (например, на разных портах) - без своего имени
// у них общая кука сессии, и вход на одном стенде выбивает сессию
// на другом. Допустимы только латинские буквы, цифры и "_".
// define('SESSION_COOKIE_NAME', 'PHPSESSID_8806');

// Token для интеграции со Slack
define('SLACK_TOKEN', '');
// Оповещать по Slack
// define('SLACK_NOTIFICATION_ENABLED', true);

// GitLab URL
define('GITLAB_URL', '');

// Token для интеграции с GitLab
define('GITLAB_TOKEN', '');

// Sudo пользователь для интеграции с GitLab
define('GITLAB_SUDO_USER', '');

// Токен для GitLab Hook.
// Обязателен: пока он не задан, вызовы хука отклоняются.
define('GITLAB_HOOK_TOKEN', '');

// Настройки сервера кэша

/**
 * Имя хоста сервера memcached.
 *
 * Если используете Docker окружение из проекта,
 * то будет: memcached
 * @var string
 */
define('MEMCACHED_HOST', '');
/**
 * Порт сервера memcached.
 */
define('MEMCACHED_PORT', 11211);

// Mailgun
// Используется для отправки писем

/**
 * Домен, настроенный в Mailgun.
 */
define('MAILGUN_DOMAIN', '');

/**
 * Ключ API.
 */
define('MAILGUN_API_KEY', '');

/**
 * API URL.
 */
define('MAILGUN_ENDPOINT', '');


// Интеграция с ИИ-моделями

/**
 * Провайдер ИИ, используемый по умолчанию.
 * Поддерживаемые значения: gemini.
 */
define('AI_PROVIDER', 'gemini');

/**
 * Таймаут запроса к API модели, в секундах.
 */
define('AI_REQUEST_TIMEOUT', 60);

/**
 * Ключ доступа к Google Gemini API (выдаётся в Google AI Studio).
 * Пока ключ не задан, обращения к модели не выполняются.
 */
define('AI_GEMINI_API_KEY', '');

/**
 * Модель Gemini, используемая по умолчанию.
 */
define('AI_GEMINI_MODEL', 'gemini-3.6-flash');

// Базовый адрес Gemini API — задавать, только если нужен нестандартный.
// define('AI_GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta');

// Лимит токенов на рассуждения модели: -1 — оставить выбор модели,
// 0 — отключить рассуждения. Если не задано, используется поведение модели.
// Внимание: нулевой лимит принимают не все модели — например, gemini-3.6-flash
// отклоняет такой запрос с ошибкой.
// define('AI_GEMINI_THINKING_BUDGET', -1);


// Заголовки безопасности
// Значения по умолчанию заданы в lpm-core/consts.inc.php,
// здесь их можно переопределить.

// Политика Content-Security-Policy. Пустая строка отключает заголовок.
// define('SECURITY_CSP_POLICY', "default-src 'self'; ...");

// Слать политику только для отчёта, ничего не блокируя —
// нужно, чтобы обкатать более строгую политику на живом трафике.
// define('SECURITY_CSP_REPORT_ONLY', false);

// Время жизни Strict-Transport-Security в секундах, 0 отключает заголовок.
// Отправляется, только если SITE_URL начинается с https.
// define('SECURITY_HSTS_MAX_AGE', 15552000);


// Настройки каналов оповещений

// Оповещать по Email
define('EMAIL_NOTIFY_ENABLED', true);
// В дебаг-режиме не отправлять письма, а только писать в лог
define('EMAIL_NOTIFY_LOG_ONLY_IN_DEBUG', false);


// Сжатие видео

// Асинхронное сжатие загруженных видео через ffmpeg.
// После загрузки видео сжимается в фоне отдельным процессом,
// оригинал заменяется на сжатую версию (для экономии места на сервере).
define('VIDEO_COMPRESS_ENABLED', false);
// Путь к бинарю ffmpeg (или имя команды, если он в PATH)
define('VIDEO_COMPRESS_FFMPEG_BIN', 'ffmpeg');
// Путь к бинарю PHP CLI для запуска фонового воркера
define('VIDEO_COMPRESS_PHP_BIN', 'php');
// Ограничение разрешения при сжатии (px), не зависит от ориентации:
// длинная сторона видео не превысит MAX_LONG_SIDE, короткая — MAX_SHORT_SIDE.
// Пропорции сохраняются, апскейла нет.
define('VIDEO_COMPRESS_MAX_LONG_SIDE', 1920);
define('VIDEO_COMPRESS_MAX_SHORT_SIDE', 1080);
// Качество сжатия x264 (CRF): меньше — лучше качество и больше размер,
// больше — сильнее сжатие. Разумный диапазон 18–30.
define('VIDEO_COMPRESS_CRF', 28);

// Сколько видео разрешено сжимать одновременно на всём сервере; по умолчанию 3.
// Каждый процесс ffmpeg держит свою память, поэтому загрузка нескольких
// роликов разом без предела способна исчерпать память сервера.
// Файлы сверх предела ждут очереди и обрабатываются, когда освободится слот.
// define('VIDEO_COMPRESS_MAX_PARALLEL', 3);

// Предельное время сжатия одного файла, секунды; по умолчанию час.
// По его истечении процесс прерывается, сжатие считается неудачным
// (остаётся оригинал), а слот освобождается для следующего файла из очереди.
// define('VIDEO_COMPRESS_TIMEOUT', 3600);