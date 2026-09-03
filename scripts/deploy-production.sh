#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "$0")/.."

mode=${1:-update}

if [[ $mode != update && $mode != --init ]]; then
    echo "Usage: $0 [--init]" >&2
    exit 1
fi

if [[ ! -f .env.production ]]; then
    echo "Missing .env.production." >&2
    exit 1
fi

if [[ $(stat -c '%a' .env.production) != 600 ]]; then
    echo ".env.production must have mode 600. Run: chmod 600 .env.production" >&2
    exit 1
fi

compose=(docker compose -f compose.production.yaml --env-file .env.production)

"${compose[@]}" config --quiet
"${compose[@]}" build --pull
"${compose[@]}" up -d db

if [[ $mode == update ]]; then
    "$(dirname "$0")/backup-production.sh"
fi

"${compose[@]}" run --rm app php artisan migrate --force --no-interaction

if [[ $mode == --init ]]; then
    "${compose[@]}" run --rm app php artisan db:seed \
        --class=Database\\Seeders\\ProductionSeeder \
        --force \
        --no-interaction
fi

"${compose[@]}" up -d --remove-orphans

# Nginx resolves the app service when it loads its configuration. Reload it
# after Compose may have recreated the app container so it never keeps the
# previous container IP and serves transient 502 responses.
"${compose[@]}" exec -T web nginx -s reload

for attempt in {1..30}; do
    if "${compose[@]}" exec -T web wget --quiet --output-document=- http://127.0.0.1/up >/dev/null; then
        break
    fi

    if [[ $attempt -eq 30 ]]; then
        echo "Application health check failed." >&2
        "${compose[@]}" ps
        exit 1
    fi

    sleep 2
done

app_manifest=$(
    "${compose[@]}" exec -T app sha256sum /var/www/html/public/build/manifest.json \
        | awk '{ print $1 }'
)
web_manifest=$(
    "${compose[@]}" exec -T web sha256sum /var/www/html/public/build/manifest.json \
        | awk '{ print $1 }'
)

if [[ -z $app_manifest || $app_manifest != "$web_manifest" ]]; then
    echo "Frontend manifest mismatch between the Laravel and Nginx images." >&2
    exit 1
fi

"${compose[@]}" ps

if [[ $mode == --init ]]; then
    echo "Initial production database created without test data."
    echo "After the first login, change the temporary admin password."
else
    echo "Production deployment completed."
fi
