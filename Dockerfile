# syntax=docker/dockerfile:1.7

FROM php:8.3-fpm-alpine AS php-runtime

RUN apk add --no-cache \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        git \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        posix \
        zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-pikado.ini
COPY deploy/entrypoint.sh /usr/local/bin/pikado-entrypoint

RUN chmod 0755 /usr/local/bin/pikado-entrypoint

WORKDIR /var/www/html

FROM php-runtime AS builder

RUN apk add --no-cache nodejs npm

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs

ARG VITE_APP_NAME=Pikado
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=pikado.nosatipub.com
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https

ENV VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-dev \
        --no-interaction

# Generate Wayfinder's route helpers explicitly so Laravel errors are not
# swallowed by Vite's plugin wrapper during image builds.
RUN php artisan wayfinder:generate --with-form

RUN npm run build

FROM php-runtime AS app

COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && if [ -d deploy/seed-storage ]; then cp -R deploy/seed-storage/. storage/app/public/; fi \
    && chown -R www-data:www-data bootstrap/cache storage \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

USER www-data

ENTRYPOINT ["pikado-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:stable-alpine AS web

COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=builder /var/www/html/public /var/www/html/public

RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
