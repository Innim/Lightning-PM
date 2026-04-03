FROM php:7.3-apache-bullseye

# Image metadata
LABEL version="1.0"
LABEL description="Legacy PHP 7.3 application with Apache"

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
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Fix LDAP library path issue
RUN ln -fs /usr/lib/x86_64-linux-gnu/libldap.so /usr/lib/libldap.so \
    && ln -fs /usr/lib/x86_64-linux-gnu/liblber.so /usr/lib/liblber.so

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype-dir --with-jpeg-dir \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install -j$(nproc) \
        curl \
        gd \
        intl \
        ldap \
        mbstring \
        mysqli \
        pspell \
        xml \
        xsl \
        zip

# Install memcached extension via PECL
RUN pecl install memcached \
    && docker-php-ext-enable memcached

# Enable required Apache modules
RUN a2enmod rewrite headers

# Copy custom configurations
COPY config/etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN ln -sf /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY config/etc/php/7.3/php.ini /usr/local/etc/php/php.ini

# Only expose port 80 (nginx handles HTTPS)
EXPOSE 80

# Use the default apache2-foreground command from the base image
CMD ["apache2-foreground"]