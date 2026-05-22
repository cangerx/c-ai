# 苍洱 AI 宝塔面板部署教程

## 一、环境准备

### 1.1 安装必要软件（宝塔面板）

登录宝塔面板 → **软件商店**，安装以下：

| 软件 | 版本建议 | 用途 |
|------|----------|------|
| Nginx | 1.24+ | Web 服务器 |
| MySQL | 8.0 | 数据库 |
| Redis | 7.0+ | 缓存 / 队列 |
| PHP-8.3 | 8.3.x | 后端运行环境 |
| PM2 管理器 | 最新版 | 前端 Node.js 进程管理 |
| phpMyAdmin | 最新版 | 数据库管理（可选） |

### 1.2 PHP 扩展配置

进入 **PHP-8.3 设置** → **安装扩展**，确保以下扩展已安装：

```
bcmath
ctype
dom
fileinfo
gd
json
mbstring
openssl
pdo_mysql
redis
tokenizer
xml
zip
```

> 注意：`fileinfo` 如果报 "already loaded" 警告，检查 `/www/server/php/83/etc/php.ini` 不要有重复的 `extension=fileinfo.so`。

---

## 二、后端部署

### 2.1 创建站点

宝塔面板 → **网站** → **添加站点**：

- **域名**：填写你的后端域名，例如 `vxvx.eu.cc`
- **根目录**：`/www/wwwroot/vxvx.eu.cc/public`
- **PHP 版本**：PHP-8.3
- **数据库**：选择 MySQL，记住账号密码

### 2.2 上传/拉取代码

```bash
cd /www/wwwroot/vxvx.eu.cc
git clone https://github.com/cangerx/c-ai.git .
# 或者上传本地压缩包后解压
```

### 2.3 安装依赖

```bash
cd /www/wwwroot/vxvx.eu.cc
# 安装 Composer（如未安装）
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 安装项目依赖
composer install --no-dev --optimize-autoloader
```

### 2.4 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 文件：

```env
APP_NAME=苍洱AI
APP_ENV=production
APP_KEY=base64:xxx（自动生成）
APP_DEBUG=false
APP_URL=https://vxvx.eu.cc

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cang_ai
DB_USERNAME=宝塔创建的数据库账号
DB_PASSWORD=宝塔创建的数据库密码

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis

# 对象存储（可选，默认本地存储）
STORAGE_DRIVER=local
# STORAGE_DRIVER=cos
# STORAGE_BUCKET=your-bucket
# STORAGE_REGION=ap-guangzhou
# STORAGE_ENDPOINT=https://cos.ap-guangzhou.myqcloud.com
# STORAGE_URL=https://your-cdn.example.com
# STORAGE_ACCESS_KEY=xxx
# STORAGE_SECRET_KEY=xxx
```

### 2.5 数据库初始化

```bash
cd /www/wwwroot/vxvx.eu.cc
php artisan migrate --force
php artisan db:seed --force
```

### 2.6 缓存优化

```bash
cd /www/wwwroot/vxvx.eu.cc
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.7 目录权限

```bash
chmod -R 755 /www/wwwroot/vxvx.eu.cc/storage
chmod -R 755 /www/wwwroot/vxvx.eu.cc/bootstrap/cache
chown -R www:www /www/wwwroot/vxvx.eu.cc/storage
chown -R www:www /www/wwwroot/vxvx.eu.cc/bootstrap/cache
```

### 2.8 配置队列 Worker

宝塔面板 → **计划任务** → **添加任务**：

| 设置 | 值 |
|------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | AI图片生成队列 |
| 执行周期 | 每分钟 |
| 脚本内容 | `cd /www/wwwroot/vxvx.eu.cc && php artisan task:worker >> /dev/null 2>&1` |

> 建议启动 2-3 个 worker，复制添加多个相同任务即可。

### 2.9 配置 Laravel 调度器

宝塔面板 → **计划任务** → **添加任务**：

| 设置 | 值 |
|------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | Laravel 调度器 |
| 执行周期 | 每分钟 |
| 脚本内容 | `cd /www/wwwroot/vxvx.eu.cc && php artisan schedule:run >> /dev/null 2>&1` |

### 2.10 重启 PHP-FPM

宝塔面板 → **服务** → 找到 PHP-8.3 → **重启**

或者命令行：

```bash
/etc/init.d/php-fpm-83 restart
```

---

## 三、前端部署

### 3.1 上传/拉取代码

```bash
cd /www/wwwroot/cang-ai-web
git clone https://github.com/cangerx/cang-ai-web.git .
```

### 3.2 安装依赖 & 构建

```bash
cd /www/wwwroot/cang-ai-web
npm install
npm run build
```

### 3.3 PM2 启动

```bash
cd /www/wwwroot/cang-ai-web
pm2 start ecosystem.config.js --env production
# 或者
pm2 start npm --name "cang-ai-web" -- run start
```

或者使用宝塔的 **PM2 管理器**：

- **启动文件**：`/www/wwwroot/cang-ai-web/server.js`（Next.js standalone 模式）
- **项目目录**：`/www/wwwroot/cang-ai-web`
- **运行参数**：`-p 3000`

### 3.4 配置前端环境变量

创建 `/www/wwwroot/cang-ai-web/.env.production`：

```env
NEXT_PUBLIC_API_URL=https://vxvx.eu.cc/api
NEXT_PUBLIC_APP_URL=https://cang-ai-web.example.com
```

---

## 四、Nginx 配置

### 4.1 后端站点配置

宝塔 → **网站** → 选择后端站点 → **设置** → **配置文件**：

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name vxvx.eu.cc;
    root /www/wwwroot/vxvx.eu.cc/public;
    index index.php index.html;

    # SSL 证书（宝塔自动配置）
    ssl_certificate /www/server/panel/vhost/cert/vxvx.eu.cc/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/vxvx.eu.cc/privkey.pem;

    # 前端静态资源跨域（如前端独立域名）
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        add_header Access-Control-Allow-Origin '*' always;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Laravel 入口
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问敏感文件
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 4.2 前端站点配置（反向代理到 PM2）

宝塔 → **网站** → **添加站点** → 前端域名 → **设置** → **反向代理** → **添加反向代理**：

| 设置 | 值 |
|------|-----|
| 目标 URL | `http://127.0.0.1:3000` |
| 发送域名 | `$host` |
| 内容替换 | 留空 |

