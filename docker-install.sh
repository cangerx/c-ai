#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
cd "$APP_DIR"

usage() {
  cat <<'EOF'
CANG-AI Docker 一键部署

用法:
  bash docker-install.sh              首次部署或启动
  bash docker-install.sh update       更新并重建服务
  bash docker-install.sh status       查看服务状态
  bash docker-install.sh logs         查看应用日志

可选环境变量:
  APP_URL=https://你的域名
  HTTP_PORT=127.0.0.1:8080
EOF
}

fail() {
  echo "错误: $1" >&2
  exit 1
}

random_secret() {
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -base64 32 | tr -d '\n'
  else
    date +%s%N | sha256sum | awk '{print $1}'
  fi
}

set_env() {
  local key="$1"
  local value="$2"
  local escaped
  escaped=$(printf '%s' "$value" | sed 's/[\/&]/\\&/g')

  if grep -q "^${key}=" .env; then
    sed -i.bak "s/^${key}=.*/${key}=${escaped}/" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
  rm -f .env.bak
}

get_env() {
  local key="$1"
  grep "^${key}=" .env 2>/dev/null | tail -1 | cut -d= -f2-
}

set_env_if_empty() {
  local key="$1"
  local value="$2"
  local current
  current="$(get_env "$key")"

  if [ -z "$current" ] || [ "$current" = "change-root-password" ] || [ "$current" = "change-db-password" ] || [ "$current" = "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" ]; then
    set_env "$key" "$value"
  fi
}

require_docker() {
  command -v docker >/dev/null 2>&1 || fail "未找到 docker，请先在宝塔软件商店安装 Docker"
  docker compose version >/dev/null 2>&1 || fail "未找到 docker compose，请确认 Docker Compose 插件已安装"
}

prepare_env() {
  if [ ! -f .env ]; then
    cp .env.docker.example .env
  fi

  local app_url="${APP_URL:-}"
  if [ -z "$app_url" ]; then
    app_url=$(grep '^APP_URL=' .env | cut -d= -f2- || true)
    [ -n "$app_url" ] || app_url="https://example.com"
  fi

  set_env APP_NAME "CANG-AI"
  set_env APP_ENV "production"
  set_env APP_DEBUG "false"
  set_env APP_URL "$app_url"
  set_env_if_empty APP_KEY "base64:$(random_secret)"
  set_env HTTP_PORT "${HTTP_PORT:-127.0.0.1:8080}"
  set_env RUN_MIGRATIONS "false"
  set_env INSTALL_PRECONFIGURED "true"

  set_env_if_empty MYSQL_ROOT_PASSWORD "$(random_secret)"
  set_env DB_CONNECTION "mysql"
  set_env DB_HOST "mysql"
  set_env DB_PORT "3306"
  set_env DB_SOCKET ""
  set_env DB_DATABASE "cang_ai"
  set_env DB_USERNAME "cang_ai"
  set_env_if_empty DB_PASSWORD "$(random_secret)"

  set_env REDIS_CLIENT "phpredis"
  set_env REDIS_HOST "redis"
  set_env REDIS_PORT "6379"
  set_env REDIS_PASSWORD "null"
  set_env CACHE_STORE "redis"
  set_env QUEUE_CONNECTION "redis"
  set_env SESSION_DRIVER "database"

  mkdir -p storage bootstrap/cache docker-data/mysql docker-data/redis
  chmod 664 .env 2>/dev/null || true
  chown -R 33:33 .env storage bootstrap/cache 2>/dev/null || true
}

start_stack() {
  docker compose build
  docker compose up -d mysql redis
  docker compose up -d api nginx worker scheduler
}

print_next_steps() {
  local port
  port=$(grep '^HTTP_PORT=' .env | cut -d= -f2-)
  cat <<EOF

部署已启动。

宝塔反向代理目标:
  http://127.0.0.1:${port##*:}

首次访问你的域名会进入安装向导，只需要填写站点名称和管理员账号。

常用命令:
  docker compose ps
  docker compose logs -f api
  docker compose logs -f worker
EOF
}

ACTION="${1:-install}"

case "$ACTION" in
  install|full)
    require_docker
    prepare_env
    start_stack
    print_next_steps
    ;;
  update)
    require_docker
    git pull origin main || true
    docker compose build api nginx
    docker compose up -d api nginx worker scheduler
    docker compose exec api php artisan migrate --force
    docker compose exec api php artisan app:optimize
    docker compose restart worker scheduler
    ;;
  status)
    require_docker
    docker compose ps
    ;;
  logs)
    require_docker
    docker compose logs -f api worker
    ;;
  -h|--help|help)
    usage
    ;;
  *)
    usage
    fail "未知操作: $ACTION"
    ;;
esac
