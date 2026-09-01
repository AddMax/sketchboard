#!/bin/sh
set -e

APP_DIR=/var/www/backend
cd "$APP_DIR"

log() { echo "[entrypoint] $*"; }

# php-fpm — «главный» контейнер: он ставит зависимости и катит миграции.
# websocket поднимается на том же коде и просто дожидается результата.
IS_PRIMARY=0
[ "$1" = "php-fpm" ] && IS_PRIMARY=1

if [ "$IS_PRIMARY" = "1" ]; then
    if [ ! -f vendor/autoload.php ]; then
        log "vendor/ отсутствует — ставлю зависимости"
        composer install --no-interaction --prefer-dist
    fi

    log "жду PostgreSQL"
    until php -r 'exit(@fsockopen("postgres", 5432) ? 0 : 1);' 2>/dev/null; do
        sleep 1
    done

    log "применяю миграции"
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || \
        log "миграции не применились — проверьте 'docker compose logs php'"

    log "готово, стартую php-fpm"
else
    log "жду, пока php-контейнер установит зависимости"
    until [ -f vendor/autoload.php ]; do sleep 1; done

    log "жду Redis"
    until php -r 'exit(@fsockopen("redis", 6379) ? 0 : 1);' 2>/dev/null; do
        sleep 1
    done
fi

exec "$@"
