FROM php:8.2-fpm-alpine

RUN apk update --no-cache && \
    apk upgrade --no-cache
RUN apk add --no-cache \
        supervisor \
        bash
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    linux-headers
RUN apk add --no-cache \
    freetype-dev \
    jpeg-dev \
    icu-dev \
    libzip-dev

#####################################
# PHP Extensions
#####################################
# Install the PHP shared memory driver
RUN pecl install APCu && \
    docker-php-ext-enable apcu

# Install the PHP bcmath extension
RUN docker-php-ext-install bcmath

# Install the PHP graphics library
# RUN docker-php-ext-configure gd \
#     --with-freetype \
#     --with-jpeg
# RUN docker-php-ext-install gd

# Install the PHP mysqli extention
RUN docker-php-ext-install mysqli && \
    docker-php-ext-enable mysqli

# Install the PHP opcache extention
RUN docker-php-ext-enable opcache

# Install the PHP pcntl extention
RUN docker-php-ext-install pcntl

# Install the PHP pdo_mysql extention
RUN docker-php-ext-install pdo_mysql

# Install the PHP zip extention
RUN docker-php-ext-install zip

#####################################
# Composer
#####################################
RUN curl -s http://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer