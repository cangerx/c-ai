<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CANG-AI 安装向导</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3f0;
            --panel: rgba(255,255,255,0.72);
            --panel-strong: rgba(255,255,255,0.92);
            --line: rgba(0,0,0,0.06);
            --text: #1a1a1a;
            --muted: #6b7280;
            --accent: #2d5bf0;
            --accent-soft: rgba(45,91,240,0.08);
            --shadow: 0 24px 48px -12px rgba(0,0,0,0.12);
            --shadow-soft: 0 8px 32px -8px rgba(0,0,0,0.08);
            --glass: saturate(1.8) blur(20px);
            --success: #10b981;
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: "Noto Sans SC", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, rgba(45,91,240,0.06), transparent),
                radial-gradient(ellipse 60% 40% at 80% 10%, rgba(249,115,22,0.04), transparent),
                radial-gradient(ellipse 50% 60% at 50% 100%, rgba(45,91,240,0.03), transparent);
            pointer-events: none;
        }
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        .container { position: relative; z-index: 1; width: 100%; max-width: 480px; }
        .card {
            background: var(--panel-strong);
            backdrop-filter: var(--glass);
            -webkit-backdrop-filter: var(--glass);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 44px 36px;
            box-shadow: var(--shadow);
        }
        .logo {
            width: 48px; height: 48px;
            background: var(--accent);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 16px rgba(45,91,240,0.25);
        }
        .logo svg { width: 26px; height: 26px; fill: #fff; }
        h1 { font-family: "Space Grotesk", sans-serif; font-size: 22px; font-weight: 700; text-align: center; letter-spacing: -0.5px; }
        .subtitle { color: var(--muted); font-size: 13px; text-align: center; margin-top: 6px; margin-bottom: 32px; }

        .checks { margin-bottom: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; }
        .check-item {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px;
            font-size: 13px; font-weight: 500;
            border-radius: 10px;
            transition: background 0.15s;
        }
        .check-item:hover { background: var(--accent-soft); }
        @media (max-width: 420px) { .checks { grid-template-columns: 1fr; } }
        .check-icon {
            width: 20px; height: 20px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; flex-shrink: 0;
        }
        .check-ok .check-icon { background: rgba(16,185,129,0.12); color: var(--success); }
        .check-fail .check-icon { background: rgba(239,68,68,0.12); color: var(--danger); }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-input {
            width: 100%; padding: 11px 14px;
            background: rgba(255,255,255,0.6);
            border: 1.5px solid var(--line);
            border-radius: 10px;
            color: var(--text);
            font-size: 14px; font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(45,91,240,0.1);
        }
        .form-input::placeholder { color: #bbb; }

        .btn {
            width: 100%; padding: 13px;
            background: var(--accent);
            color: #fff;
            border: none; border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: inherit;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(45,91,240,0.25);
            margin-top: 8px;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(45,91,240,0.3); }
        .btn:active { transform: translateY(0); }
        .btn:disabled { background: #ccc; box-shadow: none; cursor: not-allowed; }
        .btn-secondary {
            background: var(--panel);
            color: var(--text);
            border: 1.5px solid var(--line);
            box-shadow: var(--shadow-soft);
        }
        .btn-secondary:hover { box-shadow: var(--shadow); }

        .error-box {
            background: rgba(239,68,68,0.06);
            border: 1px solid rgba(239,68,68,0.15);
            color: var(--danger);
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            line-height: 1.6;
        }
        .tip-box {
            background: rgba(249,115,22,0.06);
            border: 1px solid rgba(249,115,22,0.15);
            color: #92400e;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 12px;
            line-height: 1.8;
            text-align: left;
        }
        .tip-box strong { display: block; margin-bottom: 6px; font-size: 13px; }
        .tip-box ul { padding-left: 16px; margin: 0; }
        .tip-box li { margin-bottom: 2px; }
        .tip-box code { background: rgba(0,0,0,0.05); padding: 1px 5px; border-radius: 3px; font-size: 11px; }

        .divider { height: 1px; background: var(--line); margin: 24px 0; }

        @media (max-width: 520px) {
            .card { padding: 32px 24px; border-radius: 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <h1>CANG-AI</h1>
        <p class="subtitle">安装向导 · 环境检测与初始化配置</p>

        <div class="checks">
            @php $allOk = true; @endphp
            @foreach($checks as $check)
                <div class="check-item {{ $check['ok'] ? 'check-ok' : 'check-fail' }}">
                    <span class="check-icon">{{ $check['ok'] ? '✓' : '✗' }}</span>
                    <span>{{ $check['name'] }}</span>
                    @if(!$check['ok']) @php $allOk = false; @endphp @endif
                </div>
            @endforeach
        </div>

        @if(!$allOk)
            <div class="error-box">环境检测未通过，请先修复标红项后刷新重试。</div>
            <div class="tip-box">
                <strong>常见解决方法：</strong>
                <ul>
                    <li><b>PHP 版本不足</b> → 升级到 PHP 8.3+：<code>apt install php8.3</code></li>
                    <li><b>缺少扩展</b> → 安装对应扩展：<code>apt install php8.3-sqlite3 php8.3-gd php8.3-mbstring</code></li>
                    <li><b>目录不可写</b> → 修改权限：<code>chmod -R 775 storage database</code> 并 <code>chown -R www-data:www-data .</code></li>
                    <li><b>.env 不可写</b> → <code>touch .env && chmod 664 .env</code></li>
                </ul>
            </div>
            <button class="btn btn-secondary" onclick="location.reload()">重新检测</button>
        @else
            @if($errors->any())
                <div class="error-box">
                    @foreach($errors->all() as $e)
                        <div>{{ $e }}</div>
                    @endforeach
                </div>
                <div class="tip-box">
                    <strong>安装失败？试试：</strong>
                    <ul>
                        <li><b>数据库迁移失败</b> → 删除 <code>database/database.sqlite</code> 后重试</li>
                        <li><b>邮箱已存在</b> → 换一个管理员邮箱，或删除数据库重装</li>
                        <li><b>权限问题</b> → 确保 <code>storage/</code> 和 <code>database/</code> 目录可写</li>
                        <li><b>仍然失败</b> → 查看 <code>storage/logs/laravel.log</code> 获取详细错误</li>
                    </ul>
                </div>
            @endif

            <div class="divider"></div>

            <form method="POST" action="/install">
                @csrf
                <div class="form-group">
                    <label class="form-label">站点名称</label>
                    <input class="form-input" name="site_name" value="{{ old('site_name', 'CANG-AI') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">站点地址</label>
                    <input class="form-input" name="site_url" value="{{ old('site_url', request()->getSchemeAndHttpHost()) }}" placeholder="https://ai.example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">管理员邮箱</label>
                    <input class="form-input" type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">管理员密码</label>
                    <input class="form-input" type="password" name="admin_password" placeholder="至少 6 位" required minlength="6">
                </div>
                <button type="submit" class="btn">开始安装</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
