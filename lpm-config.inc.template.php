<?php

/**
 * Шаблон файла конфигурации. 
 * Необходимо заполнить значения констант 
 * и переменовать его в lpm-config.inc.php
 * @author GreyMag
 * @copyright 2011
 */

// настройки БД
/**
 * Сервер БД mysql
 * @var string
 */
define( 'MYSQL_SERVER', '' ); 
/**
 * Пользователь mysql
 * @var string
 */
define( 'MYSQL_USER', '' ); 
/**
 * Пароль пользователя
 * @var string
 */
define( 'MYSQL_PASS', '' ); 

/**
 * Имя базы данных
 * @var string
 */
define( 'DB_NAME', '' );
/**
 * Префикс таблиц проекта 
 * @var string
 */
define( 'PREFIX', 'lpm_' );

// да-да, это значит включен режим дебага
define( 'DEBUG', false );


// пути
/**
 * url сайта
 * @var string
 */
define( 'SITE_URL', '' ); 
                                                  

// настройки времени
/**
 * Сдвиг времени сервера в часах
 * @var int
 */
define( 'TIMEADJUST', 0 );

// Token для интеграции со Slack
define('SLACK_TOKEN', '');

// GitLab URL
define('GITLAB_URL', '');

// Token для интеграции со GitLab
define('GITLAB_TOKEN', '');

// Sudo пользователь для интеграции со GitLab
define('GITLAB_SUDO_USER', '');