<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>模板广场 — {{ config('app.name', 'CANG-AI') }}</title>
    <style>
        :root {
            --bg: #f5f3f0;
            --panel: rgba(255,255,255,0.85);
            --line: rgba(0,0,0,0.06);
            --line-strong: rgba(0,0,0,0.1);
            --text: #1a1a1a;
            --muted: #6b7280;
            --muted-soft: #9ca3af;
            --accent: #2d5bf0;
            --accent-soft: rgba(45,91,240,0.08);
            --black: #111113;
            --radius: 14px;
        }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: var(--bg); color: var(--text); line-height: 1.5;
            -webkit-font-smoothing: antialiased; min-height: 100vh;
        }
        .shell { max-width: 1280px; margin: 0 auto; padding: 0 20px 80px; }

        .header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 0; position: sticky; top: 0; z-index: 100;
            background: rgba(245,243,240,0.85); backdrop-filter: saturate(1.8) blur(20px);
            -webkit-backdrop-filter: saturate(1.8) blur(20px);
            margin: 0 -20px; padding-left: 20px; padding-right: 20px;
        }
        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
        .brand-icon { width: 32px; height: 32px; border-radius: 10px; background: var(--black); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 14px; }
        .brand-name { font-size: 17px; font-weight: 700; letter-spacing: -0.02em; }
        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-links a { padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; transition: all 0.2s; }
        .nav-links a:hover { color: var(--text); background: rgba(0,0,0,0.04); }
        .nav-links a.active { color: var(--accent); background: var(--accent-soft); }
        .btn-create { background: var(--black) !important; color: #fff !important; font-weight: 600 !important; }

        .hero-section { text-align: center; padding: 32px 0 24px; }
        .hero-section h1 { font-size: clamp(22px, 3.5vw, 32px); font-weight: 800; letter-spacing: -0.03em; color: var(--black); margin-bottom: 6px; }
        .hero-section p { color: var(--muted); font-size: 14px; }

        /* Tabs */
        .tabs { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .tab {
            padding: 8px 18px; border-radius: 999px; font-size: 13px; font-weight: 500;
            color: var(--muted); background: #fff; border: 1px solid var(--line);
            cursor: pointer; text-decoration: none; transition: all 0.2s;
        }
        .tab:hover { border-color: var(--accent); color: var(--accent); }
        .tab.active { background: var(--black); color: #fff; border-color: var(--black); }

        /* Grid */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        @media (max-width: 600px) { .template-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; } }

        .tpl-card {
            border-radius: var(--radius); background: #fff; border: 1px solid var(--line);
            overflow: hidden; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s;
        }
        .tpl-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.12); }

        .tpl-img {
            width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block;
            background: linear-gradient(135deg, #f0f0ee, #e8e6e3);
        }
        .tpl-body { padding: 14px 16px; }
        .tpl-title { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--text); }
        .tpl-meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--muted-soft); }
        .tpl-tag { padding: 2px 8px; border-radius: 4px; background: var(--accent-soft); color: var(--accent); font-size: 11px; }
        .tpl-badge { padding: 2px 8px; border-radius: 4px; background: #fef3c7; color: #d97706; font-size: 11px; font-weight: 500; }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--muted-soft); font-size: 15px; }
        .footer { text-align: center; padding: 40px 0 0; font-size: 12px; color: var(--muted-soft); }
        .footer a { color: var(--muted); text-decoration: none; }
    </style>
</head>
<body>
<div class="shell">
    <header class="header">
        <a href="/" class="brand">
            <div class="brand-icon">C</div>
            <span class="brand-name">CANG-AI</span>
        </a>
        <nav class="nav-links">
            <a href="/">创作</a>
            <a href="/explore">灵感广场</a>
            <a href="/explore/templates" class="active">模板</a>
            <a href="/" class="btn-create">+ 开始创作</a>
        </nav>
    </header>

    <section class="hero-section">
        <h1>模板广场</h1>
        <p>选择模板，快速生成精美图片</p>
    </section>

    <div class="tabs">
        <a href="/explore/templates" class="tab {{ !$currentCategory ? 'active' : '' }}">全部</a>
        @foreach($categories as $cat)
            <a href="/explore/templates?category={{ $cat->id }}" class="tab {{ $currentCategory == $cat->id ? 'active' : '' }}">
                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $cat->icon }};margin-right:4px;vertical-align:middle;"></span>{{ $cat->name }}
            </a>
        @endforeach
    </div>

    @if($templates->isEmpty())
        <div class="empty-state">暂无模板</div>
    @else
        <div class="template-grid">
            @foreach($templates as $tpl)
                <div class="tpl-card" onclick="window.location.href='/explore/templates/{{ $tpl['id'] }}/use'">
                    @if($tpl['preview_url'])
                        <img class="tpl-img" src="{{ $tpl['preview_url'] }}" alt="{{ $tpl['title'] }}" loading="lazy">
                    @else
                        <div class="tpl-img" style="display:grid;place-items:center;font-size:32px;color:var(--muted-soft);">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                        </div>
                    @endif
                    <div class="tpl-body">
                        <div class="tpl-title">{{ $tpl['title'] }}</div>
                        <div class="tpl-meta">
                            @if($tpl['is_featured'])
                                <span class="tpl-badge">推荐</span>
                            @endif
                            @if($tpl['category'])
                                <span class="tpl-tag">{{ $tpl['category'] }}</span>
                            @endif
                            <span>{{ $tpl['variables_count'] }}个变量</span>
                            @if($tpl['has_image_var'])
                                <span class="tpl-tag">需上传图片</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="footer">
        <a href="/">CANG-AI</a> · 模板广场
    </div>
</div>
</body>
</html>
