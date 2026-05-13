#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/cang-ai}"
BRANCH="${BRANCH:-main}"

cd "$APP_DIR"

echo "==> Pulling latest code ($BRANCH)..."
git fetch origin
git reset --hard "origin/$BRANCH"

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing Node dependencies and building..."
npm ci
npm run build

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Clearing and caching..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Creating storage link (if needed)..."
php artisan storage:link || true

echo "==> Restarting queue workers..."
php artisan queue:restart

echo "==> Deploy completed successfully."
