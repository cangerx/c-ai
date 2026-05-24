<p align="center">
  <img src="https://img.shields.io/badge/CANG--AI-v0.3-0d1117?style=for-the-badge&labelColor=0d1117" alt="Version">
</p>

<h1 align="center">
  <br>
  苍 AI · CANG-AI
  <br>
</h1>

<p align="center">
  <strong>AI-Powered Image Generation Platform</strong><br>
  <sub>多模型智能调度 · 积分计费 · 分销体系 · 一键部署</sub>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.3-4F5B93?style=flat-square&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/Filament-5.6-FDAE4B?style=flat-square&logo=data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJ3aGl0ZSI+PGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iMTAiLz48L3N2Zz4=&logoColor=white" alt="Filament">
  <img src="https://img.shields.io/badge/Next.js-15-000000?style=flat-square&logo=next.js&logoColor=white" alt="Next.js">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker&logoColor=white" alt="Docker">
</p>

<p align="center">
  <a href="#-快速开始">快速开始</a> •
  <a href="#-功能特性">功能特性</a> •
  <a href="#-技术架构">技术架构</a> •
  <a href="#-部署方案">部署方案</a> •
  <a href="#-常见问题">FAQ</a>
</p>

---

## 🖼 功能特性

<table>
<tr>
<td width="50%">

**🎨 AI 图像生成**
- 文生图 / 图生图 / 反推提示词
- 多模型渠道智能调度（权重 / 冷却 / 重试 / 兜底）
- 提示词模板 — 分类管理、变量填充
- 公开画廊 — 作品展示与社区浏览

</td>
<td width="50%">

**💰 商业化体系**
- 积分计费 — 按尺寸 / 质量 / 模型定价
- 套餐订阅 — 灵活的 Plan 配置
- 分销推广 — 多级代理、邀请返佣、自动结算
- 兑换码 / 提现 / 订单管理

</td>
</tr>
<tr>
<td>

**🛡 安全与运维**
- 内容安全 — 提示词过滤 + 图片审核
- 多种登录 — 邮箱 / GitHub / 微信
- Redis Worker — 后台异步处理，失败自动重试
- 系统备份 — 数据库 + 存储一键备份

</td>
<td>

**☁️ 存储与部署**
- 云存储 — 阿里云 OSS / Cloudflare R2 / 本地
- 前端压缩 + 预签名直传
- Docker 一键部署 / 宝塔面板部署
- GHCR 镜像发布 + CI/CD

</td>
</tr>
</table>

---

## 🏗 技术架构

```
                    ┌──────────────────────────────────┐
                    │         Nginx / 宝塔反代          │
                    └──────────┬───────────┬───────────┘
                               │           │
                    ┌──────────▼──┐  ┌─────▼──────────┐
                    │  Next.js 15  │  │  Laravel 13    │
                    │  SSR 前端    │  │  API + Admin   │
                    │  :3000       │  │  :8080         │
                    └─────────────┘  └───────┬────────┘
                                             │
                         ┌───────────────────┼───────────────────┐
                         │                   │                   │
                  ┌──────▼──────┐    ┌───────▼──────┐   ┌───────▼──────┐
                  │  MySQL 8.0   │    │  Redis 7.0   │   │  OSS / R2    │
                  │  数据持久化   │    │  队列 + 缓存  │   │  图片存储    │
                  └─────────────┘    └──────────────┘   └──────────────┘
```

| 层级 | 技术选型 | 说明 |
|------|----------|------|
| 前端 | Next.js 15 + Tailwind CSS | SSR/SSG、图片压缩、OSS 直传 |
| 后端 | Laravel 13 + Sanctum | RESTful API、认证鉴权 |
| 管理 | Filament 5.6 | 后台管理 + 代理商中心 |
| 队列 | Redis + task:worker | BLPOP 长轮询、失败重试 |
| 存储 | MySQL 8.0 + OSS/R2 | 结构化数据 + 对象存储 |
| 部署 | Docker Compose / 宝塔 | 容器化或传统 LNMP |

---

## 🚀 快速开始

### 环境要求

- PHP 8.3+、MySQL 8.0+、Redis 7.0+、Node.js 18+
- 或 Docker + Docker Compose（推荐）

### 本地开发

