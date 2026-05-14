<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装完成 - CANG-AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f0f14; color: #e4e4e7; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 16px; padding: 40px; max-width: 520px; width: 100%; text-align: center; }
        h1 { font-size: 24px; margin-bottom: 12px; color: #4ade80; }
        .info { margin: 24px 0; text-align: left; background: #12121a; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 2; }
        .info strong { color: #aaa; }
        .links { display: flex; gap: 12px; margin-top: 24px; }
        .links a { flex: 1; display: block; padding: 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .links .primary { background: #6366f1; color: #fff; }
        .links .secondary { background: #2a2a3a; color: #e4e4e7; }
    </style>
</head>
<body>
<div class="card">
    <h1>✅ 安装完成</h1>
    <p style="color:#888;">CANG-AI 已成功部署，请记住以下信息：</p>
    <div class="info">
        <div><strong>站点地址：</strong>{{ $site_url }}</div>
        <div><strong>管理后台：</strong>{{ $site_url }}/admin</div>
        <div><strong>管理员邮箱：</strong>{{ $admin_email }}</div>
    </div>
    <p style="color:#f59e0b; font-size:13px; margin-bottom:8px;">⚠️ 请前往后台「AI 渠道」添加至少一个渠道后方可使用生成功能。</p>
    <div class="links">
        <a href="/" class="secondary">访问首页</a>
        <a href="/admin" class="primary">进入后台</a>
    </div>
</div>
</body>
</html>
