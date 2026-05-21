<p align="center">
  <img src="https://img.shields.io/badge/CANG--AI-绘图平台-blue?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiPjxwYXRoIGQ9Im0yMSAxNS0zLjA4Ni04LjQ1YS42MTEuNjExIDAgMCAwLS41NzItLjRoLS4wMDFhLjYxMS42MTEgMCAwIDAtLjU3LjRMMTMuNjg4IDE1Ii8+PHBhdGggZD0ibTEwIDE1LTMuMDg2LTguNDVhLjYxMS42MTEgMCAwIDAtLjU3Mi0uNGgtLjAwMWEuNjExLjYxMSAwIDAgMC0uNTcuNEwyLjY4OCAxNSIvPjxwYXRoIGQ9Ik0yIDE1aDgiLz48cGF0aCBkPSJNMTMgMTVoOCIvPjxwYXRoIGQ9Ik0yIDIwaDIwIi8+PC9zdmc+" alt="CANG-AI">
</p>

<h1 align="center">CANG-AI 绘图</h1>

<p align="center">
  <strong>让想象力，一键成画</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3+-8892BF?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/Next.js-15-000000?style=flat-square&logo=next.js&logoColor=white" alt="Next.js">
  <img src="https://img.shields.io/badge/License-Commercial-black?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/Worker-Redis_task:worker-red?style=flat-square" alt="Worker">
  <img src="https://img.shields.io/badge/Deploy-宝塔_|_Nginx-orange?style=flat-square" alt="Deploy">
</p>

<p align="center">
  基于 Laravel 13 + Next.js 15 + Filament 构建的 AI 图像生成平台<br>
  支持多模型渠道 · 智能调度 · 积分计费 · 分销推广 · 提示词工具 · OSS 直传 · 浏览器一键安装
</p>

---

## ✨ 特性

|  |  |
|--|--|
| 🎨 **文生图 / 图生图 / 反推提示词** — 提示词或参考图，AI 即刻生成 | 🔄 **智能渠道调度** — 权重、冷却、重试、兜底渠道 |
| 💰 **积分计费** — 按尺寸质量定价，兑换码充值 | 👥 **分销推广** — 邀请注册返佣，佣金自动结算 |
| 📱 **全端适配** — PC / 平板 / 手机响应式设计 | 🛡️ **内容安全** — 提示词过滤 + 图片审核 |
| ⚡ **Redis Worker** — `task:worker` 后台处理，失败自动重试 | 🔐 **多种登录** — 邮箱 / GitHub / 微信扫码 |
| 📝 **提示词模板** — 分类管理，变量填充，一键生成 | 🖼️ **公开画廊** — 作品展示，社区浏览 |
| ☁️ **图片压缩 + OSS 直传** — 前端自动压缩，预签名直传阿里云/R2 | 🗄️ **云存储管理** — 后台向导式配置 OSS / R2 / 本地 |

---

## � 技术架构

```
┌─────────────────────────────────────────────────────────────┐
│  Frontend — Next.js 15 (standalone) + PM2                   │
│  图片压缩 · OSS 预签名直传 · SSR/SSG                         │
├─────────────────────────────────────────────────────────────┤
│  API Layer — Laravel 13 + Sanctum 认证                      │
├─────────────────────────────────────────────────────────────┤
│  Admin — Filament 管理后台 + 代理中心                        │
├─────────────────────────────────────────────────────────────┤
│  Worker — Redis BLPOP + task:worker                         │
├─────────────────────────────────────────────────────────────┤
│  Storage — MySQL 8.0 / SQLite · 阿里云 OSS / Cloudflare R2  │
└─────────────────────────────────────────────────────────────┘
```

```
后端仓库 (c-ai)                     前端仓库 (cang-ai-web)
├── app/Apps/ImageGen/  图像生成     ├── src/app/          Next.js 页面
├── app/Filament/       管理后台     ├── src/components/   UI 组件
├── app/Services/       业务服务     ├── src/lib/          API·上传·工具
├── routes/api.php      API 路由     ├── src/stores/       状态管理
├── deploy.sh           部署脚本     └── next.config.mjs   Next.js 配置
└── public/             静态资源
```

---

## 🚀 一键部署（推荐）

> 适合有 SSH 权限的 Linux 服务器，3 条命令完成全栈部署。

### 1. 后端部署

```bash
git clone https://github.com/cangerx/c-ai.git /www/wwwroot/your-site
cd /www/wwwroot/your-site
bash deploy.sh
```

**脚本自动处理：**

| 项目 | 自动完成 |
|------|----------|
| PHP 环境 | 检测 8.3+，自动寻找宝塔 PHP 路径 |
| 禁用函数 | 自动解除 putenv、proc_open、exec、symlink 等 |
| Composer | 自动安装/升级到最新版 |
| PHP 扩展 | 检测 mbstring、gd、redis 等 11 项扩展 |
| 依赖安装 | composer install --no-dev |
| 目录权限 | storage、bootstrap/cache 自动修复 |
| 安装向导 | 交互式选择：命令行 或 Web 向导 |
| 数据库 | 支持 MySQL / SQLite，自动迁移 |
| Worker | 自动启动 Redis task:worker |

