# Lightning PM

Простой менеджер проектов.

## Требования

- PHP 7.3
- MySQL 8
- Apache с поддержкой `.htaccess` и включенным модулем `mod_rewrite`

### Настройки PHP

```
short_open_tag = On
```

### Настройки MySQL

```
sql_mode=''
```

Требуемые расширения, которые могут быть выключены по умолчанию:
 - [mbstring](https://www.php.net/manual/ru/intro.mbstring.php)
 - [Разбор XML](https://www.php.net/manual/ru/intro.xml.php)
 - [memcached](https://www.php.net/manual/en/book.memcached.php)

## Установка

1. Скопируйте все, кроме `.dev`, `_private`, `ci`, `README.md` и `CHANGELOG.md` на сервер.
2. Переименуйте `lpm-config.inc.template.php` в `lpm-config.inc.php` и заполните его.
3. Импортируйте БД из папки `_db/dump.sql` (используется префикс таблиц по умолчанию).
4. Убедитесь, что php имеет права на запись в папку `lpm-files` (рекурсивно).
5. Если нужно писать логи, создайте пустую папку `_private` и дайте php права на запись в неё (логи по умолчанию пишутся в `/_private/logs/`). Не забудьте запретить к ней доступ снаружи.
6. Обязательно задайте URL сайта заканчивающийся на `/` через глобальную константу `SITE_URL`


## Структура

### `_db`

Приватная директория с файлами для настройки БД.

- `dump.sql` содержит текущий актуальный дамп базы (используется для новой установки).
- `changes-log.sql` содержит историю изменений БД.


## FAQ

### Проблемы при работе таска

#### Не работают ajax запросы, выдается ошибка "net::ERR_CONTENT_DECODING_FAILED 200 (OK)"

Проблема с тем, что ответ на запрос приходит клиенту в неверном формате, который тот не может прочитать.

Самые распространенные причины:

- BOM. Во время разработки или, чаще всего, при сохранении конфига, его сохранили с заголовком (кодировка UTF8 with BOM). Этот заголовок добавляется к любому выводу (т.к. присутствует в файле конфигурации, который подключается всегда) и ломает формат - клиент не может распаковать данные, т.к. в нем присутствуют лишние символы. **Решение:** надо просто пересохранить файл(ы) без BOM.
- Двойное сжатие. Flash2PHP самостоятельно сжимает ответ перед отправкой (при включенной настройке `F2P_USE_COMPRESS`, в проекте включена), если при этом где-нибудь на уровне сервера еще включено сжатие для вывода, то данные будут сжаты дважды и клиент не сможет их корректно распарсить. **Решение:** нужно отключить сжатие на уровне сервера.

## Разработка

### Code Style

Для форматирования кода используется [php-cs-fixer](https://cs.symfony.com/download/php-cs-fixer-v2.phar) с включенными правилами `@PSR1, @PSR2`.

#### Настройка IDE 

Для VS Code можно установить плагин [vscode-php-cs-fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer), но в настройках нужно обязательно указать свой php-cs-fixer и правила:

```json
    "vscode-php-cs-fixer.toolPath": "/usr/local/bin/php-cs-fixer",
    "vscode-php-cs-fixer.rules": "@PSR1,@PSR2",
```

Скачать нужный `php-cs-fixer` можно [здесь](https://cs.symfony.com/download/php-cs-fixer-v2.phar).

### Frontend

В качестве библиотеки стилей используется Bootstrap 5. 
Для переопределения глобальных Bootstrap стилей нужно использовать файл `/.lpm-themes/default/css/bootstrap-reset.css`.
Для использования компонентов библиотеки см. документацию к [Bootstrap](https://getbootstrap.com/).

### Окружение

#### Docker

Docker окружение размещается в `/.dev/docker-env`. См. [README](/.dev/docker-env/README.md).

#### Adminer

В поставку включен Adminer (устанавливается вместе с другим окружением с помощью Docker).

Доступен по адресу приложения с указанием порта `ADMINER_PORT`, заданного в `.dev/docker-env/.env`.