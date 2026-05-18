#!/bin/bash
# CANG-AI 宝塔一键部署/更新脚本
# 用法: bash deploy.sh          (首次部署+更新)
#       bash deploy.sh update   (仅更新)
set -e

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
cd "$APP_DIR"

echo "============================================"
echo "  CANG-AI 部署脚本"
echo "  目录: $APP_DIR"
echo "============================================"

# 首次部署检查
if [ ! -f .env ]; then
    echo ""
    echo ">>> 首次部署 - 初始化环境"
    cp .env.example .env
    php artisan key:generate --force
    echo ""
    echo "┌─────────────────────────────────────────┐"
    echo "│  .env 已创建，请编辑以下配置后重新运行:  │"
    echo "│  - APP_URL                              │"
    echo "│  - DB_CONNECTION / DB_DATABASE          │"
    echo "│  - 其他 API 密钥                        │"
    echo "└─────────────────────────────────────────┘"
    echo ""
    echo "编辑完成后运行: bash deploy.sh"
    exit 0
fi

echo ""
echo ">>> [1/7] 拉取最新代码"
git pull origin main

echo ""
echo ">>> [2/7] 安装 PHP 依赖"
composer install --no-dev --optimize-autoloader --no-interaction

echo ""
echo ">>> [3/7] 执行数据库迁移"
php artisan migrate --force

echo ""
echo ">>> [4/7] 缓存配置"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo ">>> [5/7] 存储链接"
php artisan storage:link 2>/dev/null || true

echo ""
echo ">>> [6/7] 修复权限"
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo ""
echo ">>> [7/7] 重启队列 Worker"
pkill -f "task:worker" 2>/dev/null || true
sleep 1
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &

echo ""
echo "============================================"
echo "  部署完成 ✓"
echo "  站点: $(grep APP_URL .env | cut -d= -f2)"
echo "  Worker PID: $(pgrep -f 'task:worker' | tr '\n' ' ')"
echo "============================================"
