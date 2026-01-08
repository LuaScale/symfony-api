FROM dunglas/frankenphp:php8.5-alpine

RUN apk add --no-cache \
    git \
    unzip \
    zip \
    bash

RUN install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    intl \
    zip \
    opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Increase PHP execution time limit to prevent timeouts during API Platform warmup
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini

ENTRYPOINT ["frankenphp", "php-server", "--listen", ":80", "--root", "public/"]