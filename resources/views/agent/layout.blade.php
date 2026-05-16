<!doctype html>
<html lang="zh-CN" x-data="{ theme: localStorage.getItem('agent-theme') || 'light' }" :data-theme="theme">
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
            --accent-hover: #1d4ed8;
            --bg: #f5f3f0;
            --panel: #ffffff;
            --panel-hover: #fafaf9;
            --text: #1a1a1e;
            --text-secondary: #71717a;
            --text-muted: #a1a1aa;
            --line: rgba(0, 0, 0, 0.06);
            --line-strong: rgba(0, 0, 0, 0.10);
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.06);
        }
        [data-theme="dark"] {
            --bg: #0f0f12;
            --panel: #1a1a1e;
            --panel-hover: #222226;
            --text: #f0f0f2;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --line: rgba(255, 255, 255, 0.08);
            --line-strong: rgba(255, 255, 255, 0.12);
        }
        [data-theme="dark"] .form-input,
        [data-theme="dark"] .form-select { background: #222226; border-color: rgba(255,255,255,0.1); color: var(--text); }
        [data-theme="dark"] .modal-box { background: var(--panel); color: var(--text); }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: "DM Sans", "Noto Sans SC", -apple-system, sans-serif; background: var(--bg); color: var(--text); -webkit-font-smoothing: antialiased; min-height: 100vh; }
        [x-cloak] { display: none !important; }

        /* Topbar */
        .topbar { background: var(--panel); border-bottom: 1px solid var(--line); padding: 0 24px; height: 56px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .topbar-brand { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
        .topbar-brand span { background: var(--accent); color: #fff; width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .topbar-nav { display: flex; gap: 2px; overflow-x: auto; }
        .topbar-nav a { padding: 8px 12px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500; color: var(--text-secondary); text-decoration: none; transition: all 0.15s; white-space: nowrap; }
        .topbar-nav a:hover { background: var(--accent-soft); color: var(--accent); }
        .topbar-nav a.active { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 12px; font-size: 13px; color: var(--text-secondary); }
        .topbar-right button { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 4px; }

        /* Layout */
        .container { max-width: 1080px; margin: 0 auto; padding: 24px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .page-subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; }

        /* Cards */
        .card { background: var(--panel); border-radius: var(--radius-md); border: 1px solid var(--line); box-shadow: var(--shadow-sm); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 14px; font-weight: 600; }
        .card-body { padding: 20px; }

        /* Stats */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .stat-card { background: var(--panel); border-radius: var(--radius-md); border: 1px solid var(--line); padding: 20px; }
        .stat-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 28px; font-weight: 700; margin-top: 6px; }
        .stat-change { font-size: 12px; margin-top: 4px; }
        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--danger); }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 12px 16px; border-bottom: 1px solid var(--line); }
        td { padding: 14px 16px; font-size: 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 0 18px; height: 38px; border-radius: var(--radius-md); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(45,91,240,0.2); }
        .btn-ghost { background: transparent; color: var(--text-secondary); border-color: var(--line-strong); }
        .btn-ghost:hover { background: var(--panel-hover); color: var(--text); }
        .btn-danger { background: var(--danger); color: #fff; border-color: var(--danger); }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { height: 32px; padding: 0 12px; font-size: 12px; }
        .btn-loading { pointer-events: none; opacity: 0.7; }

        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-info { background: var(--accent-soft); color: var(--accent); }

        /* Forms */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .form-input, .form-select { width: 100%; padding: 9px 12px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); font: inherit; font-size: 13px; background: var(--panel); color: var(--text); outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
        .form-input:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 32px; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(34,197,94,0.08); color: #15803d; border: 1px solid rgba(34,197,94,0.15); }
        .alert-danger { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.15); }
        .alert-warning { background: rgba(245,158,11,0.08); color: #d97706; border: 1px solid rgba(245,158,11,0.15); }

        /* Modal */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 9998; display: flex; align-items: center; justify-content: center; }
        .modal-box { background: var(--panel); border-radius: var(--radius-lg); padding: 28px; max-width: 520px; width: 90%; box-shadow: var(--shadow-md); max-height: 90vh; overflow-y: auto; }
        .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
        .modal-desc { font-size: 14px; color: var(--text-secondary); margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 12px; justify-content: center; }

        /* Toast */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 18px; border-radius: var(--radius-md); font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; animation: toastIn 0.3s ease; box-shadow: var(--shadow-md); }
        .toast.leaving { animation: toastOut 0.3s ease forwards; }
        .toast-success { background: #fff; color: #15803d; border: 1px solid rgba(34,197,94,0.2); }
        .toast-error { background: #fff; color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
        @keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes toastOut { from { opacity: 1; } to { opacity: 0; transform: translateX(20px); } }

        /* Empty state */
        .empty-state { padding: 48px 20px; text-align: center; }
        .empty-state-icon { font-size: 36px; margin-bottom: 12px; }
        .empty-state-text { font-size: 14px; color: var(--text-muted); }

        /* Pagination */
        nav[role="navigation"] { margin-top: 16px; }
        nav[role="navigation"] > div { display: flex; justify-content: center; gap: 4px; flex-wrap: wrap; }
        nav[role="navigation"] > div > div:first-child { display: none; }
        nav[role="navigation"] span, nav[role="navigation"] a { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border-radius: var(--radius-sm); font-size: 13px; text-decoration: none; color: var(--text-secondary); border: 1px solid var(--line); }
        nav[role="navigation"] span[aria-current] { background: var(--accent); color: #fff; border-color: var(--accent); }
        nav[role="navigation"] a:hover { background: var(--accent-soft); color: var(--accent); }
        nav[role="navigation"] span[aria-disabled] { opacity: 0.4; cursor: default; }

        /* Pagination */
        .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 8px; border-radius: var(--radius-sm); font-size: 13px; text-decoration: none; color: var(--text-secondary); border: 1px solid var(--line); }
        .pagination a:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        .pagination span.current { background: var(--accent); color: #fff; border-color: var(--accent); }
        .pagination span.disabled { opacity: 0.4; }

        /* Invite box */
        .invite-box { background: var(--accent-soft); border: 1px dashed var(--accent); border-radius: var(--radius-sm); padding: 14px 18px; font-family: monospace; font-size: 16px; color: var(--accent); font-weight: 700; letter-spacing: 0.05em; display: inline-block; }

        /* Progress bar */
        .progress-bar { position: fixed; top: 0; left: 0; height: 3px; background: var(--accent); z-index: 9999; transition: width 0.3s; }

        /* Responsive */
        @media (max-width: 768px) {
            .topbar { padding: 0 12px; }
            .topbar-nav { gap: 0; }
            .topbar-nav a { padding: 6px 8px; font-size: 12px; }
            .container { padding: 16px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="progress-bar" x-data="{ show: false }" x-show="show" x-cloak id="progressBar" style="width:0;"></div>

    <header class="topbar">
        <a href="{{ route('agent.dashboard') }}" class="topbar-brand">
            <span>A</span>
            代理中心
        </a>
        <nav class="topbar-nav">
            <a href="{{ route('agent.dashboard') }}" class="{{ request()->routeIs('agent.dashboard') ? 'active' : '' }}">概览</a>
            <a href="{{ route('agent.statistics') }}" class="{{ request()->routeIs('agent.statistics') ? 'active' : '' }}">统计</a>
            <a href="{{ route('agent.sub-users') }}" class="{{ request()->routeIs('agent.sub-users*') ? 'active' : '' }}">用户</a>
            <a href="{{ route('agent.redeem-codes') }}" class="{{ request()->routeIs('agent.redeem-codes*') ? 'active' : '' }}">兑换码</a>
            <a href="{{ route('agent.plans') }}" class="{{ request()->routeIs('agent.plans*') ? 'active' : '' }}">套餐</a>
            <a href="{{ route('agent.site-settings') }}" class="{{ request()->routeIs('agent.site-settings*') ? 'active' : '' }}">分站</a>
            <a href="{{ route('agent.transactions') }}" class="{{ request()->routeIs('agent.transactions') ? 'active' : '' }}">流水</a>
            <a href="{{ route('agent.withdrawals') }}" class="{{ request()->routeIs('agent.withdrawals*') ? 'active' : '' }}">提现</a>
        </nav>
        <div class="topbar-right">
            <button @click="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('agent-theme', theme)" title="切换主题">
                <span x-text="theme === 'dark' ? '☀️' : '🌙'"></span>
            </button>
            <span>{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('agent.logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">退出</button>
            </form>
        </div>
    </header>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success" x-data x-init="setTimeout(() => $el.remove(), 4000)">✓ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">✕ {{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">@foreach($errors->all() as $e) {{ $e }} @endforeach</div>
        @endif

        @yield('content')
    </div>

    @stack('modals')

    <!-- Confirm Modal -->
    <div x-data="confirmModal()" @confirm.window="open($event.detail)"
         x-show="show" x-cloak style="display:none;">
        <div class="modal-backdrop" @click.self="cancel()"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="modal-box" style="max-width:380px; text-align:center;"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="modal-title" x-text="title"></div>
                <div class="modal-desc" x-text="message"></div>
                <div class="modal-actions">
                    <button class="btn btn-ghost btn-sm" @click="cancel()">取消</button>
                    <button class="btn btn-danger btn-sm" @click="confirm()">确认</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast-container" x-data="toastManager()" @toast.window="add($event.detail)">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast" :class="'toast-' + toast.type + (toast.leaving ? ' leaving' : '')">
                <span x-text="toast.type === 'success' ? '✓' : '✕'"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js" defer></script>
    <script>
        function confirmModal() {
            return {
                show: false, title: '', message: '', formEl: null,
                open(detail) { this.title = detail.title || '确认操作'; this.message = detail.message || '确定继续？'; this.formEl = detail.form || null; this.show = true; },
                confirm() { this.show = false; if (this.formEl) this.formEl.submit(); },
                cancel() { this.show = false; this.formEl = null; }
            };
        }
        function toastManager() {
            return {
                toasts: [], id: 0,
                add(detail) {
                    const t = { id: ++this.id, type: detail.type || 'success', message: detail.message, leaving: false };
                    this.toasts.push(t);
                    setTimeout(() => { t.leaving = true; setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== t.id); }, 300); }, 2500);
                }
            };
        }
        // Progress bar on navigation
        document.addEventListener('click', e => {
            const a = e.target.closest('a[href]');
            if (a && !a.target && !a.href.startsWith('javascript') && !a.hasAttribute('download')) {
                const bar = document.getElementById('progressBar');
                if (bar) { bar.style.width = '70%'; bar.style.display = 'block'; }
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
