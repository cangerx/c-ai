#!/bin/bash
# CANG-AI 快速更新脚本（日常使用）
# 用法: bash update.sh
set -e

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
cd "$APP_DIR"

echo ">>> 拉取最新代码..."
git pull origin main

echo ">>> 安装依赖..."
composer install --no-dev --optimize-autoloader --no-interaction -q

echo ">>> 迁移数据库..."
php artisan migrate --force

echo ">>> 刷新缓存并优化..."
php artisan app:optimize

echo ">>> 重启 Worker..."
pkill -f "task:worker" 2>/dev/null || true
sleep 1
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &

echo ">>> 更新完成 ✓ ($(date '+%H:%M:%S'))"
