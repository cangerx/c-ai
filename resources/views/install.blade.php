<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CANG-AI 安装向导</title>
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
            --danger: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
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
        .container { position: relative; z-index: 1; width: 100%; max-width: 520px; }
        .card {
            background: var(--panel-strong);
            backdrop-filter: var(--glass);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 40px 36px;
            box-shadow: var(--shadow);
        }
        .logo { width: 48px; height: 48px; background: var(--accent); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 4px 16px rgba(45,91,240,0.25); }
        .logo svg { width: 26px; height: 26px; fill: #fff; }
        h1 { font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; font-size: 22px; font-weight: 700; text-align: center; letter-spacing: -0.5px; }
        .subtitle { color: var(--muted); font-size: 13px; text-align: center; margin-top: 6px; margin-bottom: 28px; }

        /* Steps indicator */
        .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 28px; }
        .steps .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--line); transition: all 0.3s; }
        .steps .dot.active { background: var(--accent); width: 24px; border-radius: 4px; }
        .steps .dot.done { background: var(--success); }

        /* Checks */
        .checks { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; margin-bottom: 24px; }
        .check-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; font-size: 13px; }
        .check-item.pass { color: var(--success); background: rgba(16,185,129,0.05); }
        .check-item.fail { color: var(--danger); background: rgba(239,68,68,0.05); }

        /* Form */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--muted); margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--line); border-radius: 10px;
            font-size: 14px; font-family: inherit;
            background: rgba(255,255,255,0.6);
            transition: border-color 0.2s;
            outline: none;
        }
        .form-input:focus, .form-select:focus { border-color: var(--accent); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .btn {
            width: 100%; padding: 14px;
            background: var(--accent); color: #fff;
            border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600; font-family: inherit;
            cursor: pointer; margin-top: 8px;
            box-shadow: 0 4px 16px rgba(45,91,240,0.25);
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(45,91,240,0.3); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary { background: transparent; color: var(--accent); border: 1.5px solid var(--accent); box-shadow: none; }
        .btn-secondary:hover { background: rgba(45,91,240,0.04); transform: none; box-shadow: none; }

        .error-box { background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #b91c1c; margin-bottom: 16px; }
        .success-box { background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.15); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #065f46; margin-bottom: 16px; }
        .divider { height: 1px; background: var(--line); margin: 24px 0; }
        .section-title { font-size: 14px; font-weight: 600; margin-bottom: 16px; }

        .step-panel { display: none; }
        .step-panel.active { display: block; }

        .db-toggle { display: flex; gap: 8px; margin-bottom: 16px; }
        .db-toggle label { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border: 1.5px solid var(--line); border-radius: 10px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .db-toggle input { display: none; }
        .db-toggle input:checked + label { border-color: var(--accent); background: rgba(45,91,240,0.04); color: var(--accent); }

        .mysql-fields { transition: opacity 0.2s; }
        .mysql-fields.hidden { display: none; }

        @media (max-width: 520px) {
            .card { padding: 32px 24px; }
            .form-row { grid-template-columns: 1fr; }
            .checks { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <svg viewBox="0 0 24 24"><path d="m21 15-3.086-8.45a.611.611 0 0 0-.572-.4h-.001a.611.611 0 0 0-.57.4L13.688 15m7.312 0h-7.312M10 15l-3.086-8.45a.611.611 0 0 0-.572-.4h-.001a.611.611 0 0 0-.57.4L2.688 15M10 15H2.688M2 20h20"/></svg>
        </div>
        <h1>CANG-AI 安装向导</h1>
        <p class="subtitle">三步完成部署</p>

        <div class="steps">
            <div class="dot" id="dot1"></div>
            <div class="dot" id="dot2"></div>
            <div class="dot" id="dot3"></div>
        </div>

        @if(!empty($error))
            <div class="error-box">{{ $error }}</div>
        @endif

        <!-- Step 1: 环境检测 -->
        <div class="step-panel" id="step1">
            <div class="section-title">环境检测</div>
            <div class="checks">
                @foreach($checks ?? [] as $check)
                    <div class="check-item {{ $check['ok'] ? 'pass' : 'fail' }}">
                        <span>{{ $check['ok'] ? '✓' : '✗' }}</span>
                        <span>{{ $check['name'] }}</span>
                    </div>
                @endforeach
            </div>
            @php $allPass = collect($checks ?? [])->every(fn($c) => $c['ok']); @endphp
            @if(!$allPass)
                <div class="error-box">环境检测未通过，请先修复标红项。</div>
                <button class="btn btn-secondary" onclick="location.reload()">重新检测</button>
            @else
                <div class="success-box">✓ 环境检测通过</div>
                <button class="btn" onclick="goStep(2)">下一步：配置数据库</button>
            @endif
        </div>

        <!-- Step 2: 数据库配置 -->
        <div class="step-panel" id="step2">
            <div class="section-title">数据库配置</div>

            <div class="db-toggle">
                <input type="radio" name="db_type" id="db_mysql" value="mysql" checked>
                <label for="db_mysql">MySQL</label>
                <input type="radio" name="db_type" id="db_sqlite" value="sqlite">
                <label for="db_sqlite">SQLite</label>
            </div>

            <div class="mysql-fields" id="mysqlFields">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">主机地址</label>
                        <input class="form-input" id="db_host" value="127.0.0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">端口</label>
                        <input class="form-input" id="db_port" value="3306">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">数据库名</label>
                    <input class="form-input" id="db_database" placeholder="cang_ai">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">用户名</label>
                        <input class="form-input" id="db_username" value="root">
                    </div>
                    <div class="form-group">
                        <label class="form-label">密码</label>
                        <input class="form-input" type="password" id="db_password">
                    </div>
                </div>
            </div>

            <div id="dbTestResult"></div>
            <button class="btn" id="testDbBtn" onclick="testDatabase()">测试连接</button>
            <button class="btn btn-secondary" style="margin-top:8px" onclick="goStep(1)">上一步</button>
        </div>

        <!-- Step 3: 站点配置 -->
        <div class="step-panel" id="step3">
            <div class="section-title">站点配置</div>
            <form method="POST" action="/install" id="installForm">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="db_connection" id="f_db_connection" value="mysql">
                <input type="hidden" name="db_host" id="f_db_host">
                <input type="hidden" name="db_port" id="f_db_port">
                <input type="hidden" name="db_database" id="f_db_database">
                <input type="hidden" name="db_username" id="f_db_username">
                <input type="hidden" name="db_password" id="f_db_password">

                <div class="form-group">
                    <label class="form-label">站点名称</label>
                    <input class="form-input" name="site_name" value="CANG-AI" required>
                </div>
                <div class="form-group">
                    <label class="form-label">站点地址</label>
                    <input class="form-input" name="site_url" value="{{ request()->getSchemeAndHttpHost() }}" required>
                </div>
                <div class="divider"></div>
                <div class="section-title">管理员账号</div>
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input class="form-input" type="email" name="admin_email" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input class="form-input" type="password" name="admin_password" placeholder="至少 6 位" required minlength="6">
                </div>
                <button type="submit" class="btn" id="submitBtn">开始安装</button>
                <button type="button" class="btn btn-secondary" style="margin-top:8px" onclick="goStep(2)">上一步</button>
            </form>
        </div>
    </div>
</div>

<script>
let currentStep = {{ $step ?? 1 }};

function goStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('step' + n).classList.add('active');
    document.querySelectorAll('.steps .dot').forEach((d, i) => {
        d.className = 'dot' + (i + 1 < n ? ' done' : '') + (i + 1 === n ? ' active' : '');
    });
    currentStep = n;
}

function testDatabase() {
    const btn = document.getElementById('testDbBtn');
    const result = document.getElementById('dbTestResult');
    const dbType = document.querySelector('input[name="db_type"]:checked').value;
    btn.disabled = true;
    btn.textContent = '测试中...';
    result.innerHTML = '';

    const body = { db_connection: dbType, _token: '{{ csrf_token() }}' };
    if (dbType === 'mysql') {
        body.db_host = document.getElementById('db_host').value;
        body.db_port = document.getElementById('db_port').value;
        body.db_database = document.getElementById('db_database').value;
        body.db_username = document.getElementById('db_username').value;
        body.db_password = document.getElementById('db_password').value;
    }

    fetch('/install/test-db', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '测试连接';
        if (data.ok) {
            result.innerHTML = '<div class="success-box" style="margin-top:12px">✓ ' + data.msg + '</div>';
            // 填充隐藏字段并跳到下一步
            document.getElementById('f_db_connection').value = dbType;
            if (dbType === 'mysql') {
                document.getElementById('f_db_host').value = document.getElementById('db_host').value;
                document.getElementById('f_db_port').value = document.getElementById('db_port').value;
                document.getElementById('f_db_database').value = document.getElementById('db_database').value;
                document.getElementById('f_db_username').value = document.getElementById('db_username').value;
                document.getElementById('f_db_password').value = document.getElementById('db_password').value;
            }
            setTimeout(() => goStep(3), 600);
        } else {
            result.innerHTML = '<div class="error-box" style="margin-top:12px">✗ ' + data.msg + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = '测试连接';
        result.innerHTML = '<div class="error-box" style="margin-top:12px">请求失败，请检查网络</div>';
    });
}

// DB type toggle
document.querySelectorAll('input[name="db_type"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('mysqlFields').classList.toggle('hidden', r.value === 'sqlite' && r.checked);
    });
});

// Submit loading
document.getElementById('installForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '安装中，请稍候...';
});

goStep(currentStep);
</script>
</body>
</html>
