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
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
  <img src="https://img.shields.io/badge/Deploy-宝塔_|_Nginx_|_Caddy-orange?style=flat-square" alt="Deploy">
</p>

<p align="center">
  基于 Laravel 13 构建的 AI 图像生成平台<br>
  支持多模型渠道 · 积分计费 · 分销推广 · 浏览器一键安装
</p>

---

## ✨ 特性

|  |  |
|--|--|
| 🎨 **文生图 / 图生图** — 提示词或参考图，AI 即刻生成 | 🔄 **多渠道轮询** — GPT-Image、DALL·E 等模型自由切换 |
| 💰 **积分计费** — 按尺寸质量定价，兑换码充值 | 👥 **分销推广** — 邀请注册返佣，佣金自动结算 |
| 📱 **全端适配** — PC / 平板 / 手机响应式设计 | 🛡️ **内容安全** — 提示词过滤 + 图片审核 |
| ⚡ **异步队列** — 任务后台处理，失败自动重试 | 🔐 **多种登录** — 邮箱 / GitHub / 微信扫码 |
| 📝 **提示词模板** — 分类管理，变量填充，一键生成 | 🖼️ **公开画廊** — 作品展示，社区浏览 |

---

## 🚀 部署方式

### 方式一：全自动脚本（推荐）

只需 3 条命令，脚本自动完成所有环境修复和配置：

```bash
git clone https://github.com/cangerx/c-ai.git /www/wwwroot/your-site
cd /www/wwwroot/your-site
bash deploy.sh
```

**脚本自动处理：**
- ✅ 检测 PHP 版本，自动寻找宝塔 PHP 8.3 路径
- ✅ 自动解除 PHP 禁用函数（putenv、proc_open、exec、symlink 等）
- ✅ 自动安装/升级 Composer 到最新版
- ✅ 检测并提示缺失的 PHP 扩展
- ✅ 安装 PHP 依赖
- ✅ 修复所有目录权限
- ✅ 交互式选择安装方式（命令行 或 Web 向导）
- ✅ 自动创建 MySQL 数据库和用户（命令行模式）
- ✅ 数据库迁移、缓存配置、启动队列 Worker

### 方式二：Web 安装向导

运行 `bash deploy.sh` 时选择「Web 安装向导」，然后浏览器访问：

```
https://your-domain.com/install
```

向导自动完成：
- ✅ PHP 环境检测（8 项扩展 + 目录权限）
- ✅ 数据库连接测试（支持 MySQL / SQLite）
- ✅ 生成应用密钥 & 运行迁移
- ✅ 创建管理员账号
- ✅ 配置生产环境参数

---

## 📦 宝塔面板部署步骤

| 步骤 | 操作 |
|:---:|------|
| 1 | 新建 PHP 站点，PHP 版本 **8.3+** |
| 2 | 终端执行：`git clone https://github.com/cangerx/c-ai.git .` |
| 3 | 终端执行：`bash deploy.sh`（自动修复环境 + 安装） |
| 4 | 网站目录 → 运行目录设为 `/public` |
| 5 | 伪静态 → 选择 `laravel5` |
| 6 | SSL → Let's Encrypt 一键申请 |
| 7 | 管理后台 → AI 渠道 → 添加 API Key |

> 💡 `deploy.sh` 会自动处理：Composer 安装、PHP 禁用函数解除、扩展检测、权限修复、数据库创建。无需手动操作。

**队列 Worker（可选，提升性能）：**

宝塔 → Supervisor → 添加守护进程：
```
php /www/wwwroot/your-site/artisan task:worker --max-retries=3
```
进程数设为 2。

---

## 🔄 日常更新

```bash
bash deploy.sh
```

脚本自动执行：git pull → composer install → 数据库迁移 → 缓存刷新 → 重启 Worker。

---

## 📐 技术架构

```
┌─────────────────────────────────────────────┐
│  Frontend — 原生 HTML/CSS/JS 单页应用（零依赖）  │
├─────────────────────────────────────────────┤
│  API Layer — Laravel 13 + Sanctum 认证       │
├─────────────────────────────────────────────┤
│  Queue — Database Driver + Supervisor        │
├─────────────────────────────────────────────┤
│  Storage — MySQL 8.0 / SQLite               │
└─────────────────────────────────────────────┘
```

**核心目录：**

```
app/Apps/ImageGen/    → 图像生成模块（控制器、路由、视图）
app/Services/         → 业务服务（计费、内容过滤）
app/Jobs/             → 异步任务（图片生成、重试）
public/index.html     → 前端单页应用
resources/views/admin → 管理后台
```

---

## 📡 API

| 方法 | 端点 | 说明 |
|:----:|------|------|
| POST | `/api/register` | 注册 |
| POST | `/api/login` | 登录 |
| GET | `/api/me` | 用户信息（需认证） |
| POST | `/api/generate` | 提交生成任务（需认证） |
| GET | `/api/status` | 查询任务状态（需认证） |
| POST | `/api/redeem` | 兑换码充值（需认证） |
| GET | `/api/templates` | 提示词模板列表 |
| POST | `/api/templates/{id}/build` | 构建模板提示词 |
| GET | `/api/config` | 前端配置 |
| GET | `/api/download` | 图片代理下载 |

---

## 🔧 环境要求

| 组件 | 版本 | 说明 |
|------|------|------|
| PHP | 8.3+ | 扩展：mbstring, xml, ctype, intl, pdo_mysql, bcmath, gd, fileinfo, curl, openssl |
| MySQL | 8.0+ | 或 SQLite |
| Composer | 2.2+ | 脚本自动安装 |
| Web 服务器 | Nginx / Caddy | 宝塔自带 |

> ⚠️ 不需要 Node.js，前端构建产物已包含在仓库中。

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
