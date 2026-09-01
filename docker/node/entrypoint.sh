#!/bin/sh
set -e

cd /app

# node_modules живёт в отдельном volume — при первом старте он пуст
if [ ! -d node_modules/vite ]; then
    echo "[entrypoint] ставлю npm-зависимости"
    npm install
fi

exec "$@"
