FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip icu-dev libpq-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install intl pdo pdo_mysql pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
