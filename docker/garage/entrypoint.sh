#!/bin/sh
# Запускает сервер Garage и один раз настраивает его: роль узла, бакеты
# public/private, ключ доступа из .env. Все шаги идемпотентны, поэтому
# скрипт безопасно выполнять при каждом старте контейнера — так же, как
# php-контейнер катит миграции.
set -e

log() { echo "[garage] $*"; }

for var in GARAGE_RPC_SECRET S3_ACCESS_KEY S3_SECRET_KEY S3_BUCKET_PUBLIC S3_BUCKET_PRIVATE; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
        log "не задана переменная $var — проверьте .env"
        exit 1
    fi
done

"$@" &
server_pid=$!
# sh не передаёт сигналы фоновому процессу: пробрасываем сами,
# иначе docker stop ждёт таймаут и убивает Garage
trap 'kill -TERM $server_pid 2>/dev/null' TERM INT

# Пока узлу не назначена роль, /health отвечает 503 — ждём не «здоров»,
# а просто «слушает»: иначе bootstrap никогда не начнётся
log "жду, пока admin API начнёт отвечать"
until wget -SqO /dev/null http://127.0.0.1:3903/health 2>&1 | grep -q 'HTTP/'; do
    sleep 1
done

# CLI ходит по RPC и берёт секрет из GARAGE_RPC_SECRET; логи соединения
# не информативны, прячем их
garage() { command garage "$@" 2>/dev/null; }

# ── Роль узла ──────────────────────────────────────────────────────────
# Новый узел не хранит данные, пока ему не назначена роль в layout.
# Если layout уже применён (версия > 0), оставляем как есть.
if garage layout show | grep -q 'Current cluster layout version: 0'; then
    node_id=$(garage status | awk '/^[0-9a-f]{16} /{print $1; exit}')
    log "назначаю роль узлу $node_id (ёмкость ${GARAGE_CAPACITY:-10G})"
    garage layout assign -z dc1 -c "${GARAGE_CAPACITY:-10G}" "$node_id"
    garage layout apply --version 1
fi

# ── Бакеты ─────────────────────────────────────────────────────────────
for bucket in "$S3_BUCKET_PUBLIC" "$S3_BUCKET_PRIVATE"; do
    if ! garage bucket info "$bucket" >/dev/null; then
        log "создаю бакет $bucket"
        garage bucket create "$bucket"
    fi
done

# Публичный бакет раздаётся web-endpoint'ом (порт 3902) без подписи запросов.
# Приватный — только через S3 API с ключом
garage bucket website --allow "$S3_BUCKET_PUBLIC" >/dev/null

# ── Ключ доступа ───────────────────────────────────────────────────────
# Пару ключей задаёт .env, чтобы php-контейнер знал её заранее.
# Повторный import того же ID Garage отвергает, поэтому сначала проверяем
if ! garage key info "$S3_ACCESS_KEY" >/dev/null; then
    log "импортирую ключ $S3_ACCESS_KEY"
    garage key import -n sketchboard --yes "$S3_ACCESS_KEY" "$S3_SECRET_KEY"
fi

for bucket in "$S3_BUCKET_PUBLIC" "$S3_BUCKET_PRIVATE"; do
    garage bucket allow --read --write --owner "$bucket" --key "$S3_ACCESS_KEY" >/dev/null
done

log "готово: бакеты $S3_BUCKET_PUBLIC (web) и $S3_BUCKET_PRIVATE, ключ $S3_ACCESS_KEY"

wait $server_pid
