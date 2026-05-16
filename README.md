# CANG-AI 绘图

> AI 智能绘画平台 — 一键生成高质量图片

基于 Laravel 13 + 纯前端单页构建的 AI 图像生成平台，支持多模型渠道、积分计费、分销推广、任务队列异步生成。

## 预览

| 功能 | 说明 |
|------|------|
| 🎨 文生图 | 输入提示词，AI 生成高质量图片 |
| ✏️ 图生图 | 上传参考图 + 提示词，编辑生成 |
| 🔄 多渠道 | 支持 GPT-Image、DALL·E 等多个 AI 模型 |
| 💰 积分系统 | 按尺寸/质量灵活计费，兑换码充值 |
| 👥 分销推广 | 邀请注册返佣，多级分销 |
| 📱 响应式 | PC / 平板 / 手机全适配 |
| 🛡️ 内容安全 | 提示词过滤 + 图片审核 |

## 技术栈

- **后端**：PHP 8.3 + Laravel 13 + Sanctum 认证
- **前端**：原生 HTML/CSS/JS 单页应用（零框架依赖）
- **数据库**：MySQL 8.0 / SQLite
- **队列**：Database Queue + Supervisor
- **登录**：邮箱注册 / GitHub OAuth / 微信扫码

## 快速开始

```bash
# 克隆
git clone https://github.com/cangerx/c-ai.git
cd c-ai

# 安装依赖
composer install

# 环境配置
cp .env.example .env
php artisan key:generate

# 数据库
php artisan migrate
php artisan db:seed

# 启动
php artisan serve
```

访问 `http://localhost:8000`

默认管理员：`admin@cang-ai.com`（密码通过 `ADMIN_PASSWORD` 环境变量设置，默认 `ChangeMe!2024`）

## 项目结构

```
├── app/
│   ├── Apps/ImageGen/       # 图像生成模块（控制器、路由、视图）
│   ├── Http/Controllers/
│   │   ├── Admin/           # 管理后台
│   │   └── Api/             # API 接口（认证、生成、用户）
│   ├── Jobs/                # 异步任务（图片生成）
│   ├── Models/              # 数据模型
│   └── Services/            # 业务服务（计费、内容过滤）
├── database/
│   ├── migrations/          # 数据库迁移
│   └── seeders/             # 数据填充
├── deploy/                  # 部署配置（Nginx、Supervisor、脚本）
├── public/
│   └── index.html           # 前端单页应用
├── resources/views/
│   └── admin/               # 管理后台视图
└── routes/
    ├── api.php              # API 路由
    ├── admin.php            # 后台路由
    └── web.php              # Web 路由
```

## 核心功能

### AI 图像生成
- 支持多渠道配置（API Key、模型、权重轮询）
- 文生图 / 图生图两种模式
- 多尺寸（1:1、16:9、9:16 等）
- 异步队列处理，失败自动重试
- 任务状态实时轮询

### 积分计费
- 按图片尺寸 + 质量等级差异化定价
- 兑换码批量生成与充值
- 生成失败自动退款
- 使用记录完整追溯

### 分销系统
- 邀请码注册绑定
- 邀请人数统计
- 佣金自动结算
- 佣金明细查询

### 管理后台
- 用户管理（积分调整、状态控制）
- AI 渠道管理（增删改查、启停）
- 任务监控（状态、重试、详情）
- 兑换码管理（批量生成、导出）
- 系统设置（站点名称、公告、登录方式）
- 佣金管理

## 部署

详见 [deploy/README.md](deploy/README.md)

**环境要求：**
- PHP 8.3+（含 mbstring, gd, curl, fileinfo 扩展）
- MySQL 8.0+ 或 SQLite
- Composer 2.x
- Supervisor（队列进程管理）

**生产环境关键配置：**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
QUEUE_CONNECTION=database
```

### 宝塔面板部署

1. **创建站点**
   - 新建 PHP 站点，域名填写你的域名
   - PHP 版本选择 8.3+
   - 数据库选 MySQL 8.0

2. **上传代码**
   ```bash
   cd /www/wwwroot/your-domain.com
   git clone https://github.com/cangerx/c-ai.git .
   ```

3. **站点设置**
   - 网站目录 → 运行目录设为 `/public`
   - 伪静态 → 选择 `laravel5`
   - PHP 设置 → 安装扩展：fileinfo、redis（可选）

4. **安装依赖**
   ```bash
   cd /www/wwwroot/your-domain.com
   composer install --no-dev --optimize-autoloader
   cp .env.example .env
   php artisan key:generate
   ```

5. **配置 .env**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=cang_ai
   DB_USERNAME=cang_ai
   DB_PASSWORD=你的数据库密码

   QUEUE_CONNECTION=database
   ```

6. **初始化数据库**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   php artisan storage:link
   ```

7. **目录权限**
   ```bash
   chown -R www:www storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

8. **队列 Worker（宝塔 Supervisor）**
   - 宝塔 → 软件商店 → 安装 Supervisor
   - 添加守护进程：
     - 名称：`cang-ai-queue`
     - 运行目录：`/www/wwwroot/your-domain.com`
     - 启动命令：`php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
     - 进程数：`2`

9. **SSL 证书**
   - 宝塔 → 站点 → SSL → Let's Encrypt 一键申请

10. **验证**
    - 访问 `https://your-domain.com` 看到绘图首页
    - 访问 `https://your-domain.com/admin` 进入管理后台
    - 管理员账号：`admin@cang-ai.com`

## API 接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/register` | 注册 |
| POST | `/api/login` | 登录 |
| GET | `/api/me` | 用户信息 |
| POST | `/api/generate` | 提交生成任务 |
| GET | `/api/tasks/{id}` | 查询任务状态 |
| GET | `/api/usage-history` | 使用记录 |
| POST | `/api/redeem` | 兑换码充值 |
| GET | `/api/gallery` | 公开画廊 |

## License

MIT
