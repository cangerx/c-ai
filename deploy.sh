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

# 环境检查
echo ""
echo ">>> 检查环境依赖"

# PHP 版本检查
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
if [ -z "$PHP_VER" ]; then
    echo "✗ PHP 未安装"; exit 1
fi
if php -r "exit(version_compare(PHP_VERSION,'8.3.0','<')?1:0);" 2>/dev/null; then
    echo "✓ PHP $PHP_VER"
else
    echo "✗ PHP 版本过低 ($PHP_VER)，需要 8.3+"; exit 1
fi

# PHP 扩展检查
MISSING_EXT=""
for ext in mbstring xml ctype iconv intl pdo_mysql bcmath gd fileinfo curl openssl; do
    if ! php -m 2>/dev/null | grep -qi "^$ext$"; then
        MISSING_EXT="$MISSING_EXT $ext"
    fi
done
if [ -n "$MISSING_EXT" ]; then
    echo "✗ 缺少 PHP 扩展:$MISSING_EXT"
    echo "  请在宝塔面板 → PHP 8.3 → 安装扩展 中启用"
    exit 1
fi
echo "✓ PHP 扩展完整"

# Composer 检查
if ! command -v composer &>/dev/null; then
    echo "✗ Composer 未安装"; exit 1
fi
COMPOSER_API=$(composer --no-ansi about 2>/dev/null | grep -oP 'version \K[0-9]+\.[0-9]+' | head -1)
if composer --no-ansi about 2>/dev/null | grep -q "^Composer version 1\."; then
    echo "✗ Composer 版本过低，请运行: composer self-update"
    exit 1
fi
echo "✓ Composer $(composer --version --no-ansi 2>/dev/null | grep -oP '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"

# 首次部署检查
if [ ! -f .env ]; then
    echo ""
    echo ">>> 首次部署 - 初始化环境"
    composer install --no-dev --optimize-autoloader --no-interaction
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
