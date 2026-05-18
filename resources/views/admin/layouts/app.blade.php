<!doctype html>
<html lang="zh-CN" :data-theme="theme" x-data="{ theme: localStorage.getItem('admin-theme') || 'light' }" x-init="$watch('theme', v => localStorage.setItem('admin-theme', v))"
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理后台') — {{ config('app.name', 'CANG-AI') }}</title>
    <style>
        :root {
            --accent: #2d5bf0;
            --accent-soft: rgba(45, 91, 240, 0.10);
            --accent-hover: #1d4ed8;
            --bg: #f5f3f0;
            --panel: #ffffff;
            --panel-hover: #fafaf9;
            --sidebar-bg: #1a1a1e;
            --sidebar-text: #a1a1aa;
            --sidebar-text-active: #ffffff;
            --sidebar-item-hover: rgba(255,255,255,0.06);
            --sidebar-item-active: rgba(45, 91, 240, 0.15);
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
            --radius-xl: 20px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.04), 0 2px 6px rgba(0,0,0,0.03);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.03);
            --sidebar-width: 260px;
            --topbar-height: 0px;
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
            --accent-soft: rgba(45, 91, 240, 0.15);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.2);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.3);
            --shadow-lg: 0 12px 40px rgba(0,0,0,0.4);
        }

        [data-theme="dark"] body::before {
            background:
                radial-gradient(ellipse 80% 50% at 60% 0%, rgba(45, 91, 240, 0.06), transparent),
                radial-gradient(ellipse 60% 40% at 20% 80%, rgba(249, 115, 22, 0.04), transparent);
        }

        [data-theme="dark"] .form-input,
        [data-theme="dark"] .form-select {
            background: #222226;
            border-color: rgba(255,255,255,0.1);
            color: var(--text);
        }

        [data-theme="dark"] .modal-box {
            background: var(--panel);
            color: var(--text);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 60% 0%, rgba(45, 91, 240, 0.04), transparent),
                radial-gradient(ellipse 60% 40% at 20% 80%, rgba(249, 115, 22, 0.03), transparent);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.02em;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 16px 12px;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-section-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.3);
            padding: 0 8px 8px;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.15s ease;
            margin-bottom: 2px;
        }

        .sidebar-item:hover {
            background: var(--sidebar-item-hover);
            color: var(--sidebar-text-active);
        }

        .sidebar-item.active {
            background: var(--sidebar-item-active);
            color: var(--sidebar-text-active);
        }

        .sidebar-item.active .sidebar-icon {
            color: var(--accent);
        }

        .sidebar-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            opacity: 0.7;
        }

        .sidebar-item.active .sidebar-icon,
        .sidebar-item:hover .sidebar-icon {
            opacity: 1;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
        }

        .sidebar-user:hover {
            background: var(--sidebar-item-hover);
        }

        .sidebar-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .sidebar-user-info {
            flex: 1;
            min-width: 0;
        }

        .sidebar-user-name {
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-user-role {
            font-size: 11px;
            color: var(--sidebar-text);
        }

        /* ── Main Content ── */
        .main {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .topbar {
            padding: 20px 32px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-header {
            padding: 24px 32px 0;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .page-content {
            padding: 20px 32px 40px;
        }

        /* ── Cards ── */
        .card {
            background: var(--panel);
            border-radius: var(--radius-xl);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
        }

        .card-body {
            padding: 24px;
        }

        /* ── Stat Cards ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--panel);
            border-radius: var(--radius-lg);
            border: 1px solid var(--line);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin-top: 8px;
            letter-spacing: -0.02em;
        }

        .stat-change {
            font-size: 12px;
            margin-top: 6px;
            font-weight: 500;
        }

        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--danger); }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 18px;
            height: 38px;
            border-radius: var(--radius-md);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(45, 91, 240, 0.2);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--line-strong);
        }

        .btn-ghost:hover {
            background: var(--panel);
            color: var(--text);
            border-color: var(--line-strong);
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-sm {
            height: 32px;
            padding: 0 12px;
            font-size: 12px;
        }

        /* ── Forms ── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--line-strong);
            border-radius: var(--radius-sm);
            font: inherit;
            font-size: 14px;
            background: #fff;
            color: var(--text);
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ── Toggle Switch ── */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input[type="checkbox"] { opacity: 0; width: 0; height: 0; position: absolute; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: var(--line-strong); border-radius: 24px; transition: .2s; }
        .toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .2s; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .toggle-switch input:checked + .toggle-slider { background: var(--accent); }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

        /* ── Card Spacing ── */
        .card + .card { margin-top: 20px; }
        .card + .btn, .card + .form-actions { margin-top: 20px; }

        /* ── Table ── */
        .table-wrap {
            overflow-x: auto;
            position: relative;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
        }

        td {
            padding: 14px 16px;
            font-size: 14px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: background 0.1s;
        }

        tbody tr:hover {
            background: rgba(45, 91, 240, 0.02);
        }

        /* ── Badge ── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .badge-success { background: rgba(34, 197, 94, 0.1); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-info { background: var(--accent-soft); color: var(--accent); }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 40px;
            margin-bottom: 12px;
            opacity: 0.4;
        }

        .empty-state-text {
            font-size: 14px;
        }

        /* ── Alerts ── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: rgba(34, 197, 94, 0.08); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.15); }
        .alert-danger { background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.15); }
        .alert-warning { background: rgba(245, 158, 11, 0.08); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.15); }

        /* ── Mobile ── */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 200;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-md);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 99;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.open {
                display: block;
            }

            .mobile-toggle {
                display: flex;
            }

            .main {
                margin-left: 0;
            }

            .topbar,
            .page-header,
            .page-content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .page-header {
                padding-top: 56px;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ── Animations ── */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(20px); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .page-content {
            animation: fadeIn 0.3s ease both;
        }

        .stat-card {
            animation: fadeIn 0.4s ease both;
        }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .btn:active {
            transform: scale(0.95) !important;
        }

        .card {
            animation: fadeIn 0.4s ease both;
            animation-delay: 0.1s;
        }

        tbody tr {
            transition: background 0.15s ease, transform 0.15s ease;
        }

        tbody tr:hover {
            background: rgba(45, 91, 240, 0.03);
        }

        .empty-state-icon {
            animation: float 3s ease-in-out infinite;
        }

        .alert {
            animation: slideInRight 0.3s ease both;
        }

        /* ── Toast ── */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            animation: slideInRight 0.3s ease both;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 320px;
        }

        .toast.leaving {
            animation: slideOutRight 0.3s ease both;
        }

        .toast-success { background: #fff; color: #15803d; border: 1px solid rgba(34, 197, 94, 0.2); }
        .toast-error { background: #fff; color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }

        /* ── Confirm Modal ── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 9998;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-box {
            background: #fff;
            border-radius: var(--radius-xl);
            padding: 28px;
            max-width: 380px;
            width: 90%;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .modal-desc {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* ── Loading Spinner ── */
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-loading::after {
            content: "";
            width: 14px;
            height: 14px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 6px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        [x-cloak] { display: none !important; }

        .transition { transition-property: all; }
        .ease-out { transition-timing-function: cubic-bezier(0, 0, 0.2, 1); }
        .ease-in { transition-timing-function: cubic-bezier(0.4, 0, 1, 1); }
        .duration-200 { transition-duration: 200ms; }
        .duration-150 { transition-duration: 150ms; }
        .opacity-0 { opacity: 0; }
        .opacity-100 { opacity: 1; }
        .scale-95 { transform: scale(0.95); }
        .scale-100 { transform: scale(1); }

        /* ── Top Progress Bar ── */
        .top-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--accent);
            z-index: 9999;
            transition: width 0.3s ease;
            border-radius: 0 2px 2px 0;
            box-shadow: 0 0 8px rgba(45, 91, 240, 0.4);
        }
        .top-loader.loading { width: 70%; transition: width 8s cubic-bezier(0.1, 0.5, 0.3, 1); }
        .top-loader.done { width: 100%; opacity: 0; transition: width 0.2s ease, opacity 0.3s ease 0.2s; }

        /* ── Table scroll hint ── */
        @media (max-width: 768px) {
            .table-wrap::after {
                content: "";
                position: absolute;
                top: 0;
                right: 0;
                bottom: 0;
                width: 24px;
                background: linear-gradient(to right, transparent, var(--panel));
                pointer-events: none;
                border-radius: 0 var(--radius-xl) var(--radius-xl) 0;
            }
            .table-wrap.scrolled-end::after { display: none; }
        }

        /* Pagination */
        nav[role="navigation"] {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        nav[role="navigation"] p {
            display: none;
        }
        nav[role="navigation"] > div {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: center;
        }
        nav[role="navigation"] > div > div {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        nav[role="navigation"] span[aria-current="page"] span,
        nav[role="navigation"] a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--radius-sm);
            text-decoration: none;
            transition: all 0.15s;
        }
        nav[role="navigation"] span[aria-current="page"] span {
            background: var(--accent);
            color: #fff;
        }
        nav[role="navigation"] a {
            color: var(--text-secondary);
            background: var(--panel);
            border: 1px solid var(--line);
        }
        nav[role="navigation"] a:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }
        nav[role="navigation"] .hidden {
            display: none;
        }
        nav[role="navigation"] span[aria-disabled="true"] span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            font-size: 13px;
            color: var(--text-muted);
            cursor: not-allowed;
            opacity: 0.5;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="top-loader" id="topLoader"></div>
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open'); document.querySelector('.sidebar-overlay').classList.toggle('open');" aria-label="菜单">☰</button>
    <div class="sidebar-overlay" onclick="document.querySelector('.sidebar').classList.remove('open'); this.classList.remove('open');"></div>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">C</div>
            <span class="sidebar-brand-text">CANG-AI</span>
        </div>

        <nav class="sidebar-nav">
            {{-- Platform Section --}}
            <div class="sidebar-section">
                <div class="sidebar-section-title">平台</div>
                <a href="/admin" class="sidebar-item {{ request()->is('admin') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                    仪表盘
                </a>
                <a href="/admin/users" class="sidebar-item {{ request()->is('admin/users*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    用户管理
                </a>
                <a href="/admin/redeem-codes" class="sidebar-item {{ request()->is('admin/redeem-codes*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 5H3l2.5 7L3 19h18l-2.5-7L21 5z"/><line x1="12" y1="5" x2="12" y2="19"/></svg>
                    兑换码
                </a>
                <a href="/admin/settings" class="sidebar-item {{ request()->is('admin/settings*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    站点设置
                </a>
                <a href="/admin/storage" class="sidebar-item {{ request()->is('admin/storage*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    云存储
                </a>
                <a href="/admin/announcements" class="sidebar-item {{ request()->is('admin/announcements*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    广告横幅
                </a>
                <a href="/admin/login-settings" class="sidebar-item {{ request()->is('admin/login-settings*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    登录设置
                </a>
                <a href="/admin/mail-settings" class="sidebar-item {{ request()->is('admin/mail-settings*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    邮件配置
                </a>
                <a href="/admin/plans" class="sidebar-item {{ request()->is('admin/plans*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3h-8l-2 4h12l-2-4z"/></svg>
                    套餐管理
                </a>
                <a href="/admin/commissions" class="sidebar-item {{ request()->is('admin/commissions*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    分销管理
                </a>
                @if(auth()->user()->role === 'admin')
                <a href="/admin/agent-sites" class="sidebar-item {{ request()->is('admin/agent-sites*') ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    分站管理
                </a>
                @endif
            </div>

            {{-- App Sections from AppLoader --}}
            @foreach($appMenuGroups ?? [] as $group)
            <div class="sidebar-section">
                <div class="sidebar-section-title">{{ $group['title'] }}</div>
                @foreach($group['items'] as $item)
                @php
                    $itemUrl = '#';
                    if (!empty($item['url'])) {
                        $itemUrl = url($item['url']);
                    } elseif (!empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route'])) {
                        $itemUrl = route($item['route']);
                    }
                    $itemActive = !empty($item['route']) && request()->routeIs($item['route'] . '*');
                @endphp
                <a href="{{ $itemUrl }}" class="sidebar-item {{ $itemActive ? 'active' : '' }}">
                    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                    {{ $item['label'] }}
                </a>
                @endforeach
            </div>
            @endforeach
        </nav>

        <div class="sidebar-footer">
            <button class="sidebar-item" style="width:100%; border:none; background:none; cursor:pointer; margin-bottom:8px;"
                    @click="theme = theme === 'dark' ? 'light' : 'dark'">
                <svg x-show="theme === 'light'" class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg x-show="theme === 'dark'" class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <span x-text="theme === 'dark' ? '浅色模式' : '深色模式'"></span>
            </button>
            <div class="sidebar-user">
                <div class="sidebar-avatar">{{ mb_substr(auth()->user()->name ?? 'A', 0, 1) }}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ auth()->user()->name ?? '管理员' }}</div>
                    <div class="sidebar-user-role">{{ auth()->user()->role ?? 'admin' }}</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        @hasSection('header')
        <div class="page-header">
            @yield('header')
        </div>
        @endif

        <div class="page-content">
            @if(session('success'))
                <div class="alert alert-success">✓ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">✕ {{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error) {{ $error }} @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    @stack('modals')

    <!-- Toast Notifications -->
    <div class="toast-container" x-data="toastManager()" x-ref="toasts" @toast.window="add($event.detail)">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast" :class="'toast-' + toast.type + (toast.leaving ? ' leaving' : '')">
                <span x-text="toast.type === 'success' ? '✓' : '✕'"></span>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    <!-- Confirm Modal -->
    <div x-data="confirmModal()" @confirm.window="open($event.detail)"
         x-show="show" x-cloak style="display:none;">
        <div class="modal-backdrop" @click.self="cancel()"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="modal-box"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 style="transform-origin: center;">
                <div class="modal-title" x-text="title"></div>
                <div class="modal-desc" x-text="message"></div>
                <div class="modal-actions">
                    <button class="btn btn-ghost btn-sm" @click="cancel()">取消</button>
                    <button class="btn btn-danger btn-sm" @click="confirm()">确认</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js" defer></script>
    <script>
        function toastManager() {
            return {
                toasts: [],
                add(detail) {
                    const toast = { id: Date.now(), ...detail, leaving: false };
                    this.toasts.push(toast);
                    setTimeout(() => { toast.leaving = true; }, 2500);
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== toast.id); }, 2800);
                }
            };
        }

        function confirmModal() {
            return {
                show: false,
                title: '',
                message: '',
                formEl: null,
                open(detail) {
                    this.title = detail.title || '确认操作';
                    this.message = detail.message || '此操作不可撤销，确定继续？';
                    this.formEl = detail.form || null;
                    this.show = true;
                },
                confirm() {
                    this.show = false;
                    if (this.formEl) this.formEl.submit();
                },
                cancel() {
                    this.show = false;
                    this.formEl = null;
                }
            };
        }

        function countUp(target) {
            return {
                value: 0,
                init() {
                    const end = parseInt(target) || 0;
                    if (end === 0) { this.value = 0; return; }
                    const duration = 800;
                    const step = Math.ceil(end / (duration / 16));
                    const tick = () => {
                        this.value = Math.min(this.value + step, end);
                        if (this.value < end) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                }
            };
        }

        // Auto-dismiss flash alerts
        document.querySelectorAll('.alert').forEach(el => {
            setTimeout(() => {
                el.style.animation = 'slideOutRight 0.3s ease forwards';
                setTimeout(() => el.remove(), 300);
            }, 4000);
        });

        // Form submit loading state
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = form.querySelector('button[type="submit"]:not([data-no-loading])');
                if (btn && !btn.classList.contains('btn-loading')) {
                    btn.classList.add('btn-loading');
                }
            });
        });

        // Top loader on navigation
        const loader = document.getElementById('topLoader');
        document.addEventListener('click', e => {
            const a = e.target.closest('a[href]');
            if (a && a.href && !a.href.startsWith('#') && !a.target && a.origin === location.origin) {
                loader.className = 'top-loader loading';
            }
        });
        document.querySelectorAll('form').forEach(f => {
            f.addEventListener('submit', () => { loader.className = 'top-loader loading'; });
        });
        window.addEventListener('pageshow', () => { loader.className = 'top-loader done'; });
    </script>

    @stack('scripts')
</body>
</html>
