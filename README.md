# Lightning PM

Простой менеджер проектов.

## Требования

- PHP 5.5
- MySQL 5.5
- Apache с поддержкой `.htaccess` и включенным модулем `mod_rewrite`

### Настройки PHP

`short_open_tag = On`

## Установка

1. Скопируйте все, кроме `_dp`, `_private` и `README.md` на сервер.
2. Переименуйте `lpm-config.inc.template.php` в `lpm-config.inc.php` и заполните его.
3. Импортируйте БД из папки `_db/dump.sql` (используется префикс таблиц по цмолчанию).
4. Убедитесь, что php имеет права на запись в папку `lpm-files` (рекурсивно).
5. Если нужно писать логи, создайте пустую папку `_private` и дайте php права на запись в неё (логи по умолчанию пришутся в `/_private/logs/`)


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

- BOM. Во время разработки или, чаще всего, при сохранении конфига, его сохранили с заголовком (кодировка UTF8 with BOM). Этот заголовок добавляется к любому выводу (т.к. присутствует в файле конфигурации, который поключается всегда) и ломает формат - клиент не может распаковать данные, т.к. в нем пристутсвуют лишние символы. **Решение:** надо просто пересохранить файл(ы) без BOM.
- Двойное сжатие. Flash2PHP самостоятельно сжимает ответ перед отправкой (при включенной настройке `F2P_USE_COMPRESS`, в проекте включена), если при этом где-нибудь на уровне сервера еще включено сжатие для вывода, то данные будут сжаты дважды и клиент не сможет их корректно распарсить. **Решение:** нужно отключить сжатие на уровне сервера.