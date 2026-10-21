FROM php:8.3-apache-bookworm

# Image metadata
LABEL version="2.0"
LABEL description="Lightning PM application on PHP 8.3 with Apache"

# Install required PHP extensions and system packages
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libldap2-dev \
    libmemcached-dev \
    libpq-dev \
    libpspell-dev \
    libxml2-dev \
    libxslt1-dev \
    libzip-dev \
    zlib1g-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libfreetype6-dev \
    libwebp-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions.
# curl, mbstring, opcache и xml собраны в самом базовом образе - повторная
# установка пересобрала бы их как разделяемые модули и дала бы
# "Module already loaded" при каждом старте.
# Каталог библиотек для ldap зависит от архитектуры (x86_64 / aarch64),
# поэтому берётся из dpkg-architecture, а не прописывается строкой.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure ldap --with-libdir=lib/$(dpkg-architecture -qDEB_HOST_MULTIARCH) \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        ldap \
        mysqli \
        pspell \
        xsl \
        zip

# Расширение memcached собирается из релизного архива на GitHub, а не через
# pecl install: pecl.php.net отвечал 504 и валил сборку целиком. Версия и
# контрольная сумма зафиксированы; если GitHub пересоберёт архив тега и сумма
# перестанет сходиться - сверять её нужно заново, а не выкидывать проверку.
ARG MEMCACHED_VERSION=3.4.0
ARG MEMCACHED_SHA256=10b98a0fdc3ce9ab823db59e8935f068de87e374728871a1efacdadd8c7807d1
RUN set -eux; \
    curl -fsSL -o /tmp/php-memcached.tar.gz \
        "https://github.com/php-memcached-dev/php-memcached/archive/refs/tags/v${MEMCACHED_VERSION}.tar.gz"; \
    echo "${MEMCACHED_SHA256}  /tmp/php-memcached.tar.gz" | sha256sum -c -; \
    mkdir -p /usr/src/php-memcached; \
    tar -xzf /tmp/php-memcached.tar.gz -C /usr/src/php-memcached --strip-components=1; \
    cd /usr/src/php-memcached; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable memcached; \
    rm -f /tmp/php-memcached.tar.gz

# Install ffmpeg for background video compression
RUN apt-get update && apt-get install -y ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules
RUN a2enmod rewrite headers deflate expires

# Copy custom configurations
COPY config/etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN ln -sf /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY config/etc/php/8.3/php.ini /usr/local/etc/php/php.ini

# Only expose port 80 (nginx handles HTTPS)
EXPOSE 80

# Use the default apache2-foreground command from the base image
CMD ["apache2-foreground"]
