FROM php:8.4-fpm-alpine

ARG WWWUSER=1000
ARG WWWGROUP=1000

USER root

RUN apk add --no-cache \
    bash \
    bash-completion \
    curl \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    sqlite \
    sqlite-dev \
    shadow

RUN docker-php-ext-install \
    pdo_sqlite \
    mbstring \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php-fpm/bashrc /etc/bash/bashrc

RUN usermod -u ${WWWUSER} www-data \
    && groupmod -g ${WWWGROUP} www-data

WORKDIR /var/www/html

USER www-data
