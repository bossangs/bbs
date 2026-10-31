ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-alpine

RUN apk add --no-cache oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring zip opcache \
    && apk del oniguruma-dev libzip-dev

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
