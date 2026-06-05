FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    bcmath \
    opcache \
    pcntl \
    zip \
    intl \
    fileinfo

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
