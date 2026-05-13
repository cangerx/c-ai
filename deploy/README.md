# CANG-AI 部署指南

## 服务器环境要求

- **PHP** 8.3+（扩展：mbstring, xml, ctype, iconv, intl, pdo_sqlite, bcmath, gd, fileinfo, curl, openssl）
- **Node.js** 20+
- **Composer** 2.x
- **数据库**：SQLite 或 MySQL 8.0+
- **Web 服务器**：Nginx 或 Caddy
- **进程管理**：Supervisor（队列 worker）

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
npm ci
npm run build
```

### 3. 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`：
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql  # 或 sqlite
DB_DATABASE=/var/www/cang-ai/database/database.sqlite  # SQLite 时
QUEUE_CONNECTION=database
```

### 4. 数据库初始化

```bash
php artisan migrate --force
php artisan db:seed --force  # 如有 seeder
```

默认管理员：`admin@cang-ai.com` / `admin123`（请立即修改密码）

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

### 8. 队列 Worker（Supervisor）

```bash
cp deploy/supervisor.conf /etc/supervisor/conf.d/cang-ai.conf
# 编辑路径和用户名
supervisorctl reread
supervisorctl update
supervisorctl start cang-ai-queue:*
```

Supervisor 配置要点：
- `command=php /var/www/cang-ai/artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- `numprocs=2`
- `autostart=true`
- `autorestart=true`
- `stdout_logfile=/var/www/cang-ai/storage/logs/queue.log`

## 日常更新

```bash
bash deploy/deploy.sh
```

或手动：
```bash
cd /var/www/cang-ai
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

## 队列 Worker 部署

队列负责处理图片生成等异步任务。推荐使用 Supervisor 管理：

```ini
[program:cang-ai-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cang-ai/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=900
directory=/var/www/cang-ai
user=www
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/www/cang-ai/storage/logs/queue.log
stderr_logfile=/var/www/cang-ai/storage/logs/queue-error.log
```

重启 worker：
```bash
php artisan queue:restart
# 或
supervisorctl restart cang-ai-queue:*
```

手动重试失败任务：
```bash
php artisan queue:retry all
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

### 2. 队列任务不执行
```bash
supervisorctl status cang-ai-queue:*
# 确认 QUEUE_CONNECTION 不是 sync
php artisan queue:work --once  # 手动执行一个任务调试
```

### 3. 图片生成失败
- 检查 AI 渠道配置（管理后台 → AI 渠道）
- 确认 API Key 有效
- 查看 `storage/logs/laravel.log` 中的错误信息

### 4. 静态资源 404
```bash
php artisan storage:link
npm run build
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
5. 在宝塔「Supervisor」中添加 queue worker
