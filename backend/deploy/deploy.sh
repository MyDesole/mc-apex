#!/usr/bin/env bash
#
# Выкладка APEX на сервер. Запускается на сервере из корня репозитория
# после того, как нужный коммит уже лежит в рабочей копии (git pull / checkout).
#
#   APP_DIR=/var/www/mc-apex.ru/mc-apex bash backend/deploy/deploy.sh
#
# Делает: бэкап БД, сборку фронтенда, зависимости, миграции, кеши,
# перезапуск очередей и Reverb. Сидеры каталога безопасно перезапускать.
#
# Без простоя API: тяжёлая часть (бэкап, npm ci, vite build — минуты) идёт
# при работающем сайте, фронтенд собирается в frontend/dist.next и подменяется
# одним переименованием. Режим обслуживания включается, только если есть
# неприменённые миграции или поменялся composer.lock, и держится лишь пока
# идут composer, миграции и кеши (секунды).

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mc-apex.ru/mc-apex}"
BACKUP_DIR="${BACKUP_DIR:-/root/backups}"
WEB_USER="${WEB_USER:-www-data}"

cd "$APP_DIR/backend"

log() { printf '\n\033[1;35m==> %s\033[0m\n' "$*"; }

env_get() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'; }

log "Бэкап базы"
TS=$(date +%Y%m%d-%H%M%S)
mkdir -p "$BACKUP_DIR/$TS"
MYSQL_PWD="$(env_get DB_PASSWORD)" mysqldump \
    -h "$(env_get DB_HOST)" -P "$(env_get DB_PORT)" -u "$(env_get DB_USERNAME)" \
    --single-transaction --routines --no-tablespaces "$(env_get DB_DATABASE)" \
    | gzip > "$BACKUP_DIR/$TS/db.sql.gz"
cp .env "$BACKUP_DIR/$TS/backend.env"
git -C "$APP_DIR" rev-parse HEAD > "$BACKUP_DIR/$TS/git-head.txt"
# Храним последние 14 бэкапов
ls -1dt "$BACKUP_DIR"/*/ 2>/dev/null | tail -n +15 | xargs -r rm -rf

log "Фронтенд (сайт при этом работает)"
cd "$APP_DIR/frontend"
npm ci --no-audit --no-fund
rm -rf dist.next
npm run build -- --outDir dist.next --emptyOutDir

# Карта сайта кладётся в свежую сборку заранее: после подмены она уже на месте
cd "$APP_DIR/backend"
php artisan sitemap:generate --path="$APP_DIR/frontend/dist.next/sitemap.xml" || true
# Планировщик (www-data) обновляет карту раз в сутки — файл должен быть его
chown "$WEB_USER":"$WEB_USER" "$APP_DIR/frontend/dist.next/sitemap.xml" 2>/dev/null || true

# Открытые вкладки ещё просят чанки старой сборки: оставляем их рядом с новыми
if [ -d "$APP_DIR/frontend/dist/assets" ]; then
    cp -rn "$APP_DIR/frontend/dist/assets/." "$APP_DIR/frontend/dist.next/assets/" 2>/dev/null || true
fi
# ...но не навсегда: старше двух недель выбрасываем
find "$APP_DIR/frontend/dist.next/assets" -type f -mtime +14 -delete 2>/dev/null || true

log "Нужен ли режим обслуживания"
LOCK_HASH_FILE="$BACKUP_DIR/.composer-lock.md5"
LOCK_HASH="$(md5sum composer.lock | cut -d' ' -f1)"
NEED_DOWN=0
REASON=""

if ! php artisan migrate:status --pending 2>/dev/null | grep -q "No pending migrations"; then
    NEED_DOWN=1
    REASON="есть неприменённые миграции"
fi

if [ ! -f "$LOCK_HASH_FILE" ] || [ "$(cat "$LOCK_HASH_FILE")" != "$LOCK_HASH" ]; then
    NEED_DOWN=1
    REASON="${REASON:+$REASON, }изменился composer.lock"
fi

if [ "$NEED_DOWN" = "1" ]; then
    log "Режим обслуживания ($REASON)"
    php artisan down --retry=15 || true
    trap 'php "$APP_DIR/backend/artisan" up' EXIT

    log "Composer"
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    echo "$LOCK_HASH" > "$LOCK_HASH_FILE"
else
    log "Без простоя: миграций и новых зависимостей нет"
    # Новые классы нужны автозагрузчику, зависимости при этом не трогаем
    composer dump-autoload --optimize --no-interaction
fi

log "Миграции и каталог"
php artisan migrate --force
php artisan db:seed --class=AchievementSeeder --force
php artisan db:seed --class=ShopItemSeeder --force
# Экипировка подземелий: скрытые предметы магазина, сидер идемпотентный
php artisan db:seed --class=DungeonGearSeeder --force
# Очки рейтинга и сила кланов — страховка, если формулу или данные меняли миграцией
php artisan apex:rating-recalc

log "Кеши"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link 2>/dev/null || true

log "Подмена фронтенда"
cd "$APP_DIR/frontend"
# Два переименования подряд: окно, когда dist нет, — доли секунды
rm -rf dist.old
if [ -d dist ]; then mv dist dist.old; fi
mv dist.next dist
rm -rf dist.old

log "Права"
chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR/backend/storage" "$APP_DIR/backend/bootstrap/cache"

log "Перезапуск сервисов"
cd "$APP_DIR/backend"
# Мягкий перезапуск: процесс сам выходит после текущей задачи, supervisor
# поднимает его заново. «supervisorctl restart all» ждал до stopwaitsecs=3600
# и держал сайт в режиме обслуживания, пока кто-то сидел в чате.
php artisan queue:restart
php artisan reverb:restart
systemctl reload php8.4-fpm

log "Готово: $(git -C "$APP_DIR" log --oneline -1)"
