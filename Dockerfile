FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        gmp-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        gmp \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && addgroup -g 1000 www \
    && adduser -D -G www -u 1000 www

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www:www /var/www/html

USER www

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]
