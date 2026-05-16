<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>维护中 - CANG-AI</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Noto Sans SC",-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f3f0;color:#1a1a1a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;-webkit-font-smoothing:antialiased}
        .card{background:rgba(255,255,255,0.92);backdrop-filter:saturate(1.8) blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:20px;padding:48px 36px;box-shadow:0 24px 48px -12px rgba(0,0,0,0.12);text-align:center;max-width:420px;width:100%}
        .icon{font-size:48px;margin-bottom:16px}
        h1{font-size:20px;font-weight:700;margin-bottom:8px}
        p{color:#6b7280;font-size:14px;margin-bottom:24px;line-height:1.6}
        .refresh{display:inline-block;padding:12px 28px;background:#2d5bf0;color:#fff;border-radius:12px;text-decoration:none;font-weight:600;font-size:14px;border:none;cursor:pointer;box-shadow:0 4px 16px rgba(45,91,240,0.25)}
    </style>
</head>
<body>
<div class="card">
    <div class="icon">🔧</div>
    <h1>系统维护中</h1>
    <p>我们正在进行系统升级，预计很快恢复。<br>给您带来不便，敬请谅解。</p>
    <button class="refresh" onclick="location.reload()">刷新重试</button>
</div>
</body>
</html>
