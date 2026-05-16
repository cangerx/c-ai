#!/bin/bash
# 宝塔一键部署脚本 - 放到服务器项目根目录执行
set -e

cd /www/wwwroot/vxvx.eu.cc

echo ">>> 拉取最新代码"
git pull origin main

echo ">>> 检查 .env 文件"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
    echo "    .env 已创建，请编辑填写数据库等配置后重新运行此脚本"
    exit 0
fi

echo ">>> 修复权限"
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo ">>> 安装依赖"
composer install --no-dev --optimize-autoloader --no-interaction

echo ">>> 清除缓存"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ">>> 执行迁移"
php artisan migrate --force

echo ">>> 重启队列worker"
pkill -f "task:worker" 2>/dev/null || true
sleep 1
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &

echo ">>> 部署完成 ✓"
