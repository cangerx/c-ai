<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 — {{ config('app.name', 'CANG-AI') }}</title>
    <link href="https://fonts.googleapis.cn/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "DM Sans", "Noto Sans SC", sans-serif;
            background: #f5f3f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 30% 20%, rgba(45, 91, 240, 0.06), transparent),
                radial-gradient(ellipse 50% 40% at 70% 80%, rgba(249, 115, 22, 0.04), transparent);
            pointer-events: none;
        }
        .login-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            padding: 0 20px;
            animation: fadeUp 0.5s ease both;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #1a1a1e;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 16px;
        }
        .login-brand h1 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1e;
        }
        .login-brand p {
            font-size: 14px;
            color: #71717a;
            margin-top: 4px;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 12px 40px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.03);
            padding: 32px;
        }
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1a1a1e;
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid rgba(0,0,0,0.10);
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-input:focus {
            border-color: #2d5bf0;
            box-shadow: 0 0 0 3px rgba(45, 91, 240, 0.10);
        }
        .btn-login {
            width: 100%;
            height: 44px;
            border: none;
            border-radius: 12px;
            background: #1a1a1e;
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-login:hover {
            background: #2d5bf0;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(45, 91, 240, 0.25);
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
            border: 1px solid rgba(239, 68, 68, 0.12);
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-brand">
            <div class="login-logo">C</div>
            <h1>CANG-AI</h1>
            <p>管理后台</p>
        </div>
        <div class="login-card">
            @if($errors->any())
                <div class="error-msg">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="admin@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input class="form-input" type="password" name="password" required placeholder="••••••••">
                </div>
                <button class="btn-login" type="submit">登录</button>
            </form>
        </div>
    </div>
</body>
</html>
