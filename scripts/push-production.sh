#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "$0")/.."

mode=${1:-update}
remote=${PIKADO_SSH_HOST:-pikadoapp}
remote_path=${PIKADO_REMOTE_PATH:-/opt/pikado}
seed_logo=${PIKADO_SEED_LOGO:-storage/app/public/venues/1/0JESqWAQotxXztZeOdRej5UXcFPem1gz1qbRO10m.png}

if [[ $mode != update && $mode != --init ]]; then
    echo "Usage: $0 [--init]" >&2
    exit 1
fi

if [[ ! $remote =~ ^[A-Za-z0-9._@-]+$ ]]; then
    echo "Unsafe SSH host value." >&2
    exit 1
fi

if [[ ! $remote_path =~ ^/[A-Za-z0-9._/-]+$ ]]; then
    echo "Unsafe remote path value." >&2
    exit 1
fi

ssh "$remote" "mkdir -p '$remote_path'"

rsync -az --delete-delay \
    --exclude='/.git/' \
    --exclude='/.env' \
    --exclude='/.env.*' \
    --exclude='/backups/' \
    --exclude='/database/*.sqlite' \
    --exclude='/deploy/seed-storage/' \
    --exclude='/node_modules/' \
    --exclude='/public/build/' \
    --exclude='/public/hot' \
    --exclude='/public/storage' \
    --exclude='/storage/' \
    --exclude='/vendor/' \
    ./ "$remote:$remote_path/"

if [[ $mode == --init && -f $seed_logo ]]; then
    ssh "$remote" "mkdir -p '$remote_path/deploy/seed-storage/seed'"
    rsync -az "$seed_logo" \
        "$remote:$remote_path/deploy/seed-storage/seed/nosati-pub-logo.png"
fi

ssh "$remote" "cd '$remote_path' && ./scripts/deploy-production.sh '$mode'"
