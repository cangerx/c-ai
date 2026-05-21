#!/bin/bash
# CANG-AI 一键部署/更新脚本（前后端）
# 用法:
#   bash deploy.sh                 首次全栈部署
#   bash deploy.sh update          一键更新前后端
#   bash deploy.sh update-backend  仅更新后端
#   bash deploy.sh update-frontend 仅更新前端
#   bash deploy.sh nginx           仅生成 Nginx 配置
#   bash deploy.sh status          查看运行状态

set +e  # 脚本自行管理错误处理，不用 set -e

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
FRONTEND_DIR="${FRONTEND_DIR:-$(dirname "$APP_DIR")/cang-ai-web}"
FRONTEND_REPO="https://github.com/cangerx/cang-ai-web.git"
FRONTEND_PORT="${FRONTEND_PORT:-3000}"
ACTION="${1:-full}"

cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

echo "╔══════════════════════════════════════════╗"
echo "║  CANG-AI 部署脚本                        ║"
echo "║  后端: $APP_DIR"
echo "║  前端: $FRONTEND_DIR"
echo "║  操作: $ACTION"
echo "╚══════════════════════════════════════════╝"

fail() { echo ""; echo "  ✗ $1"; exit 1; }
DEPLOY_STASH_KEEP="${DEPLOY_STASH_KEEP:-5}"

cleanup_deploy_stashes() {
    if [ ! -d .git ]; then
        return 0
    fi

    local drops
    drops=$(git stash list 2>/dev/null \
        | grep 'deploy-auto-stash-' \
        | awk -F'[{ }]' "NR>${DEPLOY_STASH_KEEP}{print \$2}" \
        | sort -nr)

    if [ -n "$drops" ]; then
        echo "  → 清理旧自动备份，仅保留最近 ${DEPLOY_STASH_KEEP} 份"
        for idx in $drops; do
            git stash drop "stash@{${idx}}" >/dev/null 2>&1 || true
        done
    fi
}

file_sha256() {
    if command -v sha256sum &>/dev/null; then
        sha256sum "$1" 2>/dev/null | awk '{print $1}'
    elif command -v shasum &>/dev/null; then
        shasum -a 256 "$1" 2>/dev/null | awk '{print $1}'
    fi
}

install_composer_deps_if_needed() {
    local lock_hash_file="bootstrap/cache/composer.lock.sha256"
    local current_hash=""
    local saved_hash=""

    if [ -f composer.lock ]; then
        current_hash=$(file_sha256 composer.lock)
    fi
    if [ -f "$lock_hash_file" ]; then
        saved_hash=$(cat "$lock_hash_file" 2>/dev/null)
    fi

    if [ -f vendor/autoload.php ] && [ -n "$current_hash" ] && [ "$current_hash" = "$saved_hash" ]; then
        echo "  ✓ PHP 依赖未变化，跳过 Composer install"
        return 0
    fi

    "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction || fail "Composer install 失败"
    if [ -n "$current_hash" ]; then
        mkdir -p bootstrap/cache
        echo "$current_hash" > "$lock_hash_file"
    fi
}

run_git_update() {
    if [ ! -d .git ]; then
        echo "  ⚠ 当前目录不是 Git 仓库，跳过代码拉取"
        return 0
    fi

    git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

    local stash_name="deploy-auto-stash-$(date +%Y%m%d%H%M%S)"
    if ! git diff --quiet || ! git diff --cached --quiet || [ -n "$(git ls-files --others --exclude-standard)" ]; then
        echo "  ⚠ 检测到本地未提交改动，自动备份到 git stash: $stash_name"
        git stash push -u -m "$stash_name" >/dev/null || fail "本地改动备份失败，请手动执行 git status 检查"
    fi

    echo "  → fetch origin/main"
    timeout 60 git fetch origin main || fail "git fetch 失败，请检查网络或仓库权限"

    echo "  → pull --ff-only"
    timeout 60 git pull --ff-only origin main || fail "git pull 失败。已备份本地改动，请执行 git stash list 查看；如分支分叉请手动处理"
    cleanup_deploy_stashes
}

# ========== 智能环境检测与自动修复 ==========
echo ""
echo ">>> [环境检测] 自动检查并修复所有依赖"

