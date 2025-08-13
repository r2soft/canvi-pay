# syntax=docker/dockerfile:1.6
ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-fpm-alpine

ARG ENABLE_XDEBUG=0

# Dependências básicas de build e extensões nativas
RUN set -eux; \
    apk add --no-cache git unzip bash autoconf g++ make openssl-dev; \
    docker-php-ext-install pcntl; \
    if [ "$ENABLE_XDEBUG" = "1" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Configurações do PHP
COPY php_ini_configuration.ini /usr/local/etc/php/conf.d/zz-custom.ini
# Config do Xdebug (carrega, mas só ativa se XDEBUG_MODE != off)
COPY xdebug_configuration.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

WORKDIR /app

# Expõe a porta do FPM
EXPOSE 9000

# Comando padrão do php-fpm
CMD ["php-fpm", "-F"]