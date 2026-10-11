# Imagem apenas de runtime (ADR-015): o código é montado de fora, somente leitura.
FROM php:8.3-fpm

RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
 && docker-php-ext-install pdo_mysql intl bcmath zip opcache pcntl \
 && rm -rf /var/lib/apt/lists/*

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
