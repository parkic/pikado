#!/usr/bin/env bash

set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v ngrok >/dev/null 2>&1; then
    echo "ngrok nije instaliran. Instaliraj ga komandom: brew install ngrok/ngrok/ngrok"
    exit 1
fi

if [ -f public/hot ]; then
    hot_backup="/tmp/pikado-public-hot-$(date +%s)"
    mv public/hot "$hot_backup"
    echo "Vite dev marker je privremeno pomeren u $hot_backup"
fi

echo "Pripremam bazu i produkcijske assete..."
php artisan migrate --force
npm run build

echo
echo "Pokrećem Pikado server, queue, Reverb i javni ngrok link."
echo "Novi javni URL će biti prikazan u redu koji počinje sa 'Forwarding'."
echo "Sve zaustavljaš jednom prečicom: Ctrl+C"
echo

npx concurrently \
    --kill-others \
    --names "server,queue,reverb,ngrok,awake" \
    --prefix-colors "blue,green,magenta,cyan,yellow" \
    "php artisan serve --host=0.0.0.0 --port=8000" \
    "php artisan queue:work --tries=3 --timeout=0" \
    "php artisan reverb:start" \
    "ngrok http 8000" \
    "caffeinate -dimsu"
