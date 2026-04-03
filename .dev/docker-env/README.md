# Docker Evn

Базовая инфраструктура для запуска проекта на базе Docker.

## Первоначальная настройка

Скопируйте `.env.template` в `.env` и заполните нужные значения настроек.

### Права на запись

Чтобы MySQL мог работать, нужно дать права на запись в папку с данными `data/`. 
Для этого мы передаем в контейнер uid и gid пользователя для mysql внутри контейнера.
Это должен быть пользователь на хостовой машине, который владеет папкой `data/`.

Чтобы узнать uid и gid текущего пользователя:

```bash
# Узнать uid
id -u 
# Узнать gid
id -g
```

Задайте значения `USER_ID` и `GROUP_ID` в `.env`.

Аналогично, чтобы MySQL и Apache могли писать логи, убедитесь, что используемый пользователь имеет права на запись
в директории, указанные в `.env` в качестве путей для логов (`APACHE_LOGS_PATH`, `PHP_LOGS_PATH`).

💡 Вам следует создать все директории, монтируемые в Docker, включая директории логов. 
Иначе они могут быть созданы от root, и тогда контейнеры не смогут туда писать.

## Запуск

Для запуска используется Docker Compose. Ниже приведены основные команды 
(для старого Docker будет отдельная команда `docker-compose` вместо `docker compose`)

Выполните в текущей директории:

```bash
docker compose up
```

чтобы поднять окружение.

Для запуска в фоне добавьте флаг `-d`:

```bash
docker compose up -d
```

Если менялся `Dockerfile`, то нужно пересобрать контейнер

```bash
docker compose up --build
```

Для перезапуска контейнеров

```bash
docker compose restart
```

## Особенности настройки proxy через nginx

Если вы запускаете проект за nginx, то нужно убедиться, что проксирование настроено правильно.

Обратите внимание на следующие моменты:
- нужно задать `client_max_body_size`, чтобы можно было загружать файлы нужного размера
(значение должно быть не меньше, чем `upload_max_filesize` и `post_max_size` в `.dev/docker-env/config/etc/php/7.3/php.ini`);
- нужно запретить доступ к служебным папкам `_private` и `.dev`, чтобы они не были доступны извне (временное решение, пока не будет настроено на уровне проекта/окружения или директории будут убраны);
- порт проксирования должен соответствовать порту `APP_PORT`, заданному в `.env`;
- здесь же настраивается SSL и перенаправление HTTP на HTTPS.

Пример конфигурации nginx:

```nginx
server {
    server_name wip.task.innim.ru;
    listen 80;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name  wip.task.innim.ru;

    ssl_certificate      /etc/letsencrypt/live/wip.task.innim.ru/fullchain.pem;
    ssl_certificate_key  /etc/letsencrypt/live/wip.task.innim.ru/privkey.pem;

    ssl_session_cache shared:SSL:1m;
    ssl_session_timeout  5m;

    ssl_ciphers  HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers   on;

    client_max_body_size 100M;

    location ~* ^/(_private|.dev)($|\/) {
       deny all;
    }

    location / {
        proxy_pass http://localhost:8801;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
```

## Установка и настройка на production

TODO: описать настройку и запуск более подробно