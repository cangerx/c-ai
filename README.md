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

## 🚀 一键安装

> 无需命令行，浏览器完成全部配置。

```
1. 上传代码到服务器
2. 浏览器访问 https://your-domain.com/install
3. 完成 ✓
```

**安装向导自动完成：**

- ✅ PHP 环境检测（8 项扩展 + 目录权限）
- ✅ 生成应用密钥
- ✅ 创建数据库 & 运行迁移
- ✅ 创建管理员账号
- ✅ 配置生产环境参数

安装完成后，进入 **管理后台 → AI 渠道** 添加至少一个渠道的 API Key 即可开始使用。

---

## 📦 宝塔面板部署

<table>
<tr><td>1</td><td>新建 PHP 站点，PHP 版本 <strong>8.3+</strong>，数据库选 MySQL</td></tr>
<tr><td>2</td><td>上传代码：<code>git clone https://github.com/cangerx/c-ai.git .</code></td></tr>
<tr><td>3</td><td>网站目录 → 运行目录设为 <code>/public</code></td></tr>
<tr><td>4</td><td>伪静态 → 选择 <code>laravel5</code></td></tr>
<tr><td>5</td><td>PHP 管理 → 安装扩展：<code>fileinfo</code></td></tr>
<tr><td>6</td><td>PHP 管理 → 禁用函数 → 删除：<code>putenv</code> <code>proc_open</code> <code>proc_get_status</code> <code>proc_close</code> <code>symlink</code></td></tr>
<tr><td>7</td><td>终端执行：<code>composer install --no-dev --optimize-autoloader</code></td></tr>
<tr><td>8</td><td>浏览器访问 <code>/install</code> 完成安装向导</td></tr>
<tr><td>9</td><td>宝塔 Supervisor → 添加守护进程：<code>php artisan queue:work --sleep=3 --tries=3</code></td></tr>
<tr><td>10</td><td>SSL → Let's Encrypt 一键申请</td></tr>
<tr><td>11</td><td>管理后台 → AI 渠道 → 添加 API Key</td></tr>
</table>

---

<details>
<summary><strong>🛠 手动部署（开发环境 / 高级用户）</strong></summary>

```bash
git clone https://github.com/cangerx/c-ai.git
cd c-ai
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

访问 `http://localhost:8000`

生产环境额外步骤：
```bash
# Nginx / Caddy 配置参考 deploy/ 目录
# 队列进程管理参考 deploy/supervisor.conf
php artisan config:cache
php artisan route:cache
```

详细部署文档：[deploy/README.md](deploy/README.md)

</details>

---

## 🔄 日常更新

```bash
# 方式一：一键更新脚本
bash update.sh

# 方式二：完整部署脚本（含权限修复）
bash deploy.sh
```

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