### 2. 前端部署

前端为独立的 Next.js 项目，支持两种部署方式：

#### 方式 A：服务器构建（deploy.sh 自动处理）

```bash
bash deploy.sh update-frontend
```

脚本自动：克隆前端仓库 → npm install → npm run build → PM2 启动 standalone。

> 要求服务器已安装 Node.js 18+（宝塔软件商店可一键安装）。

#### 方式 B：本地构建上传（推荐低配服务器）

在本地（Mac/Windows）构建，只上传编译产物到服务器：

```bash
# 本地执行
cd cang-ai-web
bash deploy-frontend.sh root@your-server-ip
```

脚本自动：本地 npm run build → 打包 standalone → rsync 上传 → PM2 重启。

> 服务器只需 Node.js 运行时，不需要 npm。

### 3. Nginx 配置

前后端同域名，通过 Nginx 路由分流：

```nginx
server {
    listen 80;
    listen 443 ssl;
    http2 on;
    server_name your-domain.com;

    ssl_certificate    /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    root /www/wwwroot/your-site/public;
    index index.php;

    # ── 后端 Laravel（API + 管理后台） ──
    location ~ ^/(api|admin|agent|livewire|filament|install|up|sanctum) {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── PHP 处理 ──
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # ── 后端静态资源 ──
    location ~ ^/(storage|vendor|css/filament|js/filament) {
        expires 30d;
        try_files $uri =404;
    }

    # ── 前端 Next.js ──
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 📦 宝塔面板手动部署

> 适合不熟悉命令行、偏好可视化操作的用户。

### 第一步：安装环境

在宝塔 **软件商店** 安装以下组件：

| 组件 | 操作 |
|------|------|
| **Nginx** | 安装最新稳定版 |
| **PHP 8.3** | 安装后，在 **设置 → 禁用函数** 中删除：`putenv`、`proc_open`、`exec`、`symlink`、`pcntl_signal`、`pcntl_alarm` |
| **MySQL 8.0** | 安装后，**数据库** 页面创建数据库 `cang_ai`，记住用户名密码 |
| **Redis** | 安装并确保已启动 |
| **Node.js 18+** | PM2管理器 → Node版本 → 安装 v18 或更高版本 |
| **Supervisor** | 软件商店安装（用于守护 Worker 进程） |

### 第二步：创建网站

1. **网站 → 添加站点**
   - 域名：`your-domain.com`
   - PHP 版本：`PHP-83`
   - 数据库：选择 MySQL（或不创建，用 SQLite）

2. 进入站点根目录：
   ```
   /www/wwwroot/your-domain.com
   ```

### 第三步：部署后端

```bash
# 进入站点目录（清空默认文件）
cd /www/wwwroot/your-domain.com
rm -rf .htaccess 404.html index.html .user.ini

# 克隆代码
git clone https://github.com/cangerx/c-ai.git .

# 一键部署
bash deploy.sh
```

部署脚本会引导你完成数据库配置和管理员创建。

### 第四步：配置站点

在宝塔面板中：

| 配置项 | 操作 |
|--------|------|
| **网站目录** | 运行目录设为 `/public` |
| **伪静态** | 选择 `laravel5` |
| **SSL** | Let's Encrypt → 一键申请 |
| **PHP 版本** | 确认为 `PHP-83` |

### 第五步：部署前端

```bash
# 自动拉取前端仓库、构建、PM2 启动
bash deploy.sh update-frontend
```

或手动：

```bash
# 克隆前端
cd /www/wwwroot
git clone https://github.com/cangerx/cang-ai-web.git
cd cang-ai-web

# 设置环境变量
echo "NEXT_PUBLIC_API_URL=https://your-domain.com" > .env.local

# 安装依赖并构建
npm install
npm run build

