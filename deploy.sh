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

# ========== 智能环境检测与自动修复 ==========
echo ""
echo ">>> [环境检测] 自动检查并修复所有依赖"

ERRORS=0

# ---------- 1. 找到可用的 PHP ----------
PHP_BIN=""
for p in /www/server/php/83/bin/php /www/server/php/84/bin/php /www/server/php/82/bin/php $(which php 2>/dev/null); do
    if [ -x "$p" ] && $p -r "exit(version_compare(PHP_VERSION,'8.2.0','<')?1:0);" 2>/dev/null; then
        PHP_BIN="$p"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    echo "  ✗ 未找到 PHP 8.2+，请在宝塔面板 → 软件商店 → 安装 PHP 8.3"
    exit 1
fi

PHP_VER=$($PHP_BIN -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_VER_NUM=$(echo $PHP_VER | tr -d '.')
echo "  PHP $PHP_VER → $PHP_BIN"

# ---------- 2. PHP 扩展：检测+自动修复 ----------
REQUIRED_EXTS="mbstring curl openssl pdo pdo_mysql xml ctype iconv tokenizer dom fileinfo bcmath gd intl pcntl"
MISSING=""

for ext in $REQUIRED_EXTS; do
    if ! $PHP_BIN -m 2>/dev/null | grep -qi "^${ext}$"; then
        MISSING="$MISSING $ext"
    fi
done

if [ -n "$MISSING" ]; then
    echo "  ⚠ 缺少扩展:$MISSING"
    echo "  → 尝试自动修复..."

    BT_PHP_DIR="/www/server/php/${PHP_VER_NUM}"
    BT_PHP_INI="${BT_PHP_DIR}/etc/php.ini"
    BT_EXT_DIR=$(ls -d ${BT_PHP_DIR}/lib/php/extensions/no-debug-non-zts-*/ 2>/dev/null | head -1)

    for ext in $MISSING; do
        FIXED=0

        # 方法1: .so 已存在但未启用 → 写 php.ini
        if [ -n "$BT_EXT_DIR" ] && [ -f "${BT_EXT_DIR}${ext}.so" ]; then
            if [ -f "$BT_PHP_INI" ] && ! grep -q "^extension=${ext}" "$BT_PHP_INI"; then
                echo "extension=${ext}" >> "$BT_PHP_INI"
                echo "    ✓ 已启用 $ext (.so 已存在)"
                FIXED=1
            fi
        fi

        # 方法2: apt/yum 安装
        if [ $FIXED -eq 0 ]; then
            if command -v apt-get &>/dev/null; then
                apt-get install -y "php${PHP_VER}-${ext}" 2>/dev/null && FIXED=1
            elif command -v yum &>/dev/null; then
                yum install -y "php-${ext}" 2>/dev/null && FIXED=1
            fi
            [ $FIXED -eq 1 ] && echo "    ✓ 系统包管理安装 $ext 成功"
        fi

        [ $FIXED -eq 0 ] && echo "    ⚠ $ext 需手动安装"
    done

    # 重启 PHP-FPM 使扩展生效
    /etc/init.d/php-fpm-${PHP_VER_NUM} restart 2>/dev/null \
        || systemctl restart "php${PHP_VER}-fpm" 2>/dev/null \
        || true

    # 重新验证
    FINAL_MISSING=""
    for ext in $REQUIRED_EXTS; do
        if ! $PHP_BIN -m 2>/dev/null | grep -qi "^${ext}$"; then
            FINAL_MISSING="$FINAL_MISSING $ext"
        fi
    done

    if [ -n "$FINAL_MISSING" ]; then
        echo ""
        echo "  ✗ 以下扩展无法自动安装:$FINAL_MISSING"
        echo "    请手动操作: 宝塔面板 → 软件商店 → PHP $PHP_VER → 安装扩展"
        echo "    勾选:$FINAL_MISSING"
        ERRORS=1
    fi
fi

[ $ERRORS -eq 0 ] && echo "  ✓ PHP 扩展完整"

# ---------- 3. 解除 PHP 禁用函数 ----------
NEED_FUNCS="putenv proc_open proc_get_status proc_close exec symlink pcntl_signal pcntl_alarm"
PHP_INI_DIR=$(dirname "$($PHP_BIN -r "echo php_ini_loaded_file();" 2>/dev/null)")
REMOVED_FUNCS=""

if [ -d "$PHP_INI_DIR" ]; then
    for ini_file in "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php-cli.ini"; do
        [ -f "$ini_file" ] || continue
        for fn in $NEED_FUNCS; do
            if grep -q "disable_functions.*$fn" "$ini_file" 2>/dev/null; then
                sed -i "s/,$fn//g; s/$fn,//g; s/$fn//g" "$ini_file"
                REMOVED_FUNCS="$REMOVED_FUNCS $fn"
            fi
        done
    done
fi

if [ -n "$REMOVED_FUNCS" ]; then
    echo "  ✓ 已解除禁用函数:$REMOVED_FUNCS"
    /etc/init.d/php-fpm-${PHP_VER_NUM} restart 2>/dev/null || true
else
    echo "  ✓ 禁用函数无需修复"
fi

# ---------- 4. Composer ----------
COMPOSER_BIN=""
for c in /usr/local/bin/composer /usr/bin/composer $(which composer 2>/dev/null); do
    if [ -x "$c" ] && $PHP_BIN "$c" --version &>/dev/null 2>&1; then
        COMPOSER_BIN="$c"
        break
    fi
done

if [ -z "$COMPOSER_BIN" ]; then
    echo "  ⚠ Composer 不可用，自动安装..."
    curl -sS https://getcomposer.org/installer | $PHP_BIN -- --install-dir=/usr/local/bin --filename=composer 2>/dev/null
    COMPOSER_BIN="/usr/local/bin/composer"
    [ ! -f /usr/bin/composer ] && ln -sf "$COMPOSER_BIN" /usr/bin/composer
fi
echo "  ✓ Composer $($PHP_BIN "$COMPOSER_BIN" --version --no-ansi 2>/dev/null | grep -oP '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"

# ---------- 5. MySQL ----------
if command -v mysql &>/dev/null; then
    echo "  ✓ MySQL 可用"
else
    echo "  ⚠ MySQL 未检测到，请确保已安装并在 .env 配置好数据库连接"
fi

# ---------- 6. Redis ----------
if command -v redis-cli &>/dev/null && redis-cli ping 2>/dev/null | grep -q PONG; then
    echo "  ✓ Redis 运行中"
else
    echo "  ⚠ Redis 未运行，请在宝塔面板安装并启动 Redis"
fi

# ---------- 7. 目录权限 ----------
for dir in storage storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database; do
    [ -d "$dir" ] || mkdir -p "$dir"
done
chown -R www:www storage bootstrap/cache database 2>/dev/null || true
chmod -R 775 storage bootstrap/cache database 2>/dev/null || true
echo "  ✓ 目录权限已修复"

# ---------- 检测结果 ----------
if [ $ERRORS -ne 0 ]; then
    echo ""
    echo "============================================"
    echo "  ✗ 环境有问题，请按上方提示修复后重新运行"
    echo "============================================"
    exit 1
fi

echo ""
echo "  ✓ 环境检测全部通过"
echo ""

# ========== 首次部署 ==========
if [ ! -f .env ]; then
    echo ">>> 首次部署 - 初始化环境"
    $PHP_BIN "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

    cp .env.example .env
    $PHP_BIN artisan key:generate --force
    chown www:www .env 2>/dev/null || true

    echo ""
    echo "请选择安装方式："
    echo "  1) 命令行配置（当前终端交互）"
    echo "  2) Web 安装向导（浏览器访问 /install）"
    read -p "请选择 [1/2]: " INSTALL_MODE

    if [ "$INSTALL_MODE" = "2" ]; then
        read -p "请输入站点域名 (如 example.com): " SITE_DOMAIN
        sed -i "s|APP_URL=.*|APP_URL=https://$SITE_DOMAIN|" .env
        chown -R www:www storage bootstrap/cache database
        chmod -R 775 storage bootstrap/cache database
        echo ""
        echo "============================================"
        echo "  请在浏览器访问: https://$SITE_DOMAIN/install"
        echo "  通过 Web 向导完成安装配置"
        echo "  完成后再运行: bash deploy.sh"
        echo "============================================"
        exit 0
    fi

    echo ""
    read -p "请输入站点域名 (如 example.com): " SITE_DOMAIN
    read -p "请输入数据库密码 (留空则自动生成): " DB_PASS
    [ -z "$DB_PASS" ] && DB_PASS=$(head -c 16 /dev/urandom | base64 | tr -dc 'a-zA-Z0-9' | head -c 16)

    sed -i "s|APP_URL=.*|APP_URL=https://$SITE_DOMAIN|" .env
    sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=cang_ai|" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=cang_ai|" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env

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
echo ">>> [1/7] 拉取最新代码"
timeout 30 git pull origin main || echo "⚠ git pull 超时，跳过"

echo ""
echo ">>> [2/7] 安装 PHP 依赖"
$PHP_BIN "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

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
