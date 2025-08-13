# syntax=docker/dockerfile:1.6
ARG PHP_VERSION=8.0
FROM php:${PHP_VERSION}-cli-alpine

ARG ENABLE_XDEBUG=0

# Dependências básicas de build e extensões nativas
RUN set -eux; \
    apk add --no-cache git unzip bash autoconf g++ make openssl-dev; \
    docker-php-ext-install pcntl; \
    if [ "$ENABLE_XDEBUG" = "1" ]; then \
        pecl install xdebug && docker-php-ext-enable xdebug; \
    fi

# Configurações do PHP
COPY docker/php_ini_configuration.ini /usr/local/etc/php/conf.d/zz-custom.ini
# Copia config do Xdebug para uma pasta desabilitada por padrão
RUN mkdir -p /usr/local/etc/php/conf.disabled
COPY xdebug_configuration.ini /usr/local/etc/php/conf.disabled/zz-xdebug.ini
# Habilita config do Xdebug somente quando ENABLE_XDEBUG=1
RUN if [ "$ENABLE_XDEBUG" = "1" ]; then \
        mv /usr/local/etc/php/conf.disabled/zz-xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini; \
    fi

WORKDIR /app

# Sem porta exposta (uso CLI). O comando padrão da imagem CLI é suficiente.