ERRORS=0

# ---------- 1. 找到可用的 PHP ----------
PHP_BIN=""
for p in /www/server/php/83/bin/php /www/server/php/84/bin/php /www/server/php/82/bin/php $(which php 2>/dev/null); do
    if [ -x "$p" ] && "$p" -r "exit(version_compare(PHP_VERSION,'8.2.0','<')?1:0);" 2>/dev/null; then
        PHP_BIN="$p"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    fail "未找到 PHP 8.2+，请在宝塔面板 → 软件商店 → 安装 PHP 8.3"
fi

PHP_VER=$("$PHP_BIN" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
PHP_VER_NUM=$(echo "$PHP_VER" | tr -d '.')
echo "  PHP $PHP_VER → $PHP_BIN"

# ---------- 2. PHP 扩展：检测+自动修复 ----------
BT_PHP_DIR="/www/server/php/${PHP_VER_NUM}"
BT_PHP_INI="${BT_PHP_DIR}/etc/php.ini"
BT_PHP_CLI_INI="${BT_PHP_DIR}/etc/php-cli.ini"
BT_EXT_DIR=$(ls -d "${BT_PHP_DIR}"/lib/php/extensions/no-debug-non-zts-*/ 2>/dev/null | head -1)
IS_BT=0
[ -d "$BT_PHP_DIR" ] && IS_BT=1

# 用 php -r 检测扩展（同时检测编译内置和动态加载的）
check_ext() {
    "$PHP_BIN" -r "exit(extension_loaded('$1')?0:1);" 2>/dev/null
}

REQUIRED_EXTS="mbstring curl openssl pdo pdo_mysql xml ctype iconv tokenizer dom fileinfo bcmath gd intl pcntl"
MISSING=""

for ext in $REQUIRED_EXTS; do
    if ! check_ext "$ext"; then
        MISSING="$MISSING $ext"
    fi
done

if [ -n "$MISSING" ]; then
    echo "  ⚠ 缺少扩展:$MISSING"
    echo "  → 自动安装中..."

    # 宝塔扩展名映射（宝塔安装脚本里用的名称）
    bt_ext_name() {
        case "$1" in
            pdo_mysql) echo "pdo_mysql" ;;
            dom|xml)   echo "xml" ;;
            *)         echo "$1" ;;
        esac
    }

    # 宝塔 PHP 扩展安装方法
    bt_install_ext() {
        local ext="$1"
        local bt_name
        bt_name=$(bt_ext_name "$ext")
        local install_script="${BT_PHP_DIR}/src/ext/${bt_name}/install.sh"

        # 方法1: .so 已存在，只需在 php.ini 里启用
        if [ -n "$BT_EXT_DIR" ]; then
            for so_name in "$ext" "$bt_name"; do
                if [ -f "${BT_EXT_DIR}${so_name}.so" ]; then
                    for _ini in "$BT_PHP_INI" "$BT_PHP_CLI_INI"; do
                        [ -f "$_ini" ] || continue
                        if grep -qE "^;\s*extension\s*=\s*${so_name}" "$_ini" 2>/dev/null; then
                            sed -i "s/^;\s*extension\s*=\s*${so_name}/extension=${so_name}/" "$_ini"
                        elif ! grep -qE "^extension\s*=\s*${so_name}" "$_ini" 2>/dev/null; then
                            echo "extension=${so_name}" >> "$_ini"
                        fi
                    done
                    echo "    ✓ $ext → 启用 .so"
                    return 0
                fi
            done
        fi

        # 方法2: 宝塔 install.sh 脚本编译安装
        if [ -f "$install_script" ]; then
            echo "    → $ext: 编译安装中（可能需要 1-2 分钟）..."
            bash "$install_script" 2>/dev/null
            if check_ext "$ext"; then
                echo "    ✓ $ext → 编译安装成功"
                return 0
            fi
        fi

        # 方法3: 宝塔 API 安装（btpip/bt 命令行）
        if command -v bt &>/dev/null; then
            echo "    → $ext: 通过宝塔 CLI 安装..."
            bt 14 <<< "${PHP_VER_NUM}
${bt_name}
" 2>/dev/null
            if [ -n "$BT_EXT_DIR" ] && [ -f "${BT_EXT_DIR}${ext}.so" ]; then
                for _ini in "$BT_PHP_INI" "$BT_PHP_CLI_INI"; do
                    [ -f "$_ini" ] || continue
                    grep -qE "^extension\s*=\s*${ext}" "$_ini" 2>/dev/null || echo "extension=${ext}" >> "$_ini"
                done
                echo "    ✓ $ext → bt CLI 安装成功"
                return 0
            fi
        fi

        # 方法4: 宝塔 API HTTP 接口安装
        if [ -f /www/server/panel/BT-Panel ]; then
            local bt_panel_path="/www/server/panel"
            echo "    → $ext: 通过宝塔面板 API 安装..."
            cd "$bt_panel_path"
            python3 -c "
import sys
sys.path.insert(0, '.')
try:
    from plugin.php.php_main import phpMain
    p = phpMain()
    p.install_ext({'version': '${PHP_VER_NUM}', 'name': '${bt_name}'})
    print('OK')
except Exception as e:
    print(f'FAIL: {e}')
" 2>/dev/null | grep -q "OK" && {
                echo "    ✓ $ext → 面板 API 安装成功"
                cd "$APP_DIR"
                return 0
            }
            cd "$APP_DIR"
        fi

        # 方法5: phpize 手动编译（内置扩展）
        local php_src_dir="${BT_PHP_DIR}/src"
        local ext_src=""
        for d in "${php_src_dir}/ext/${ext}" "${php_src_dir}/ext/${bt_name}"; do
            [ -d "$d" ] && ext_src="$d" && break
        done

        if [ -n "$ext_src" ] && [ -x "${BT_PHP_DIR}/bin/phpize" ]; then
            echo "    → $ext: phpize 编译中..."
            cd "$ext_src"
            "${BT_PHP_DIR}/bin/phpize" 2>/dev/null
            ./configure --with-php-config="${BT_PHP_DIR}/bin/php-config" 2>/dev/null
            make -j$(nproc) 2>/dev/null && make install 2>/dev/null
            cd "$APP_DIR"
            if [ -n "$BT_EXT_DIR" ] && [ -f "${BT_EXT_DIR}${ext}.so" ]; then
                for _ini in "$BT_PHP_INI" "$BT_PHP_CLI_INI"; do
                    [ -f "$_ini" ] || continue
                    grep -qE "^extension\s*=\s*${ext}" "$_ini" 2>/dev/null || echo "extension=${ext}" >> "$_ini"
                done
                echo "    ✓ $ext → phpize 编译成功"
                return 0
            fi
        fi

        # 方法6: apt/yum 兜底（非宝塔环境）
        if [ $IS_BT -eq 0 ]; then
            local pkg=""
            case "$ext" in
                pdo_mysql) pkg="php${PHP_VER}-mysql" ;;
                dom|xml)   pkg="php${PHP_VER}-xml" ;;
                mbstring)  pkg="php${PHP_VER}-mbstring" ;;
                curl)      pkg="php${PHP_VER}-curl" ;;
                gd)        pkg="php${PHP_VER}-gd" ;;
                intl)      pkg="php${PHP_VER}-intl" ;;
                bcmath)    pkg="php${PHP_VER}-bcmath" ;;
            esac
            if [ -n "$pkg" ]; then
                if command -v apt-get &>/dev/null; then
                    apt-get install -y "$pkg" 2>/dev/null && return 0
                elif command -v yum &>/dev/null; then
                    yum install -y "php-${ext}" 2>/dev/null && return 0
                fi
            fi
        fi

        return 1
    }

    for ext in $MISSING; do
        bt_install_ext "$ext" || echo "    ⚠ $ext 安装失败"
    done

    # 重启 PHP-FPM 使扩展生效
    if [ $IS_BT -eq 1 ]; then
        /etc/init.d/php-fpm-"${PHP_VER_NUM}" restart 2>/dev/null || true
    else
        systemctl restart "php${PHP_VER}-fpm" 2>/dev/null || true
    fi

    # 最终验证
    FINAL_MISSING=""
    for ext in $REQUIRED_EXTS; do
        if ! check_ext "$ext"; then
            FINAL_MISSING="$FINAL_MISSING $ext"
        fi
    done

    if [ -n "$FINAL_MISSING" ]; then
        echo ""
        echo "  ⚠ 以下扩展仍缺失:$FINAL_MISSING"
        echo "    最后手段: 宝塔面板 → 软件商店 → PHP $PHP_VER → 设置 → 安装扩展"
        echo "    然后重新运行: bash deploy.sh update"
        ERRORS=1
    else
        echo "  ✓ 所有扩展安装成功"
    fi
