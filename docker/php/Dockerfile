FROM composer:2.2 AS composer

FROM php:7.4-fpm-alpine

RUN apk add --no-cache \
        mariadb-dev \
        libzip-dev \
        libpng-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        mbstring \
        bcmath \
        gd \
        zip \
        exif \
        pcntl \
        intl \
        sockets \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
