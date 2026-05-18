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

# 检查 PHP 禁用函数
PHP_INI=$(php -r "echo php_ini_loaded_file();" 2>/dev/null)
if [ -n "$PHP_INI" ]; then
    DISABLED=$(php -r "echo ini_get('disable_functions');" 2>/dev/null)
    NEED_FUNCS="putenv proc_open proc_get_status"
    BLOCKED=""
    for fn in $NEED_FUNCS; do
        if echo "$DISABLED" | grep -qi "$fn"; then
            BLOCKED="$BLOCKED $fn"
        fi
    done
    if [ -n "$BLOCKED" ]; then
        echo "⚠ PHP 禁用了必要函数:$BLOCKED"
        echo "  自动解除中..."
        for fn in $BLOCKED; do
            sed -i "s/,$fn//g; s/$fn,//g; s/$fn//g" "$PHP_INI"
        done
        echo "  已解除，重启 PHP 生效"
        # 尝试重启 PHP-FPM
        if [ -f /etc/init.d/php-fpm-83 ]; then
            /etc/init.d/php-fpm-83 restart
        elif systemctl list-units --type=service | grep -q php; then
            systemctl restart php-fpm-83 2>/dev/null || systemctl restart php8.3-fpm 2>/dev/null || true
        fi
        echo "✓ 禁用函数已解除"
    fi
fi

# Composer 检查及自动安装/升级
if ! command -v composer &>/dev/null || ! composer --version &>/dev/null 2>&1; then
    echo "⚠ Composer 不可用，自动安装最新版..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    if [ -f /usr/bin/composer ] && [ ! -L /usr/bin/composer ]; then
        rm -f /usr/bin/composer
    fi
    ln -sf /usr/local/bin/composer /usr/bin/composer 2>/dev/null || true
fi
COMPOSER_VER=$(composer --version --no-ansi 2>/dev/null | grep -oP '[0-9]+' | head -1)
if [ "${COMPOSER_VER:-0}" -lt 2 ]; then
    echo "⚠ Composer 版本过低，自动升级..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    ln -sf /usr/local/bin/composer /usr/bin/composer 2>/dev/null || true
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
timeout 30 git pull origin main || echo "⚠ git pull 超时或失败，跳过（请确认代码已是最新）"

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