else
    echo "  ✓ PHP 扩展完整"
fi

# ---------- 3. 解除 PHP 禁用函数 ----------
NEED_FUNCS="putenv proc_open proc_get_status proc_close exec symlink pcntl_signal pcntl_alarm"
PHP_INI_DIR=$(dirname "$("$PHP_BIN" -r "echo php_ini_loaded_file();" 2>/dev/null)")
REMOVED_FUNCS=""

if [ -d "$PHP_INI_DIR" ]; then
    for ini_file in "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php-cli.ini"; do
        [ -f "$ini_file" ] || continue
        DISABLE_LINE=$(grep -n "^disable_functions" "$ini_file" 2>/dev/null | head -1 | cut -d: -f1)
        [ -z "$DISABLE_LINE" ] && continue

        CURRENT=$(sed -n "${DISABLE_LINE}p" "$ini_file")
        CHANGED="$CURRENT"
        for fn in $NEED_FUNCS; do
            # 精确匹配整个函数名，不误伤子串
            if echo "$CHANGED" | grep -qE "(,|=)\s*${fn}\s*(,|$)"; then
                CHANGED=$(echo "$CHANGED" | sed -E "s/,\s*${fn}\s*//g; s/${fn}\s*,//g; s/=\s*${fn}\s*$/=/")
                REMOVED_FUNCS="$REMOVED_FUNCS $fn"
            fi
        done
        if [ "$CHANGED" != "$CURRENT" ]; then
            sed -i "${DISABLE_LINE}s|.*|${CHANGED}|" "$ini_file"
        fi
    done
