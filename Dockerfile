# syntax=docker/dockerfile:1

########################################
# Base runtime (used by docker compose for local dev & CI).
# The application code and vendor/ are provided at runtime via the bind mount,
# so this stage intentionally contains no source.
########################################
FROM dunglas/frankenphp:php8.5-alpine AS base

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
    opcache \
    pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Increase PHP execution time limit to prevent timeouts during API Platform warmup
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini

ENTRYPOINT ["frankenphp", "php-server", "--listen", ":80", "--root", "public/"]

########################################
# Production image (built and published by the docker-publish workflow).
# Self-contained: bakes in the source and production-only dependencies.
########################################
FROM base AS prod

ENV APP_ENV=prod
ENV APP_DEBUG=0

# Install prod dependencies first so this layer is cached until composer.lock changes.
# Scripts are skipped so the build needs no APP_SECRET / database access.
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-progress \
    --no-interaction

# Copy the application source and finalize an optimized, prod-only autoloader.
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

# NOTE: cache warmup, `assets:install`, JWT keys, and secrets are runtime concerns
# and must be provided by the deployment environment (they are not baked in here).
