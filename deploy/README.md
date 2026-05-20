# CANG-AI 部署指南

## 服务器环境要求

- **PHP** 8.3+（扩展：mbstring, xml, ctype, iconv, intl, bcmath, gd, fileinfo, curl, openssl, redis）
- **Composer** 2.x
- **Redis**（图片生成任务必需）
- **数据库**：SQLite 或 MySQL 8.0+
- SQLite 需 `sqlite3`、`pdo_sqlite`；MySQL 需 `pdo_mysql`
- **Web 服务器**：Nginx 或 Caddy
- **进程管理**：Supervisor（`task:worker`）

> 生产部署默认不需要 Node.js；只有改动 Vite/Tailwind 资源时才需要 `npm ci && npm run build`。

## 首次部署步骤

### 1. 克隆代码

```bash
cd /var/www
git clone <repo-url> cang-ai
cd cang-ai
```

### 2. 安装依赖

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### 3. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`，SQLite 示例：
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/cang-ai/database/database.sqlite
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

MySQL 示例：
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cang_ai
DB_USERNAME=cang_ai
DB_PASSWORD=your-password
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 4. 数据库初始化

```bash
php artisan migrate --force
```

### 5. 存储链接

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
chown -R www:www storage bootstrap/cache
```

### 6. 缓存配置

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Nginx 配置

参考 `deploy/nginx.conf`，修改 `server_name` 和 `root` 路径。

Caddy 示例：
```
your-domain.com {
    root * /var/www/cang-ai/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    encode gzip
}
```

### 8. 图片任务 Worker（Supervisor）

```bash
cp deploy/supervisor.conf /etc/supervisor/conf.d/cang-ai.conf
# 编辑路径和用户名
supervisorctl reread
supervisorctl update
supervisorctl start cang-ai-task-worker:*
```

Supervisor 配置要点：
- `command=php /var/www/cang-ai/artisan task:worker --max-retries=3`
- `numprocs=2`
- `autostart=true`
- `autorestart=true`
- `stdout_logfile=/var/www/cang-ai/storage/logs/worker.log`

## 日常更新

```bash
bash deploy/deploy.sh
```

或手动：
```bash
cd /var/www/cang-ai
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
supervisorctl restart cang-ai-task-worker:*
```

## 图片任务 Worker 部署

图片生成任务使用 Redis `BLPOP image_gen_tasks`，必须运行自定义 `task:worker`。推荐使用 Supervisor 管理：

```ini
[program:cang-ai-task-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cang-ai/artisan task:worker --max-retries=3
directory=/var/www/cang-ai
user=www
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/www/cang-ai/storage/logs/worker.log
stderr_logfile=/var/www/cang-ai/storage/logs/worker-error.log
```

重启 worker：
```bash
supervisorctl restart cang-ai-task-worker:*
```

手动调试 worker：
```bash
php artisan task:worker --max-retries=3
```

## 入口说明

| 路径 | 说明 |
|---|---|
| `/` | 前端首页 |
| `/admin` | 管理后台 |
| `/agent` | 代理后台 |
| `/api/*` | RESTful API |

## 常见问题排查

### 1. 500 错误
```bash
tail -50 storage/logs/laravel.log
# 检查文件权限
chmod -R 775 storage bootstrap/cache
```

### 2. 图片任务不执行
```bash
supervisorctl status cang-ai-task-worker:*
ps aux | grep task:worker | grep -v grep
tail -50 storage/logs/worker.log
```

### 3. 图片生成失败
- 检查 AI 渠道配置（管理后台 → AI 渠道）
- 确认 API Key 有效
- 查看 `storage/logs/laravel.log` 中的错误信息

### 4. 静态资源 404
```bash
php artisan storage:link
```

### 5. 数据库迁移失败
```bash
php artisan migrate:status
php artisan migrate --force --step  # 逐步执行
```

### 6. 宝塔面板部署
1. 新建 PHP 站点，运行目录设为 `/public`
2. 伪静态选「laravel5」
3. PHP 版本选 8.3+，启用所需扩展
4. 在站点根目录执行 composer 和 artisan 命令
5. 在宝塔「Supervisor」中添加 `task:worker`