fi

if [ -n "$REMOVED_FUNCS" ]; then
    echo "  ✓ 已解除禁用函数:$REMOVED_FUNCS"
    /etc/init.d/php-fpm-"${PHP_VER_NUM}" restart 2>/dev/null || true
else
    echo "  ✓ 禁用函数无需修复"
fi

# ---------- 4. Composer ----------
COMPOSER_BIN=""
for c in /usr/local/bin/composer /usr/bin/composer $(which composer 2>/dev/null); do
    if [ -x "$c" ] && "$PHP_BIN" "$c" --version &>/dev/null 2>&1; then
        COMPOSER_BIN="$c"
        break
    fi
done

if [ -z "$COMPOSER_BIN" ]; then
    echo "  ⚠ Composer 不可用，自动安装..."
    curl -sS https://getcomposer.org/installer | "$PHP_BIN" -- --install-dir=/usr/local/bin --filename=composer 2>/dev/null
    COMPOSER_BIN="/usr/local/bin/composer"
    [ ! -f /usr/bin/composer ] && ln -sf "$COMPOSER_BIN" /usr/bin/composer
fi
COMPOSER_VER=$("$PHP_BIN" "$COMPOSER_BIN" --version --no-ansi 2>/dev/null | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+' | head -1)
echo "  ✓ Composer ${COMPOSER_VER:-installed}"

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

# ---------- 8. Node.js ----------
NODE_BIN=""
NPM_BIN=""
PM2_BIN=""
BT_NODE_CANDIDATES=$(ls -d /www/server/nodejs/v*/bin/node 2>/dev/null | sort -V -r)
for n in $BT_NODE_CANDIDATES $(which node 2>/dev/null) /usr/local/bin/node /usr/bin/node; do
    if [ -x "$n" ] && "$n" -e "process.exit(parseInt(process.version.slice(1))<18?1:0)" 2>/dev/null; then
        NODE_BIN="$n"
        break
    fi
done

if [ -n "$NODE_BIN" ]; then
    NODE_DIR="$(dirname "$NODE_BIN")"
    NPM_BIN="${NODE_DIR}/npm"
    [ ! -x "$NPM_BIN" ] && NPM_BIN=$(which npm 2>/dev/null || echo "")
    NODE_VER=$("$NODE_BIN" -v 2>/dev/null)
    echo "  ✓ Node.js $NODE_VER → $NODE_BIN"

    # 关键：将 Node 目录加入 PATH（全局生效，解决 npm 子进程找不到 node 的问题）
    export PATH="${NODE_DIR}:$PATH"
    # 创建符号链接到 /usr/local/bin（永久解决 PATH 问题）
    for bin_name in node npm npx; do
        if [ -x "${NODE_DIR}/${bin_name}" ] && [ ! -e "/usr/local/bin/${bin_name}" ]; then
            ln -sf "${NODE_DIR}/${bin_name}" "/usr/local/bin/${bin_name}" 2>/dev/null || true
        fi
    done
else
    echo "  ⚠ Node.js 18+ 未找到（前端部署需要）"
    echo "    宝塔面板 → 网站 → Node 项目 → 安装 Node.js 20"
fi

# ---------- 9. PM2 ----------
if [ -n "$NODE_BIN" ] && [ -n "$NPM_BIN" ]; then
    for pm in "${NODE_DIR}/pm2" $(which pm2 2>/dev/null) "${NODE_DIR}/../lib/node_modules/pm2/bin/pm2"; do
        [ -x "$pm" ] && PM2_BIN="$pm" && break
    done
    if [ -z "$PM2_BIN" ]; then
        echo "  → 安装 PM2..."
        "$NPM_BIN" install -g pm2 2>/dev/null || true
        # 重新查找
        for pm in "${NODE_DIR}/pm2" $(which pm2 2>/dev/null) "${NODE_DIR}/../lib/node_modules/pm2/bin/pm2"; do
            [ -x "$pm" ] && PM2_BIN="$pm" && break
        done
    fi
    if [ -n "$PM2_BIN" ] && [ -x "$PM2_BIN" ]; then
        echo "  ✓ PM2 就绪 → $PM2_BIN"
        [ ! -e /usr/local/bin/pm2 ] && ln -sf "$PM2_BIN" /usr/local/bin/pm2 2>/dev/null || true
    else
        echo "  ⚠ PM2 安装失败，前端需手动启动"
        PM2_BIN=""
    fi
fi

echo ""

# ========== 状态查看 ==========
show_status() {
    echo ">>> 运行状态"
    echo ""
    echo "── 后端 ──"
    echo "  目录: $APP_DIR"
    [ -f .env ] && echo "  APP_URL: $(grep ^APP_URL .env | cut -d= -f2)"
    [ -f .env ] && echo "  APP_ENV: $(grep ^APP_ENV .env | cut -d= -f2)"
    echo "  Worker: $(pgrep -f 'task:worker' | wc -l | tr -d ' ') 进程"
    echo ""
    echo "── 前端 ──"
    echo "  目录: $FRONTEND_DIR"
    [ -d "$FRONTEND_DIR" ] && echo "  存在: ✓" || echo "  存在: ✗"
    if [ -n "$PM2_BIN" ] && "$PM2_BIN" list 2>/dev/null | grep -q "cang-ai-web"; then
        echo "  PM2 状态:"
        "$PM2_BIN" show cang-ai-web 2>/dev/null | grep -E "status|uptime|restart|memory" | head -5
    else
        echo "  PM2: 未运行"
    fi
    echo ""
    if [ -n "$PM2_BIN" ]; then
        echo "── PM2 全局 ──"
        "$PM2_BIN" list 2>/dev/null
    fi
}

if [ "$ACTION" = "status" ]; then
    show_status
    exit 0
fi

# ========== Nginx 配置生成 ==========
generate_nginx() {
    local DOMAIN="${SITE_DOMAIN:-}"
    if [ -z "$DOMAIN" ] && [ -f .env ]; then
        DOMAIN=$(grep ^APP_URL .env | cut -d= -f2 | sed 's|https\?://||')
    fi
    if [ -z "$DOMAIN" ]; then
        read -p "请输入站点域名 (如 ai.example.com): " DOMAIN
    fi
    [ -z "$DOMAIN" ] && fail "域名不能为空"

    local PHP_SOCK="/tmp/php-cgi-${PHP_VER_NUM}.sock"
    [ ! -e "$PHP_SOCK" ] && PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

    local CONF_FILE="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
    [ ! -d "$(dirname "$CONF_FILE")" ] && CONF_FILE="/etc/nginx/sites-available/${DOMAIN}.conf"

    cat > /tmp/cang-ai-nginx.conf << NGINX_EOF
# CANG-AI 前后端统一配置 - 自动生成于 $(date +%Y-%m-%d)
# 域名: $DOMAIN
# 后端: $APP_DIR/public
# 前端: http://127.0.0.1:$FRONTEND_PORT

server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    # ── SSL（宝塔会自动补充，或手动取消注释） ──
    # listen 443 ssl http2;
    # listen [::]:443 ssl http2;
    # ssl_certificate    /www/server/panel/vhost/cert/$DOMAIN/fullchain.pem;
    # ssl_certificate_key /www/server/panel/vhost/cert/$DOMAIN/privkey.pem;
    # ssl_protocols TLSv1.2 TLSv1.3;

    # ── 通用 ──
    client_max_body_size 50m;
    access_log /www/wwwlogs/${DOMAIN}.log;
    error_log  /www/wwwlogs/${DOMAIN}.error.log;

    # ── 后端 Laravel（API + 后台 + 资源） ──
    location ~ ^/(api|admin|agent|livewire|filament|install|up|sanctum) {
        root $APP_DIR/public;
        try_files \$uri \$uri/ /index.php?\$query_string;

        location ~ \\.php\$ {
            fastcgi_pass unix:$PHP_SOCK;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
    }

    # ── 后端静态资源 ──
    location ~ ^/(storage|images|css/filament|js/filament|vendor) {
        root $APP_DIR/public;
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # ── 静态页面 ──
    location ~ ^/(privacy|terms)\\.html\$ {
        root $APP_DIR/public;
        expires 1d;
    }
    location = /robots.txt {
        root $APP_DIR/public;
    }
    location = /favicon.ico {
        root $APP_DIR/public;
        log_not_found off;
    }

    # ── 前端 Next.js ──
    location / {
        proxy_pass http://127.0.0.1:$FRONTEND_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_connect_timeout 60;
        proxy_read_timeout 300;
    }

    # ── 禁止访问隐藏文件 ──
    location ~ /\\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
NGINX_EOF

    echo ""
    echo "  Nginx 配置已生成: /tmp/cang-ai-nginx.conf"
    echo ""

    if [ -d "$(dirname "$CONF_FILE")" ]; then
        read -p "  是否写入到 $CONF_FILE？(y/N): " WRITE_CONF
        if [ "$WRITE_CONF" = "y" ] || [ "$WRITE_CONF" = "Y" ]; then
            [ -f "$CONF_FILE" ] && cp "$CONF_FILE" "${CONF_FILE}.bak.$(date +%Y%m%d%H%M%S)"
            cp /tmp/cang-ai-nginx.conf "$CONF_FILE"
            nginx -t 2>&1 && nginx -s reload && echo "  ✓ Nginx 配置已生效" || echo "  ⚠ Nginx 配置有误，已回滚请检查"
        fi
    else
        echo "  请手动将 /tmp/cang-ai-nginx.conf 内容复制到 Nginx 站点配置中"
    fi
}

if [ "$ACTION" = "nginx" ]; then
    generate_nginx
    exit 0
fi

# ========== 前端部署函数 ==========
# 支持两种模式:
#   1. standalone 模式（推荐）: 本地构建后 rsync 上传，服务器只需 node
#   2. 服务器构建模式: 服务器上 npm install + build
deploy_frontend() {
    echo ""
    echo "━━━ 前端部署 ━━━"

    if [ -z "$NODE_BIN" ]; then
        echo "  ⚠ 跳过前端：Node.js 未安装"
        return 0
    fi

    # 检测是否已有 standalone 构建产物（由本地 deploy-frontend.sh 上传）
    if [ -f "$FRONTEND_DIR/server.js" ]; then
        echo "  ✓ 检测到 standalone 产物，直接启动"
        start_frontend_standalone
        return 0
    fi

    # 检测是否有 npm（服务器构建模式需要）
    if [ -z "$NPM_BIN" ]; then
        echo "  ⚠ npm 不可用，请使用本地构建模式:"
        echo "    本地执行: bash deploy-frontend.sh root@服务器IP"
        return 0
    fi

    # Clone 或 Pull
    if [ ! -d "$FRONTEND_DIR" ]; then
        echo "  → 首次克隆前端仓库..."
        git clone "$FRONTEND_REPO" "$FRONTEND_DIR" || fail "前端仓库克隆失败"
    else
        echo "  → 拉取前端最新代码..."
        cd "$FRONTEND_DIR"
        git config --global --add safe.directory "$FRONTEND_DIR" 2>/dev/null || true
        git fetch origin main 2>/dev/null
        git reset --hard origin/main 2>/dev/null || true
    fi

    cd "$FRONTEND_DIR"

    # 环境文件
    local BACKEND_URL="http://127.0.0.1:8000"
    if [ -f "$APP_DIR/.env" ]; then
        local APP_URL=$(grep ^APP_URL "$APP_DIR/.env" | cut -d= -f2)
        [ -n "$APP_URL" ] && BACKEND_URL="$APP_URL"
    fi
    echo "NEXT_PUBLIC_API_URL=$BACKEND_URL" > .env.local
    echo "  ✓ .env.local → NEXT_PUBLIC_API_URL=$BACKEND_URL"

    # npm install
    echo "  → npm install..."
    "$NPM_BIN" install 2>&1 | tail -3
    if [ $? -ne 0 ]; then
        echo "  ⚠ npm install 失败，尝试清除缓存重试..."
        rm -rf node_modules package-lock.json
        "$NPM_BIN" install || fail "npm install 失败，建议使用本地构建: bash deploy-frontend.sh"
    fi

    # 构建
    echo "  → npm run build（需要几分钟）..."
    NODE_OPTIONS="--max-old-space-size=1024" "$NPM_BIN" run build || fail "前端构建失败"

    # standalone 产物检测
    if [ -f ".next/standalone/server.js" ]; then
        echo "  → 使用 standalone 模式启动"
        cp -r .next/static .next/standalone/.next/static 2>/dev/null || true
        [ -d public ] && cp -r public .next/standalone/public 2>/dev/null || true
        start_frontend_standalone_from ".next/standalone"
    else
        start_frontend_npm
    fi

    cd "$APP_DIR"
}

# 启动 standalone server.js
start_frontend_standalone() {
    cd "$FRONTEND_DIR"
    # 写启动脚本
    cat > "$FRONTEND_DIR/start.sh" << 'STARTEOF'
#!/bin/bash
export PORT=${PORT:-3000}
export HOSTNAME=0.0.0.0
exec node server.js
STARTEOF
    chmod +x "$FRONTEND_DIR/start.sh"

    if [ -n "$PM2_BIN" ]; then
        "$PM2_BIN" delete cang-ai-web 2>/dev/null || true
        PORT="$FRONTEND_PORT" "$PM2_BIN" start "$FRONTEND_DIR/start.sh" --name "cang-ai-web" --cwd "$FRONTEND_DIR"
        "$PM2_BIN" save 2>/dev/null || true
        echo "  ✓ 前端 standalone 运行在 :$FRONTEND_PORT"
    else
        echo "  手动启动: cd $FRONTEND_DIR && PORT=$FRONTEND_PORT bash start.sh"
    fi
}

# 从构建目录启动 standalone
start_frontend_standalone_from() {
    local BUILD_DIR="$1"
    # 复制到前端根目录
    cp "$BUILD_DIR/server.js" "$FRONTEND_DIR/server.js" 2>/dev/null || true
    [ -d "$BUILD_DIR/.next" ] && cp -r "$BUILD_DIR/.next" "$FRONTEND_DIR/" 2>/dev/null || true
    [ -d "$BUILD_DIR/node_modules" ] && cp -r "$BUILD_DIR/node_modules" "$FRONTEND_DIR/" 2>/dev/null || true
    start_frontend_standalone
}

# npm start 模式启动
start_frontend_npm() {
    if [ -n "$PM2_BIN" ]; then
        if "$PM2_BIN" list 2>/dev/null | grep -q "cang-ai-web"; then
            "$PM2_BIN" restart cang-ai-web
        else
            cd "$FRONTEND_DIR"
            "$PM2_BIN" start "$NPM_BIN" --name "cang-ai-web" --cwd "$FRONTEND_DIR" -- start -- -p "$FRONTEND_PORT"
        fi
        "$PM2_BIN" save 2>/dev/null || true
        echo "  ✓ 前端运行在 :$FRONTEND_PORT"
    else
        echo "  手动启动: cd $FRONTEND_DIR && npm start -- -p $FRONTEND_PORT"
    fi
}

# ========== 后端部署函数 ==========
deploy_backend() {
    echo ""
    echo "━━━ 后端部署 ━━━"
    cd "$APP_DIR"

    echo "  [1/6] 拉取最新代码"
    run_git_update

    echo "  [2/6] 安装 PHP 依赖"
    install_composer_deps_if_needed

    echo "  [3/6] 执行数据库迁移"
    "$PHP_BIN" artisan migrate --force

    echo "  [4/6] 缓存配置"
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:clear 2>/dev/null || true
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache

    echo "  [5/6] 存储链接+权限"
    if [ ! -L public/storage ] && [ ! -e public/storage ]; then
        "$PHP_BIN" artisan storage:link 2>/dev/null || true
    fi
    chown -R www:www storage bootstrap/cache database 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache database 2>/dev/null || true

    echo "  [6/6] 重启 Worker"
    if [ -n "$PM2_BIN" ]; then
        if "$PM2_BIN" list 2>/dev/null | grep -q "cang-ai-worker"; then
            "$PM2_BIN" restart cang-ai-worker
        else
            "$PM2_BIN" start "$PHP_BIN" --name "cang-ai-worker" -- artisan task:worker --max-retries=3
            "$PM2_BIN" start "$PHP_BIN" --name "cang-ai-worker-2" -- artisan task:worker --max-retries=3
        fi
        "$PM2_BIN" save 2>/dev/null || true
    else
        pkill -f "task:worker" 2>/dev/null || true
        sleep 1
        nohup "$PHP_BIN" artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
        nohup "$PHP_BIN" artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
    fi
    echo "  ✓ 后端部署完成"
}

# ========== 首次部署 ==========
if [ ! -f .env ]; then
    echo ">>> 首次部署 - 初始化环境"
    install_composer_deps_if_needed

    if [ ! -f .env.example ]; then
        fail ".env.example 文件不存在，代码不完整，请检查 git clone 是否正确"
    fi
    cp .env.example .env
    "$PHP_BIN" artisan key:generate --force
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

# ========== 按 ACTION 执行 ==========
case "$ACTION" in
    update-backend)
        deploy_backend
        ;;
    update-frontend)
        deploy_frontend
        ;;
    update|full)
        deploy_backend
        deploy_frontend
        if [ "$ACTION" = "full" ] && [ ! -f /tmp/cang-ai-nginx.conf ]; then
            echo ""
            read -p "是否生成 Nginx 配置？(y/N): " GEN_NGINX
            [ "$GEN_NGINX" = "y" ] || [ "$GEN_NGINX" = "Y" ] && generate_nginx
        fi
        ;;
    *)
        echo "未知操作: $ACTION"
        echo "用法: bash deploy.sh [update|update-backend|update-frontend|nginx|status]"
        exit 1
        ;;
esac

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║  部署完成 ✓"
[ -f .env ] && echo "║  站点: $(grep ^APP_URL .env | cut -d= -f2)"
echo "║  Worker: $(pgrep -f 'task:worker' 2>/dev/null | wc -l | tr -d ' ') 进程"
[ -n "$PM2_BIN" ] && echo "║  PM2: $("$PM2_BIN" list 2>/dev/null | grep -c 'online' || echo 0) 在线"
echo "╚══════════════════════════════════════════╝"