```bash
git clone https://github.com/cangerx/c-ai.git
cd c-ai
composer setup        # 安装依赖、生成密钥、迁移数据库、构建前端
composer dev          # 启动开发服务器（API + Queue + Vite + Logs）
```

访问 `http://localhost:8000/admin` 进入管理后台。

---

## 📦 部署方案

提供三种部署方式，按需选择：

### 方案一：Docker 一键部署（推荐）

> 宝塔只负责域名和 SSL，应用全部容器化。无需安装 PHP/MySQL/Redis。

```bash
cd /www/wwwroot
git clone https://github.com/cangerx/c-ai.git cang-ai && cd cang-ai

# 一键启动（替换为你的域名）
APP_URL=https://api.example.com \
FRONTEND_APP_URL=https://example.com \
FRONTEND_API_URL=https://api.example.com \
bash docker-install.sh
```

脚本自动完成：生成 `.env` → 生成密钥和密码 → 启动全部服务（PHP / MySQL / Redis / Worker / Scheduler / Next.js）

宝塔添加两个站点，设置反向代理：

| 站点 | 代理目标 |
|------|----------|
| 前端域名 | `http://127.0.0.1:3000` |
| 后端域名 | `http://127.0.0.1:8080` |

访问 `https://api.example.com/install` 完成安装向导。

<details>
<summary><b>镜像版升级（CI/CD）</b></summary>

发布 tag 后 GitHub Actions 自动推送镜像到 GHCR：

```bash
IMAGE_TAG=v0.3.0 bash docker-release-update.sh
```

私有仓库需先登录：

```bash
GHCR_USERNAME=your-user GHCR_TOKEN=your-pat IMAGE_TAG=v0.3.0 bash docker-release-update.sh
```

</details>

---

### 方案二：宝塔面板部署

> 传统 LNMP 方式，适合已有宝塔环境的服务器。

**宝塔安装：** Nginx + MySQL 8.0 + Redis 7.0 + PHP 8.3 + PM2

```bash
git clone https://github.com/cangerx/c-ai.git /www/wwwroot/your-site
cd /www/wwwroot/your-site
bash deploy.sh
```

脚本交互式引导完成全部配置。详见 [DEPLOY_BT.md](./DEPLOY_BT.md)。

---

### 方案三：deploy.sh 自动部署

> 适合任意有 SSH 权限的 Linux 服务器。

```bash
bash deploy.sh
```

自动检测环境、安装依赖、配置 Nginx、设置 Supervisor Worker、部署前端。

---

## 🔧 运维命令

```bash
# Docker 部署
bash docker-install.sh status    # 查看服务状态
bash docker-install.sh logs      # 查看日志
bash docker-install.sh update    # 更新代码并重启

# 传统部署
php artisan task:worker          # 启动图像生成 Worker
php artisan app:optimize         # 优化缓存和性能
bash update.sh                   # 拉取代码并更新
```

---

## ❓ 常见问题

<details>
<summary><b>图片生成一直转圈</b></summary>

1. 确认 Worker 正在运行（Docker 自动管理 / 传统部署检查 Supervisor）
2. 管理后台 → AI 渠道，确认已添加有效 API Key
3. 查看日志：`bash docker-install.sh logs` 或 `storage/logs/laravel.log`

</details>

<details>
<summary><b>Docker 端口冲突</b></summary>

```bash
HTTP_PORT=127.0.0.1:18080 FRONTEND_PORT=127.0.0.1:13000 bash docker-install.sh
```

宝塔反代目标对应修改为新端口。

</details>

<details>
<summary><b>备份策略</b></summary>

```
/www/wwwroot/cang-ai/.env           # 环境配置
/www/wwwroot/cang-ai/storage        # 上传文件
/www/wwwroot/cang-ai/docker-data    # Docker 数据卷（MySQL 等）
```

</details>

---

## 📄 许可协议

本项目为**商业软件**，源码仅供学习参考。

- ✅ 个人学习、研究、借鉴思路
- ❌ 未授权商业部署、二次分发、去除版权

商业授权请联系作者。

---

<p align="center">
  <sub>Built by <strong>苍洱</strong> · <a href="https://t.me/cangerx">Telegram @cangerx</a></sub>
</p>
