# CANG-AI 宝塔 Docker 一键部署

目标：宝塔只负责域名、SSL 和反向代理；应用、MySQL、Redis、队列和定时任务全部由 Docker 管理。

## 1. 宝塔准备

在宝塔软件商店安装：

- Docker
- Nginx

不需要安装宝塔 PHP、MySQL、Redis。

## 2. 拉取代码

```bash
cd /www/wwwroot
git clone https://github.com/cangerx/c-ai.git cang-ai
cd /www/wwwroot/cang-ai
```

## 3. 一键启动

把域名换成你的真实域名。推荐两个域名：

- 前端：`https://ggx.me`
- 后端/后台：`https://admin.ggx.me`

```bash
APP_URL=https://admin.ggx.me FRONTEND_APP_URL=https://ggx.me FRONTEND_API_URL=https://admin.ggx.me bash docker-install.sh
```

脚本会自动完成：

- 生成 `.env`
- 生成 `APP_KEY`
- 生成 MySQL root 密码和业务数据库密码
- 使用 Docker 启动 Nginx、PHP、MySQL、Redis、Worker、Scheduler
- 使用 Docker 构建并启动前端 Next.js
- 后端默认只监听宿主机 `127.0.0.1:8080`
- 前端默认只监听宿主机 `127.0.0.1:3000`

## 4. 宝塔反向代理

宝塔添加两个站点并申请 SSL，然后设置反向代理。

前端站点 `ggx.me`：

```text
目标 URL: http://127.0.0.1:3000
发送域名: $host
```

后端/后台站点 `admin.ggx.me`：

```text
目标 URL: http://127.0.0.1:8080
发送域名: $host
```

也可以在 Nginx 配置里写：

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## 5. 打开网页安装

访问你的后端域名，会自动进入安装向导：

```text
https://admin.ggx.me/install
```

只需要填写：

- 站点名称
- 站点地址
- 管理员邮箱
- 管理员密码

数据库、Redis、队列都已经由脚本自动配置。

## 常用命令

```bash
cd /www/wwwroot/cang-ai

# 查看服务
bash docker-install.sh status

# 看日志
bash docker-install.sh logs

# 更新
bash docker-install.sh update
```

## 镜像版升级

发布 tag 后，GitHub Actions 会推送镜像到 GHCR。服务器可以不再本地构建：

```bash
cd /www/wwwroot/cang-ai
git pull origin main
IMAGE_TAG=v0.2.6 bash docker-release-update.sh
```

如果 GHCR 包是私有的，先带 token 执行：

```bash
GHCR_USERNAME=你的GitHub用户名 GHCR_TOKEN=你的PAT IMAGE_TAG=v0.2.6 bash docker-release-update.sh
```

注意：Next.js 的 `NEXT_PUBLIC_API_URL` / `NEXT_PUBLIC_APP_URL` 会在镜像构建时固化。
如果你的域名不是默认的 `https://ggx.me` 和 `https://admin.ggx.me`，发布镜像前需要在 GitHub Actions 手动运行工作流并填写：

```text
frontend_api_url=https://你的后端域名
frontend_app_url=https://你的前端域名
image_tag=你的版本号
```

如果前端仓库也有对应 tag，建议同时填写：

```text
frontend_ref=v前端版本号
```

底层等价于：

```bash
IMAGE_TAG=v0.2.6 docker compose -f docker-compose.ghcr.yml pull
IMAGE_TAG=v0.2.6 docker compose -f docker-compose.ghcr.yml up -d
```

## 如果端口冲突

默认反向代理端口是 `127.0.0.1:8080`。如果 8080 被占用：

```bash
HTTP_PORT=127.0.0.1:18080 FRONTEND_PORT=127.0.0.1:13000 APP_URL=https://admin.ggx.me FRONTEND_APP_URL=https://ggx.me FRONTEND_API_URL=https://admin.ggx.me bash docker-install.sh
```

宝塔反向代理目标也对应修改：

```text
前端: http://127.0.0.1:13000
后端: http://127.0.0.1:18080
```

## 备份

建议同时备份这几个目录/文件：

```text
/www/wwwroot/cang-ai/.env
/www/wwwroot/cang-ai/storage
/www/wwwroot/cang-ai/docker-data
```
