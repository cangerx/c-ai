# CANG-AI 宝塔面板部署指南

## 环境要求

- PHP >= 8.3（需开启扩展：fileinfo, gd, sqlite3, pdo_sqlite）
- Composer 2.x
- Node.js >= 18（如需编译前端资源）
- SQLite 3
- Supervisor（队列进程守护）

---

## 一、宝塔面板安装

```bash
# CentOS
yum install -y wget && wget -O install.sh https://download.bt.cn/install/install_6.0.sh && sh install.sh ed8484bec

# Ubuntu/Debian
wget -O install.sh https://download.bt.cn/install/install-ubuntu_6.0.sh && sudo bash install.sh ed8484bec
```

安装完成后记录面板地址、用户名、密码。

---

## 二、宝塔面板内安装软件

进入宝塔面板 → 软件商店，安装：

1. **Nginx**（任意版本）
2. **PHP 8.3**
3. **Supervisor 管理器**

### PHP 扩展配置

宝塔面板 → 软件商店 → PHP 8.3 → 设置 → 安装扩展：

- `fileinfo` ✅
- `sqlite3` ✅（通常默认已有）
- `gd` ✅

禁用函数中移除：`putenv`, `proc_open`（Composer 和 Artisan 需要）

---

## 三、创建站点

1. 宝塔面板 → 网站 → 添加站点
2. 域名填写你的域名（如 `ai.example.com`）
3. 根目录设为：`/www/wwwroot/cang-ai/public`
4. PHP 版本选 8.3
5. 不创建数据库（项目用 SQLite）

---

## 四、上传代码

```bash
cd /www/wwwroot/
git clone <你的仓库地址> cang-ai
# 或者直接上传压缩包解压
```

---

## 五、项目初始化

```bash
cd /www/wwwroot/cang-ai

# 安装依赖
composer install --no-dev --optimize-autoloader

# 复制环境配置
cp .env.example .env

# 生成密钥
php artisan key:generate

# 创建 SQLite 数据库文件
touch database/database.sqlite

# 运行迁移
php artisan migrate --force

# 创建存储软链接
php artisan storage:link

# 目录权限
chown -R www:www /www/wwwroot/cang-ai
chmod -R 755 storage bootstrap/cache database
```

---

## 六、编辑 .env 生产配置

```bash
vi /www/wwwroot/cang-ai/.env
```

关键修改：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ai.example.com

DB_CONNECTION=sqlite
DB_DATABASE=/www/wwwroot/cang-ai/database/database.sqlite

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

---

## 七、Nginx 伪静态配置

宝塔面板 → 网站 → 你的站点 → 设置 → 伪静态，填入：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

或在配置文件中确认 `root` 指向 `/www/wwwroot/cang-ai/public`。

---

## 八、SSL 证书

宝塔面板 → 网站 → 站点设置 → SSL → Let's Encrypt → 申请证书 → 开启强制 HTTPS。

---

## 九、队列进程（Supervisor）

宝塔面板 → 软件商店 → Supervisor 管理器 → 添加守护进程：

| 字段 | 值 |
|------|-----|
| 名称 | cang-ai-queue |
| 启动命令 | `/www/server/php/83/bin/php /www/wwwroot/cang-ai/artisan queue:work --sleep=3 --tries=5 --timeout=420 --max-jobs=100` |
| 运行目录 | `/www/wwwroot/cang-ai` |
| 进程数量 | 2 |
| 启动用户 | www |

---

## 十、定时任务（Cron）

宝塔面板 → 计划任务 → 添加：

| 字段 | 值 |
|------|-----|
| 任务类型 | Shell 脚本 |
| 任务名称 | cang-ai-schedule |
| 执行周期 | 每 1 分钟 |
| 脚本内容 | `cd /www/wwwroot/cang-ai && /www/server/php/83/bin/php artisan schedule:run >> /dev/null 2>&1` |

这会自动执行每日 03:00 的过期图片清理任务。

---

## 十一、创建管理员

```bash
cd /www/wwwroot/cang-ai
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'admin@example.com')->first();
$user->is_admin = true;
$user->save();
```

或注册后直接改数据库：

```bash
sqlite3 database/database.sqlite "UPDATE users SET is_admin=1 WHERE email='admin@example.com';"
```

---

## 十二、验证部署

1. 访问 `https://ai.example.com` — 应看到首页
2. 访问 `https://ai.example.com/admin` — 管理后台
3. 管理后台 → AI 渠道 → 添加渠道（填入 base_url 和 api_key）
4. 管理后台 → 站点设置 → 模型设置 → 配置提示词工具模型

---

## 常见问题

**Q: 500 错误**
```bash
cat /www/wwwroot/cang-ai/storage/logs/laravel.log | tail -50
```

**Q: 队列任务不执行**
检查 Supervisor 进程是否运行中，日志在宝塔 Supervisor 管理器内查看。

**Q: 图片上传失败**
```bash
chmod -R 775 /www/wwwroot/cang-ai/storage
chown -R www:www /www/wwwroot/cang-ai/storage
```

**Q: Composer 报内存不足**
```bash
php -d memory_limit=-1 /usr/local/bin/composer install --no-dev
```
