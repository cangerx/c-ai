#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
  storage/app/public \
  storage/app/private \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${SKIP_PUBLIC_SYNC:-false}" != "true" ] && [ -d /opt/cang-ai-public ]; then
  rsync -a --delete /opt/cang-ai-public/ public/
fi

if [ ! -L public/storage ]; then
  rm -rf public/storage
  php artisan storage:link >/dev/null 2>&1 || true
fi

# 若 config 缓存中的 locale 与当前 APP_LOCALE 不一致，清掉缓存让其下次按真实 env 重建
if [ -f bootstrap/cache/config.php ]; then
  CACHED_LOCALE=$(php -r "\$c=@include 'bootstrap/cache/config.php'; echo \$c['app']['locale']??'';" 2>/dev/null || true)
  WANT_LOCALE="${APP_LOCALE:-zh_CN}"
  if [ -n "$CACHED_LOCALE" ] && [ "$CACHED_LOCALE" != "$WANT_LOCALE" ]; then
    echo "Locale mismatch (cached=$CACHED_LOCALE want=$WANT_LOCALE), clearing config cache..."
    rm -f bootstrap/cache/config.php
  fi
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
  until php -r '
    $host = getenv("DB_HOST") ?: "mysql";
    $port = getenv("DB_PORT") ?: "3306";
    $db = getenv("DB_DATABASE") ?: "cang_ai";
    $user = getenv("DB_USERNAME") ?: "cang_ai";
    $pass = getenv("DB_PASSWORD") ?: "";
    new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
  ' >/dev/null 2>&1; do
    echo "Waiting for database..."
    sleep 2
  done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

if [ -f storage/installed ] && [ "${APP_ENV:-production}" != "local" ]; then
  php artisan app:optimize
fi

exec "$@"
