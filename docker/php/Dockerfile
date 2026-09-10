# syntax=docker/dockerfile:1
FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

# Рантайм-зависимости остаются в образе
RUN apk add --no-cache \
        git \
        icu-libs \
        libpq \
        libzip \
        bash \
        postgresql-client

# Расширения ставятся по одному: параллельный make install нескольких
# расширений в одном вызове конфликтует за каталог modules/
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libzip-dev \
        linux-headers \
    && for ext in pdo_pgsql intl zip opcache pcntl sockets; do \
           docker-php-ext-install -j"$(nproc)" "$ext"; \
       done \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear /usr/src/php/ext/*/modules

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Пользователь www-data с UID/GID хоста, чтобы файлы в bind-mount
# не оказались под root
RUN deluser www-data 2>/dev/null || true; \
    delgroup www-data 2>/dev/null || true; \
    addgroup -g "${GID}" www-data; \
    adduser -u "${UID}" -G www-data -h /var/www -s /bin/bash -D www-data; \
    mkdir -p /var/www/backend /var/www/.composer; \
    chown -R www-data:www-data /var/www

# Xdebug собран, но не включён docker-php-ext-enable: его подключает
# conf.d/xdebug.ini, а режим задаёт переменная XDEBUG_MODE (по умолчанию off —
# выключенное расширение почти ничего не стоит)
COPY conf.d/ /usr/local/etc/php/conf.d/
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/backend
USER www-data

ENV COMPOSER_HOME=/var/www/.composer \
    COMPOSER_ALLOW_SUPERUSER=0

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]