或者手动编辑配置文件：

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name cang-ai-web.example.com;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

---

## 五、对象存储配置（可选）

### 5.1 腾讯云 COS

进入后台 → **系统设置** → **存储设置**：

| 字段 | 示例值 |
|------|--------|
| 存储驱动 | `cos` |
| 存储桶名称 | `my-bucket-125xxxx` |
| 所在地域 | `ap-guangzhou` |
| 访问域名 (Endpoint) | `https://cos.ap-guangzhou.myqcloud.com` |
| CDN 域名 | `https://my-bucket-125xxxx.cos.ap-guangzhou.myqcloud.com`（可选） |
| Access Key | 腾讯云 CAM 密钥 |
| Secret Key | 腾讯云 CAM 密钥 |

保存后点击 **连接测试** → **上传测试图片** 验证。

### 5.2 阿里云 OSS

| 字段 | 示例值 |
|------|--------|
| 存储驱动 | `oss` |
| 存储桶名称 | `my-bucket` |
| 所在地域 | `oss-cn-hangzhou` |
| 访问域名 (Endpoint) | `https://oss-cn-hangzhou.aliyuncs.com` |
| CDN 域名 | `https://my-bucket.oss-cn-hangzhou.aliyuncs.com`（可选） |

### 5.3 Cloudflare R2

| 字段 | 示例值 |
|------|--------|
| 存储驱动 | `r2` |
| 存储桶名称 | `my-bucket` |
| 所在地域 | `auto` |
| 访问域名 (Endpoint) | `https://xxxx.r2.cloudflarestorage.com` |
| CDN 域名 | `https://cdn.example.com`（可选，Cloudflare 自带 CDN） |

---

## 六、日常维护

### 6.1 升级代码

```bash
# 后端
cd /www/wwwroot/vxvx.eu.cc
git pull origin main
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan view:clear
php artisan config:cache && php artisan view:cache
/etc/init.d/php-fpm-83 restart

# 前端
cd /www/wwwroot/cang-ai-web
git pull origin main
npm install
npm run build
pm2 restart cang-ai-web
```

### 6.2 查看日志

```bash
# Laravel 日志
tail -f /www/wwwroot/vxvx.eu.cc/storage/logs/laravel.log

# Worker 日志（如果使用 pm2 管理 worker）
pm2 logs cang-ai-worker

# Nginx 错误日志
tail -f /www/wwwlogs/vxvx.eu.cc.error.log
```

### 6.3 重启服务

```bash
# 重启 PHP
/etc/init.d/php-fpm-83 restart

# 重启 Redis
/etc/init.d/redis restart

# 重启 Nginx
/etc/init.d/nginx restart

# 重启前端
pm2 restart cang-ai-web
```

---

## 七、常见问题

| 问题 | 解决 |
|------|------|
| 500 错误 | 检查 `storage/logs/laravel.log` |
| 图片上传失败 | 检查存储设置中的 Endpoint 和密钥；点击「连接测试」 |
| 队列不消费 | 检查 Redis 是否运行；确认 worker 任务在计划任务中 |
| 定时任务不执行 | 确认 `schedule:run` 已加入宝塔计划任务（每分钟） |
| 前端 API 请求失败 | 检查 `.env.production` 中的 `NEXT_PUBLIC_API_URL` |
| 对象存储图片 403 | 检查 bucket 权限是否为「公有读」；检查 CDN 域名配置 |

---

## 八、文件路径速查

| 用途 | 路径 |
|------|------|
| 后端代码 | `/www/wwwroot/vxvx.eu.cc` |
| 前端代码 | `/www/wwwroot/cang-ai-web` |
| Nginx 配置 | `/www/server/panel/vhost/nginx/` |
| PHP 配置 | `/www/server/php/83/etc/php.ini` |
| MySQL 数据 | `/www/server/data/` |
| Redis 配置 | `/www/server/redis/redis.conf` |
| PM2 日志 | `~/.pm2/logs/` |
