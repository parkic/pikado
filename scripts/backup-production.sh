#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "$0")/.."

compose=(docker compose -f compose.production.yaml --env-file .env.production)
backup_dir=${PIKADO_BACKUP_DIR:-./backups}
timestamp=$(date -u +%Y%m%dT%H%M%SZ)

umask 077
mkdir -p "$backup_dir"

database_backup="$backup_dir/pikado-database-$timestamp.sql.gz"
uploads_backup="$backup_dir/pikado-uploads-$timestamp.tar.gz"

"${compose[@]}" exec -T db sh -c \
    'mariadb-dump --single-transaction --quick --lock-tables=false -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' \
    | gzip -9 > "$database_backup"

"${compose[@]}" exec -T app tar -czf - -C /var/www/html/storage/app public \
    > "$uploads_backup"

find "$backup_dir" -type f \
    \( -name 'pikado-database-*.sql.gz' -o -name 'pikado-uploads-*.tar.gz' \) \
    -mtime +14 -delete

echo "Backup written to $backup_dir"
