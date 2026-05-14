<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装完成 - CANG-AI</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3f0;
            --panel-strong: rgba(255,255,255,0.92);
            --line: rgba(0,0,0,0.06);
            --text: #1a1a1a;
            --muted: #6b7280;
            --accent: #2d5bf0;
            --shadow: 0 24px 48px -12px rgba(0,0,0,0.12);
            --glass: saturate(1.8) blur(20px);
            --success: #10b981;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Noto Sans SC", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed; inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, rgba(45,91,240,0.06), transparent),
                radial-gradient(ellipse 60% 40% at 80% 10%, rgba(249,115,22,0.04), transparent);
            pointer-events: none;
        }
        .container { position: relative; z-index: 1; width: 100%; max-width: 480px; }
        .card {
            background: var(--panel-strong);
            backdrop-filter: var(--glass);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 44px 36px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .success-icon {
            width: 56px; height: 56px;
            background: rgba(16,185,129,0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
        }
        h1 { font-family: "Space Grotesk", sans-serif; font-size: 22px; font-weight: 700; color: var(--success); }
        .subtitle { color: var(--muted); font-size: 13px; margin-top: 6px; margin-bottom: 28px; }
        .info {
            text-align: left;
            background: rgba(255,255,255,0.5);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 13px;
            line-height: 2.2;
        }
        .info strong { color: var(--muted); font-weight: 600; }
        .info code { background: rgba(45,91,240,0.06); padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .tip {
            margin-top: 20px;
            padding: 12px 16px;
            background: rgba(249,115,22,0.06);
            border: 1px solid rgba(249,115,22,0.12);
            border-radius: 10px;
            font-size: 12px;
            color: #b45309;
            text-align: left;
        }
        .links { display: flex; gap: 12px; margin-top: 28px; }
        .links a {
            flex: 1; display: block; padding: 13px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600; font-size: 14px; font-family: inherit;
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .links .primary {
            background: var(--accent); color: #fff;
            box-shadow: 0 4px 16px rgba(45,91,240,0.25);
        }
        .links .primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(45,91,240,0.3); }
        .links .secondary {
            background: rgba(255,255,255,0.6); color: var(--text);
            border: 1.5px solid var(--line);
        }
        .links .secondary:hover { background: rgba(255,255,255,0.9); }
        @media (max-width: 520px) { .card { padding: 32px 24px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="success-icon">✓</div>
        <h1>安装完成</h1>
        <p class="subtitle">CANG-AI 已成功部署并准备就绪</p>

        <div class="info">
            <div><strong>站点地址</strong> <code>{{ $site_url }}</code></div>
            <div><strong>管理后台</strong> <code>{{ $site_url }}/admin</code></div>
            <div><strong>管理员</strong> <code>{{ $admin_email }}</code></div>
        </div>

        <div class="tip">
            ⚠️ 请前往后台「AI 渠道」添加至少一个渠道，配置 API Key 后方可使用生成功能。
        </div>

        <div class="links">
            <a href="/" class="secondary">访问首页</a>
            <a href="/admin" class="primary">进入后台</a>
        </div>
    </div>
</div>
</body>
</html>
