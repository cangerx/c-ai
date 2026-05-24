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

if [ "${APP_ENV:-production}" != "local" ]; then
  php artisan app:optimize
fi

exec "$@"
