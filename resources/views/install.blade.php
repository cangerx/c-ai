<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CANG-AI 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f0f14; color: #e4e4e7; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { width: 100%; max-width: 520px; }
        .card { background: #1a1a24; border: 1px solid #2a2a3a; border-radius: 16px; padding: 40px; }
        h1 { font-size: 24px; margin-bottom: 8px; text-align: center; }
        .subtitle { color: #888; font-size: 14px; text-align: center; margin-bottom: 32px; }
        .checks { margin-bottom: 28px; }
        .check-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 14px; border-bottom: 1px solid #222233; }
        .check-item:last-child { border-bottom: none; }
        .check-ok { color: #4ade80; }
        .check-fail { color: #f87171; }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; color: #aaa; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; background: #12121a; border: 1px solid #333; border-radius: 8px; color: #e4e4e7; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: #6366f1; }
        .btn { width: 100%; padding: 12px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #4f46e5; }
        .btn:disabled { background: #444; cursor: not-allowed; }
        .error { background: #2d1b1b; border: 1px solid #5c2a2a; color: #f87171; padding: 12px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🚀 CANG-AI 安装向导</h1>
        <p class="subtitle">首次部署请完成以下配置</p>

        <div class="checks">
            @php $allOk = true; @endphp
            @foreach($checks as $check)
                <div class="check-item">
                    <span class="{{ $check['ok'] ? 'check-ok' : 'check-fail' }}">{{ $check['ok'] ? '✓' : '✗' }}</span>
                    <span>{{ $check['name'] }}</span>
                    @if(!$check['ok']) @php $allOk = false; @endphp @endif
                </div>
            @endforeach
        </div>

        @if(!$allOk)
            <div class="error">环境检测未通过，请先修复上方标红项后刷新页面。</div>
            <button class="btn" onclick="location.reload()">重新检测</button>
        @else
            @if($errors->any())
                <div class="error">
                    @foreach($errors->all() as $e)
                        <div>{{ $e }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/install">
                @csrf
                <div class="form-group">
                    <label class="form-label">站点名称</label>
                    <input class="form-input" name="site_name" value="{{ old('site_name', 'CANG-AI') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">站点地址（含 https://）</label>
                    <input class="form-input" name="site_url" value="{{ old('site_url', request()->getSchemeAndHttpHost()) }}" placeholder="https://ai.example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">管理员邮箱</label>
                    <input class="form-input" type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">管理员密码</label>
                    <input class="form-input" type="password" name="admin_password" placeholder="至少6位" required minlength="6">
                </div>
                <button type="submit" class="btn">开始安装</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
