#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")" && pwd)}"
cd "$APP_DIR"

IMAGE_TAG="${IMAGE_TAG:-latest}"
IMAGE_OWNER="${IMAGE_OWNER:-cangerx}"
REGISTRY="${REGISTRY:-ghcr.io}"
HTTP_PORT="${HTTP_PORT:-$(grep '^HTTP_PORT=' .env 2>/dev/null | tail -1 | cut -d= -f2- || true)}"
FRONTEND_PORT="${FRONTEND_PORT:-$(grep '^FRONTEND_PORT=' .env 2>/dev/null | tail -1 | cut -d= -f2- || true)}"
export IMAGE_TAG IMAGE_OWNER REGISTRY

fail() {
  echo "错误: $1" >&2
  exit 1
}

compose() {
  docker compose -f docker-compose.ghcr.yml "$@"
}

command -v docker >/dev/null 2>&1 || fail "未找到 docker"
command -v curl >/dev/null 2>&1 || fail "未找到 curl"
docker compose version >/dev/null 2>&1 || fail "未找到 docker compose"
[ -f .env ] || fail ".env 不存在，请先完成初始化部署"
[ -f docker-compose.ghcr.yml ] || fail "docker-compose.ghcr.yml 不存在，请先更新代码"
[ -d storage ] || mkdir -p storage
[ -d bootstrap/cache ] || mkdir -p bootstrap/cache

echo "Using images: ${REGISTRY}/${IMAGE_OWNER}/cang-ai-*:${IMAGE_TAG}"

if [ -n "${GHCR_TOKEN:-}" ]; then
  echo "Logging in to ${REGISTRY}..."
  printf '%s' "$GHCR_TOKEN" | docker login "$REGISTRY" -u "${GHCR_USERNAME:-$IMAGE_OWNER}" --password-stdin
fi

backup_dir="storage/app/private/release-backups/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$backup_dir"
cp .env "$backup_dir/.env"

if compose ps mysql 2>/dev/null | grep -q cang-mysql; then
  echo "Creating pre-update database backup..."
  compose exec -T mysql sh -lc 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "$backup_dir/database.sql" || {
    rm -f "$backup_dir/database.sql"
    fail "数据库备份失败，已停止升级"
  }
fi

echo "Pulling release images..."
compose pull api nginx frontend

echo "Starting services..."
compose up -d mysql redis
compose up -d api nginx frontend worker scheduler

echo "Running migrations and caches..."
compose exec -T api php artisan migrate --force
compose exec -T api php artisan app:optimize
compose restart worker scheduler
compose ps

backend_port="${HTTP_PORT:-127.0.0.1:8080}"
frontend_port="${FRONTEND_PORT:-127.0.0.1:3000}"
backend_port="${backend_port##*:}"
frontend_port="${frontend_port##*:}"

echo "Running smoke checks..."
for url in "http://127.0.0.1:${backend_port}/admin/login" "http://127.0.0.1:${frontend_port}/"; do
  status="$(curl -fsS -o /dev/null -w '%{http_code}' "$url" || true)"
  case "$status" in
    200|301|302|307|308) echo "  ✓ $url -> $status" ;;
    *) fail "健康检查失败: $url -> ${status:-connection failed}. 备份目录: $backup_dir" ;;
  esac
done

echo "Release update complete. Backup: $backup_dir"
