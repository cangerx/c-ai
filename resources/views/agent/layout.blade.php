<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '代理中心') — {{ config('app.name', 'CANG-AI') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Noto+Sans+SC:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #2d5bf0;
            --accent-soft: rgba(45, 91, 240, 0.10);
            --bg: #f5f3f0;
            --panel: #ffffff;
            --text: #1a1a1e;
            --text-secondary: #71717a;
            --text-muted: #a1a1aa;
            --line: rgba(0, 0, 0, 0.06);
            --line-strong: rgba(0, 0, 0, 0.10);
            --success: #22c55e;
            --danger: #ef4444;
            --radius-md: 14px;
            --radius-sm: 10px;
            --shadow-md: 0 4px 20px rgba(0,0,0,0.06);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "DM Sans", "Noto Sans SC", sans-serif; background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; }
        .topbar { background: var(--panel); border-bottom: 1px solid var(--line); padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .topbar-brand { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .topbar-brand span { background: var(--accent); color: #fff; width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .topbar-nav { display: flex; gap: 4px; }
        .topbar-nav a { padding: 8px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text-secondary); text-decoration: none; transition: all 0.15s; }
        .topbar-nav a:hover { background: var(--accent-soft); color: var(--accent); }
        .topbar-nav a.active { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--text-secondary); }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .page-subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; }
        .card { background: var(--panel); border-radius: var(--radius-md); border: 1px solid var(--line); box-shadow: var(--shadow-md); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 14px; font-weight: 600; }
        .card-body { padding: 20px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card { background: var(--panel); border-radius: var(--radius-md); border: 1px solid var(--line); padding: 20px; }
        .stat-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 28px; font-weight: 700; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--line); }
        td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 16px; border-radius: var(--radius-sm); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-sm { height: 30px; padding: 0 10px; font-size: 12px; }
        .btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--line-strong); }
        .form-input { width: 100%; padding: 8px 12px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); font: inherit; font-size: 13px; outline: none; }
        .form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .alert { padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(34, 197, 94, 0.08); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.15); }
        .alert-danger { background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.15); }
        .invite-box { background: var(--accent-soft); border: 1px dashed var(--accent); border-radius: var(--radius-sm); padding: 14px 18px; font-family: monospace; font-size: 16px; color: var(--accent); font-weight: 700; letter-spacing: 0.05em; display: inline-block; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-brand">
            <span>A</span>
            CANG-AI 代理中心
        </div>
        <nav class="topbar-nav">
            <a href="{{ route('agent.dashboard') }}" class="{{ request()->routeIs('agent.dashboard') ? 'active' : '' }}">概览</a>
            <a href="{{ route('agent.sub-users') }}" class="{{ request()->routeIs('agent.sub-users*') ? 'active' : '' }}">子用户</a>
        </nav>
        <div class="topbar-right">
            {{ auth()->user()->name }}
            <form method="POST" action="{{ route('agent.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">退出</button>
            </form>
        </div>
    </header>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">@foreach($errors->all() as $e) {{ $e }} @endforeach</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
