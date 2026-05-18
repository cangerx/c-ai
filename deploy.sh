#!/bin/bash
# CANG-AI 宝塔一键部署/更新脚本
# 用法: bash deploy.sh          (首次部署+更新)
#       bash deploy.sh update   (仅更新)
set -e

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

echo "============================================"
echo "  CANG-AI 部署脚本"
echo "  目录: $APP_DIR"
echo "============================================"

# ========== 自动修复环境 ==========
echo ""
echo ">>> 自动检查并修复环境"

# 检测 PHP CLI 路径（宝塔可能有多版本）
PHP_BIN=$(which php 2>/dev/null)
if [ -z "$PHP_BIN" ]; then
    for p in /www/server/php/83/bin/php /www/server/php/82/bin/php /usr/bin/php; do
        if [ -x "$p" ]; then PHP_BIN="$p"; break; fi
    done
fi
if [ -z "$PHP_BIN" ]; then
    echo "✗ 未找到 PHP，请在宝塔面板安装 PHP 8.3"; exit 1
fi

# PHP 版本检查
PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
if $PHP_BIN -r "exit(version_compare(PHP_VERSION,'8.3.0','<')?1:0);" 2>/dev/null; then
    echo "✓ PHP $PHP_VER ($PHP_BIN)"
else
    echo "✗ PHP 版本过低 ($PHP_VER)，需要 8.3+"
    if [ -x /www/server/php/83/bin/php ]; then
        PHP_BIN=/www/server/php/83/bin/php
        echo "  → 切换到 $PHP_BIN"
    else
        echo "  请在宝塔面板安装 PHP 8.3"; exit 1
    fi
fi

# 自动解除 PHP 禁用函数
PHP_INI=$($PHP_BIN -r "echo php_ini_loaded_file();" 2>/dev/null)
CLI_INI="${PHP_INI/php.ini/php-cli.ini}"
PHP_INI_DIR=$(dirname "$PHP_INI")
if [ -d "$PHP_INI_DIR" ]; then
    NEED_FUNCS="putenv proc_open proc_get_status proc_close exec symlink"
    CHANGED=0
    for ini_file in "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php-cli.ini"; do
        [ -f "$ini_file" ] || continue
        for fn in $NEED_FUNCS; do
            if grep -q "$fn" "$ini_file" 2>/dev/null; then
                sed -i "s/,$fn//g; s/$fn,//g; s/$fn//g" "$ini_file"
                CHANGED=1
            fi
        done
    done
    if [ $CHANGED -eq 1 ]; then
        echo "✓ 已自动解除 PHP 禁用函数"
        /etc/init.d/php-fpm-83 restart 2>/dev/null || /etc/init.d/php-fpm-82 restart 2>/dev/null || true
    fi
fi

# 自动安装缺失的 PHP 扩展（宝塔方式）
MISSING_EXT=""
for ext in mbstring xml ctype iconv intl pdo_mysql bcmath gd fileinfo curl openssl; do
    if ! $PHP_BIN -m 2>/dev/null | grep -qi "^$ext$"; then
        MISSING_EXT="$MISSING_EXT $ext"
    fi
done
if [ -n "$MISSING_EXT" ]; then
    echo "⚠ 缺少扩展:$MISSING_EXT，尝试自动安装..."
    PHP_VER_NUM=$(echo $PHP_VER | tr -d '.')
    for ext in $MISSING_EXT; do
        # 宝塔 PHP 扩展安装路径
        EXT_SO="/www/server/php/${PHP_VER_NUM}/lib/php/extensions/no-debug-non-zts-*/${ext}.so"
        if ls $EXT_SO 1>/dev/null 2>&1; then
            echo "extension=$ext" >> "/www/server/php/${PHP_VER_NUM}/etc/php.ini"
            echo "  → 已启用 $ext"
        else
            echo "  ✗ 无法自动安装 $ext，请在宝塔面板 → PHP $PHP_VER → 安装扩展"
            exit 1
        fi
    done
    /etc/init.d/php-fpm-${PHP_VER_NUM} restart 2>/dev/null || true
fi
echo "✓ PHP 扩展完整"

# 自动安装/修复 Composer
COMPOSER_OK=0
if command -v composer &>/dev/null && composer --version &>/dev/null 2>&1; then
    COMPOSER_MAJOR=$(composer --version --no-ansi 2>/dev/null | grep -oP '[0-9]+' | head -1)
    [ "${COMPOSER_MAJOR:-0}" -ge 2 ] && COMPOSER_OK=1
