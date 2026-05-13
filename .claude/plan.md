# 后台 UI 优化计划

## 优化范围

在现有 Blade + Alpine.js + 自定义 CSS 架构上增量优化，不引入新框架。

---

## 1. 仪表盘数据可视化

**改动文件：**
- `app/Http/Controllers/Admin/DashboardController.php` — 增加近7天每日生成量数据
- `resources/views/admin/dashboard.blade.php` — 加入趋势图

**方案：** 引入 Chart.js CDN（轻量 ~60KB），在仪表盘加一个「近7天生成趋势」折线图卡片。

---

## 2. 暗色模式

**改动文件：**
- `resources/views/admin/layouts/app.blade.php` — 加 `[data-theme="dark"]` CSS 变量覆盖 + 切换按钮

**方案：** 在侧边栏底部加一个日/月切换按钮，用 localStorage 持久化，通过 `document.documentElement.dataset.theme` 切换。

---

## 3. 表格移动端适配

**改动文件：**
- `resources/views/admin/layouts/app.blade.php` — 给 `.table-wrap` 加横向滚动样式

**方案：** 小屏下表格容器加 `overflow-x: auto` + 滚动阴影提示，避免内容溢出。

---

## 4. 骨架屏 / 页面切换过渡

**改动文件：**
- `resources/views/admin/layouts/app.blade.php` — 加顶部进度条

**方案：** 加一个简单的顶部 loading bar（纯 CSS + 少量 JS），在链接点击时触发，页面加载完消失。类似 NProgress 效果但无依赖。

---

## 不做的事

- 不引入 Tailwind 到后台（当前自定义 CSS 体系完整，混用会乱）
- 不做批量操作（功能变更，不属于 UI 优化）
- 不做 SSE 实时更新（需要后端改动较大）

---

## 执行顺序

1. 暗色模式（layout 改动，影响全局）
2. 表格移动端 + 顶部进度条（layout 小改）
3. 仪表盘图表（独立页面改动）