# PM2 启动
cp -r .next/static .next/standalone/.next/static
cp -r public .next/standalone/public
cd .next/standalone
PORT=3000 pm2 start server.js --name cang-ai-web
pm2 save
```

### 第六步：修改 Nginx 配置

宝塔 → 网站 → 你的站点 → 配置文件，**替换为**以下内容（注意修改域名和路径）：

```nginx
server {
    listen 80;
    listen [::]:80;
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name your-domain.com;

    ssl_certificate    /www/server/panel/vhost/cert/your-domain.com/fullchain.pem;
    ssl_certificate_key /www/server/panel/vhost/cert/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    client_max_body_size 50m;
    access_log /www/wwwlogs/your-domain.com.log;
    error_log  /www/wwwlogs/your-domain.com.error.log;

    root /www/wwwroot/your-domain.com/public;
    index index.php;

    # 后端 Laravel
    location ~ ^/(api|admin|agent|livewire|filament|install|up|sanctum) {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # 后端静态资源
    location ~ ^/(storage|images|css/filament|js/filament|fonts/filament|vendor) {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # 前端 Next.js
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    location ~ /\. {
        deny all;
    }
}
```

修改后点击 **保存**，Nginx 会自动重载。

### 第七步：配置 Worker

宝塔 → **Supervisor** → 添加守护进程：

| 配置项 | 值 |
|--------|----|
| 名称 | `cang-ai-worker` |
| 启动命令 | `/www/server/php/83/bin/php /www/wwwroot/your-domain.com/artisan task:worker --max-retries=3` |
| 进程数 | `2` |
| 启动用户 | `www` |

### 第八步：进入管理后台

访问 `https://your-domain.com/admin`，使用安装时创建的管理员账号登录。

**首次配置：**
1. **AI 渠道** → 添加你的 API Key（OpenAI / 中转站）
2. **云存储**（可选）→ 配置阿里云 OSS 或 Cloudflare R2
3. **站点设置** → 配置网站名称、公告等

---

## 🔄 日常更新

```bash
cd /www/wwwroot/your-site

# 更新全部（后端 + 前端）
bash deploy.sh update

# 仅更新后端
bash deploy.sh update-backend

# 仅更新前端
bash deploy.sh update-frontend

# 查看运行状态
bash deploy.sh status
```

脚本自动执行：git pull → composer install → 数据库迁移 → 缓存刷新 → 重启 Worker → 前端重建。

---

## 📡 API

| 方法 | 端点 | 说明 |
|:----:|------|------|
| POST | `/api/register` | 注册 |
| POST | `/api/login` | 登录 |
| GET | `/api/me` | 用户信息（需认证） |
| POST | `/api/apps/image-gen/generate` | 提交生成任务（需认证） |
| GET | `/api/apps/image-gen/status` | 查询任务状态（需认证） |
| POST | `/api/reverse-prompt` | 图片反推提示词（需认证） |
| POST | `/api/prompt-tool` | 提示词优化/翻译（需认证） |
| POST | `/api/upload-image` | 上传参考图（需认证） |
| POST | `/api/upload-presign` | 获取 OSS 预签名 URL（需认证） |
| GET | `/api/explore` | 首页作品流 JSON |
| POST | `/api/withdrawals` | 分销员申请提现（需认证） |
| POST | `/api/redeem` | 兑换码充值（需认证） |
| GET | `/api/templates` | 提示词模板列表 |
| POST | `/api/templates/{id}/build` | 构建模板提示词 |
| GET | `/api/config` | 前端配置 |
| GET | `/api/download` | 图片代理下载 |

---

## 🔧 环境要求

| 组件 | 版本 | 说明 |
|------|------|------|
| PHP | 8.3+ | 扩展：mbstring, xml, ctype, iconv, intl, bcmath, gd, fileinfo, curl, openssl, redis |
| MySQL | 8.0+ | 或 SQLite；MySQL 需 pdo_mysql，SQLite 需 sqlite3 / pdo_sqlite |
| Composer | 2.2+ | 脚本自动安装 |
| Redis | 6.0+ | 图片生成任务必需 |
| Node.js | 18+ | 前端运行（仅需运行时，standalone 模式不需要 npm） |
| PM2 | 5+ | 前端进程管理（随 Node.js 安装） |
| Nginx | 1.18+ | 宝塔自带 |

生产 `.env` 至少确认：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cang_ai
DB_USERNAME=cang_ai
DB_PASSWORD=your-password

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

---

## ❓ 常见问题

<details>
<summary><b>前端报 404 / API 无法访问</b></summary>

检查 Nginx 配置是否正确分流了 `/api` 到 Laravel 和 `/` 到 Next.js。确保 `location ~ \.php$` 在 `location /` 之前。

```bash
# 测试后端 API 是否正常
curl -s https://your-domain.com/api/config

# 测试前端是否运行
curl -s http://127.0.0.1:3000
```
</details>

<details>
<summary><b>PM2 前端启动后立即退出</b></summary>

检查 Node.js 是否在 PATH 中：
```bash
export PATH=/www/server/nodejs/v18.x.x/bin:$PATH
pm2 logs cang-ai-web --lines 20
```
</details>

<details>
<summary><b>图片生成一直转圈</b></summary>

检查 Worker 是否运行：
```bash
php artisan task:worker --max-retries=3
# 或查看 Supervisor 状态
```
确认已在管理后台 → AI 渠道添加了有效的 API Key。
</details>

<details>
<summary><b>deploy.sh 报错 "PHP 8.2+ 未找到"</b></summary>

在宝塔软件商店安装 PHP 8.3，脚本会自动检测 `/www/server/php/83/bin/php`。
</details>

---

## 📄 许可协议

本项目为**商业软件**，仅供学习参考，**不支持开源使用**。

- ✅ 允许个人学习、研究、借鉴思路
- ❌ 禁止未经授权的商业部署、二次分发、去除版权
- ❌ 禁止将本项目代码用于任何公开或商业产品

如需商业授权或定制开发，请联系作者。

---

<p align="center">
  <sub>Made by <strong>苍洱</strong> · Telegram <a href="https://t.me/cangerx">@cangerx</a> · 商业授权咨询请私信</sub>
</p>
