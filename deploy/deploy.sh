#!/usr/bin/env bash
# CANG-AI 标准部署脚本（适用于 Supervisor 管理的服务器）
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/cang-ai}"
BRANCH="${BRANCH:-main}"
APP_USER="${APP_USER:-www-data}"

cd "$APP_DIR"

echo "==> 拉取最新代码 ($BRANCH)..."
git fetch origin
git reset --hard "origin/$BRANCH"

echo "==> 安装 PHP 依赖..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 执行数据库迁移..."
php artisan migrate --force

echo "==> 缓存配置..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> 存储链接..."
php artisan storage:link 2>/dev/null || true

echo "==> 修复权限..."
chown -R "$APP_USER:$APP_USER" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "==> 重启图片任务 Worker..."
if command -v supervisorctl &>/dev/null; then
    supervisorctl restart cang-ai-task-worker:* 2>/dev/null || true
else
    pkill -f "task:worker" 2>/dev/null || true
    sleep 1
    nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
    nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
fi

echo "==> 部署完成 ✓"
