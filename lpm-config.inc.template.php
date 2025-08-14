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

// Токен для GitLab Hook
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


// Настройки каналов оповещений

// Оповещать по Email
define('EMAIL_NOTIFY_ENABLED', true);
// В дебаг-режиме не отправлять письма, а только писать в лог
define('EMAIL_NOTIFY_LOG_ONLY_IN_DEBUG', false);