fi
if [ $COMPOSER_OK -eq 0 ]; then
    echo "⚠ Composer 不可用或版本过低，自动安装..."
    curl -sS https://getcomposer.org/installer | $PHP_BIN -- --install-dir=/usr/local/bin --filename=composer
    [ -f /usr/bin/composer ] && rm -f /usr/bin/composer
    ln -sf /usr/local/bin/composer /usr/bin/composer
fi
echo "✓ Composer $(composer --version --no-ansi 2>/dev/null | grep -oP '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"

# ========== 首次部署 ==========
if [ ! -f .env ]; then
    echo ""
    echo ">>> 首次部署 - 初始化环境"
    composer install --no-dev --optimize-autoloader --no-interaction

    # 修复权限（Web 安装向导需要）
    chown -R www:www storage bootstrap/cache database
    chmod -R 775 storage bootstrap/cache database
    chown www:www "$APP_DIR" "$APP_DIR/.env" 2>/dev/null || true

    cp .env.example .env
    $PHP_BIN artisan key:generate --force

    echo ""
    echo "请选择安装方式："
    echo "  1) 命令行配置（当前终端交互）"
    echo "  2) Web 安装向导（浏览器访问 /install）"
    read -p "请选择 [1/2]: " INSTALL_MODE

    if [ "$INSTALL_MODE" = "2" ]; then
        # Web 向导模式：只需要知道域名来提示访问地址
        read -p "请输入站点域名 (如 example.com): " SITE_DOMAIN
        sed -i "s|APP_URL=.*|APP_URL=https://$SITE_DOMAIN|" .env
        echo ""
        echo "============================================"
        echo "  请在浏览器访问: https://$SITE_DOMAIN/install"
        echo "  通过 Web 向导完成安装配置"
        echo "  完成后再运行: bash deploy.sh"
        echo "============================================"
        exit 0
    fi

    # 命令行模式
    echo ""
    read -p "请输入站点域名 (如 example.com): " SITE_DOMAIN
    read -p "请输入数据库密码 (留空则自动生成): " DB_PASS
    [ -z "$DB_PASS" ] && DB_PASS=$(head -c 16 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 16)

    # 写入 .env
    sed -i "s|APP_URL=.*|APP_URL=https://$SITE_DOMAIN|" .env
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=cang_ai|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=cang_ai|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env

    # 自动创建 MySQL 数据库和用户
    if command -v mysql &>/dev/null; then
        mysql -uroot -e "CREATE DATABASE IF NOT EXISTS cang_ai DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
        mysql -uroot -e "CREATE USER IF NOT EXISTS 'cang_ai'@'localhost' IDENTIFIED BY '$DB_PASS';" 2>/dev/null
        mysql -uroot -e "GRANT ALL PRIVILEGES ON cang_ai.* TO 'cang_ai'@'localhost'; FLUSH PRIVILEGES;" 2>/dev/null
        echo "✓ 已自动创建数据库和用户 cang_ai"
    fi

    echo ""
    echo "┌─────────────────────────────────────────┐"
    echo "│  环境配置完成！                          │"
    echo "│  域名: https://$SITE_DOMAIN             │"
    echo "│  数据库: cang_ai / 密码: $DB_PASS       │"
    echo "│  如需配置 API 密钥请编辑 .env           │"
    echo "└─────────────────────────────────────────┘"
    echo ""
    read -p "是否继续部署？(Y/n): " CONTINUE
    [ "$CONTINUE" = "n" ] && exit 0
fi

# ========== 更新部署 ==========
echo ""
echo ">>> [1/7] 拉取最新代码"
timeout 30 git pull origin main || echo "⚠ git pull 超时，跳过"

echo ""
echo ">>> [2/7] 安装 PHP 依赖"
composer install --no-dev --optimize-autoloader --no-interaction

echo ""
echo ">>> [3/7] 执行数据库迁移"
$PHP_BIN artisan migrate --force

echo ""
echo ">>> [4/7] 缓存配置"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo ""
echo ">>> [5/7] 存储链接"
$PHP_BIN artisan storage:link 2>/dev/null || true

echo ""
echo ">>> [6/7] 修复权限"
chown -R www:www storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

echo ""
echo ">>> [7/7] 重启队列 Worker"
pkill -f "task:worker" 2>/dev/null || true
sleep 1
nohup $PHP_BIN artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
nohup $PHP_BIN artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &

echo ""
echo "============================================"
echo "  部署完成 ✓"
echo "  站点: $(grep APP_URL .env | cut -d= -f2)"
echo "  Worker PID: $(pgrep -f 'task:worker' | tr '\n' ' ')"
echo "============================================"
