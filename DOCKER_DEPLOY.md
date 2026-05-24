# CANG-AI Docker Compose 部署方案

本文档用于新服务器 Docker 化部署后端全套运行环境：Laravel、Nginx、MySQL、Redis、Worker、Scheduler。前端 `cang-ai-web` 可继续独立部署，或后续单独容器化。

## 1. 服务器准备

安装 Docker 与 Docker Compose。宝塔面板可继续用于：

- 域名和 SSL 证书
- 反向代理到 Docker Nginx
- 防火墙和端口管理

Docker 后端默认只暴露宿主机 `8080`：

```text
宝塔 Nginx 443/80 -> http://127.0.0.1:8080 -> Docker cang-nginx -> cang-api
```

## 2. 拉取代码

```bash
cd /www/wwwroot
git clone https://github.com/cangerx/c-ai.git cang-ai
cd /www/wwwroot/cang-ai
git checkout main
```

## 3. 配置环境变量

```bash
cp .env.docker.example .env
```

编辑 `.env`，至少修改：

```env
APP_URL=https://api.example.com
APP_KEY=base64:旧站APP_KEY或新生成APP_KEY

MYSQL_ROOT_PASSWORD=强root密码
DB_DATABASE=cang_ai
DB_USERNAME=cang_ai
DB_PASSWORD=强数据库密码

HTTP_PORT=8080
RUN_MIGRATIONS=false
```

迁移旧站时，`APP_KEY` 必须使用旧站 `.env` 里的值。

如果是全新安装，可以先生成 key：

```bash
docker compose run --rm --no-deps --build -e WAIT_FOR_DB=false api php artisan key:generate --show
```

然后把输出写入 `.env` 的 `APP_KEY`。

## 4. 启动基础服务

```bash
docker compose build
docker compose up -d mysql redis
docker compose up -d api nginx worker scheduler
```

首次新装执行迁移：

```bash
docker compose exec api php artisan migrate --force
docker compose exec api php artisan storage:link
docker compose exec api php artisan config:cache
docker compose exec api php artisan view:cache
```

查看服务：

```bash
docker compose ps
docker compose logs -f api
docker compose logs -f worker
```

## 5. 宝塔反向代理

宝塔添加后端站点后，在反向代理中添加：

```text
目标 URL: http://127.0.0.1:8080
发送域名: $host
```

或者 Nginx 手写：

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

SSL 证书继续在宝塔里申请和续期。

## 6. 导入旧站数据

### 方式 A：使用系统备份包

旧站先升级到至少 `v0.2.4`，在后台创建系统备份。把备份包上传到新服务器，例如：

```text
/www/backup/cang-ai-backup.tar.gz
```

新站后台导入备份包并恢复。或者进入容器执行恢复相关后台操作前，确保 `.env` 已经使用新服务器数据库配置和旧站 `APP_KEY`。

### 方式 B：手工导入 SQL 和 storage

导入数据库：

```bash
docker compose exec -T mysql sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < /www/backup/database.sql
```

恢复本地文件：

```bash
tar -xzf /www/backup/storage-env.tar.gz -C /www/wwwroot/cang-ai
```

如果恢复覆盖了 `.env`，必须重新确认：

```env
DB_HOST=mysql
DB_SOCKET=
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
APP_URL=https://api.example.com
APP_KEY=旧站APP_KEY
```

恢复后执行：

```bash
docker compose exec api php artisan migrate --force
docker compose exec api php artisan storage:link
docker compose exec api php artisan config:clear
docker compose exec api php artisan cache:clear
docker compose exec api php artisan route:clear
docker compose exec api php artisan view:clear
docker compose exec api php artisan config:cache
docker compose exec api php artisan view:cache
docker compose restart api worker scheduler nginx
```

## 7. Worker 扩容

默认启动 1 个 worker。需要更多并发：

```bash
docker compose up -d --scale worker=2
```

不要让旧服务器和新服务器同时消费同一个 Redis 队列。

## 8. 日常更新

```bash
cd /www/wwwroot/cang-ai
git pull origin main
docker compose build api nginx
docker compose up -d api nginx worker scheduler
docker compose exec api php artisan migrate --force
docker compose exec api php artisan config:cache
docker compose exec api php artisan view:cache
docker compose restart worker scheduler
```

## 9. 备份

应用内备份仍可在后台创建。Docker 级别建议额外备份：

```bash
cd /www/wwwroot/cang-ai
docker compose exec -T mysql sh -c 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > /www/backup/cang_ai.sql
tar -czf /www/backup/cang_ai_storage_env.tar.gz .env storage docker-data/redis
```

如果要完整冷备 MySQL 数据目录，先停服务：

```bash
docker compose stop api worker scheduler nginx mysql redis
tar -czf /www/backup/cang_ai_docker_data.tar.gz docker-data storage .env
docker compose up -d
```

## 10. 常见问题

### 500 错误

```bash
docker compose logs -f api
tail -f storage/logs/laravel.log
```

### 数据库连不上

确认 `.env`：

```env
DB_HOST=mysql
DB_CONNECTION=mysql
```

然后：

```bash
docker compose ps mysql
docker compose logs mysql
```

### 图片 404

确认 storage 挂载和软链：

```bash
docker compose exec api php artisan storage:link
ls -la storage/app/public
```

### 队列不消费

```bash
docker compose logs -f worker
docker compose exec redis redis-cli llen image_gen_tasks
```

如果 Redis 配了密码：

```bash
docker compose exec redis sh -c 'redis-cli -a "$REDIS_PASSWORD" llen image_gen_tasks'
```
