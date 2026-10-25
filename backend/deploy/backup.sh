#!/usr/bin/env bash
#
# Ночной бэкап APEX: дамп MySQL и .env, хранится KEEP_DAYS дней.
# Ставится в cron файлом deploy/cron (см. там же).

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mc-apex.ru/mc-apex}"
BACKUP_DIR="${BACKUP_DIR:-/root/backups/daily}"
KEEP_DAYS="${KEEP_DAYS:-14}"

cd "$APP_DIR/backend"

env_get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }

mkdir -p "$BACKUP_DIR"
FILE="$BACKUP_DIR/mc_apex-$(date +%Y%m%d-%H%M).sql.gz"

MYSQL_PWD="$(env_get DB_PASSWORD)" mysqldump \
    -h "$(env_get DB_HOST)" -P "$(env_get DB_PORT)" -u "$(env_get DB_USERNAME)" \
    --single-transaction --routines --no-tablespaces "$(env_get DB_DATABASE)" \
    | gzip > "$FILE.tmp"
mv "$FILE.tmp" "$FILE"

# Загруженные пользователями файлы (аватары, обложки, вложения) — раз в неделю
if [ "$(date +%u)" = 7 ]; then
    tar -czf "$BACKUP_DIR/storage-$(date +%Y%m%d).tar.gz" -C "$APP_DIR/backend/storage/app" public
fi

find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete

echo "$(date -Is) backup ok: $FILE ($(du -h "$FILE" | cut -f1))"
