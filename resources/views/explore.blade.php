<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>灵感广场 — {{ config('app.name', 'CANG-AI') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.cn">
    <link href="https://fonts.googleapis.cn/css2?family=Noto+Sans+SC:wght@400;500;600;700;900&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
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
            --shadow: 0 24px 48px -12px rgba(0,0,0,0.12);
            --shadow-soft: 0 8px 32px -8px rgba(0,0,0,0.08);
            --radius: 14px;
        }

        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Noto Sans SC", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, rgba(45,91,240,0.05), transparent),
                radial-gradient(ellipse 60% 40% at 80% 10%, rgba(249,115,22,0.03), transparent);
            pointer-events: none;
        }

        .shell {
            position: relative;
            z-index: 1;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px 80px;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(245,243,240,0.85);
            backdrop-filter: saturate(1.8) blur(20px);
            -webkit-backdrop-filter: saturate(1.8) blur(20px);
            margin: 0 -20px;
            padding-left: 20px;
            padding-right: 20px;
        }

        .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
        .brand-icon { width: 32px; height: 32px; border-radius: 10px; background: var(--black); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 14px; font-family: "Space Grotesk", sans-serif; }
        .brand-name { font-family: "Space Grotesk", sans-serif; font-size: 17px; font-weight: 700; letter-spacing: -0.02em; }

        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-links a { padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--muted); text-decoration: none; transition: all 0.2s; }
        .nav-links a:hover { color: var(--text); background: rgba(0,0,0,0.04); }
        .nav-links a.active { color: var(--accent); background: var(--accent-soft); }
        .btn-create { background: var(--black) !important; color: #fff !important; font-weight: 600 !important; box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
        .btn-create:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.18); }

        /* Hero + Search */
        .hero-section { text-align: center; padding: 32px 0 28px; }
        .hero-section h1 { font-size: clamp(22px, 3.5vw, 32px); font-weight: 800; letter-spacing: -0.03em; color: var(--black); margin-bottom: 6px; }
        .hero-section p { color: var(--muted); font-size: 14px; margin-bottom: 20px; }

        .search-bar {
            max-width: 480px;
            margin: 0 auto;
            position: relative;
        }
        .search-bar input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border-radius: 12px;
            border: 1px solid var(--line-strong);
            background: #fff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-bar input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .search-bar::before {
            content: "🔍";
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            pointer-events: none;
        }

        .stats-row { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 16px; }
        .stat-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; background: #fff; border: 1px solid var(--line); font-size: 11px; color: var(--muted); }
        .stat-chip strong { color: var(--text); font-weight: 600; }
        /* Masonry via CSS columns */
        .masonry-container {
            columns: 4;
            column-gap: 16px;
        }

        @media (max-width: 1024px) { .masonry-container { columns: 3; } }
        @media (max-width: 720px) { .masonry-container { columns: 2; } }

        .masonry-container .card {
            break-inside: avoid;
            margin-bottom: 16px;
        }

        /* Card */
        .card {
            border-radius: var(--radius);
            background: #fff;
            border: 1px solid var(--line);
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), box-shadow 0.3s;
            opacity: 0;
            animation: cardIn 0.5s cubic-bezier(0.22,1,0.36,1) forwards;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15);
            z-index: 2;
        }

        .card-img-wrap {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f0f0ee 0%, #e8e6e3 100%);
            min-height: 160px;
        }

        .card-img-wrap img {
            width: 100%;
            display: block;
            transition: transform 0.5s cubic-bezier(0.22,1,0.36,1);
            opacity: 0;
        }

        .card-img-wrap img.loaded { opacity: 1; transition: opacity 0.4s ease, transform 0.5s cubic-bezier(0.22,1,0.36,1); }
        .card:hover .card-img-wrap img { transform: scale(1.05); }

        /* Skeleton shimmer */
        .card-img-wrap::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%);
            animation: shimmer 1.5s infinite;
        }
        .card-img-wrap.loaded::before { display: none; }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Multi-image badge */
        .card-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 3px 8px;
            border-radius: 6px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .card-body { padding: 12px 14px 14px; }

        .card-author {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .card-avatar {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .card-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }

        .card-author-name { font-size: 11px; font-weight: 500; color: var(--text); }
        .card-time { font-size: 10px; color: var(--muted-soft); margin-left: auto; }

        .card-prompt {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            color: var(--muted-soft);
        }

        .card-meta .tag {
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
            font-size: 9px;
            text-transform: uppercase;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(24px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            place-items: center;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }
        .lightbox.open { display: grid; }

        .lightbox-inner {
            background: #fff;
            border-radius: 20px;
            max-width: 920px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 340px;
            box-shadow: var(--shadow);
            animation: scaleIn 0.3s cubic-bezier(0.22,1,0.36,1);
        }

        @media (max-width: 768px) {
            .lightbox-inner { grid-template-columns: 1fr; max-height: 85vh; overflow-y: auto; }
        }

        .lightbox-img {
            background: #f5f3f0;
            display: grid;
            place-items: center;
            min-height: 300px;
            overflow: hidden;
            position: relative;
        }

        .lightbox-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            max-height: 72vh;
            transition: opacity 0.3s;
        }

        /* Lightbox nav arrows */
        .lb-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: 1px solid var(--line);
            display: grid;
            place-items: center;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .lb-nav:hover { background: #fff; transform: translateY(-50%) scale(1.1); }
        .lb-nav.prev { left: 12px; }
        .lb-nav.next { right: 12px; }

        /* Image counter dots */
        .lb-dots {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(0,0,0,0.5);
        }
        .lb-dots span {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transition: all 0.2s;
        }
        .lb-dots span.active { background: #fff; transform: scale(1.3); }

        .lightbox-info {
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            border-left: 1px solid var(--line);
            overflow-y: auto;
        }

        .lb-author {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }

        .lb-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        .lb-author-info { flex: 1; }
        .lb-author-name { font-size: 13px; font-weight: 600; color: var(--text); }
        .lb-author-time { font-size: 11px; color: var(--muted-soft); }

        .lightbox-info h3 { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }

        .lightbox-prompt {
            font-size: 13px;
            color: var(--text);
            line-height: 1.7;
            background: #f9f9f7;
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            max-height: 120px;
            overflow-y: auto;
        }

        .lightbox-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .lightbox-tags span { padding: 4px 10px; border-radius: 6px; background: var(--accent-soft); color: var(--accent); font-size: 11px; font-weight: 500; }

        .lightbox-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }

        .lightbox-actions button {
            flex: 1;
            padding: 10px 0;
            border-radius: 10px;
            border: 1px solid var(--line-strong);
            background: #fff;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
        }
        .lightbox-actions button:hover { background: #f5f5f3; }
        .lightbox-actions button.primary { background: var(--black); color: #fff; border-color: var(--black); }
        .lightbox-actions button.primary:hover { opacity: 0.9; }

        .lightbox-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: none;
            font-size: 18px;
            cursor: pointer;
            display: grid;
            place-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            z-index: 10;
        }
        .lightbox-close:hover { transform: scale(1.1); }

        /* Loading spinner */
        .load-more {
            display: flex;
            justify-content: center;
            padding: 40px 0;
        }
        .spinner {
            width: 32px; height: 32px;
            border: 3px solid var(--line);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .load-end { text-align: center; padding: 40px 0; font-size: 13px; color: var(--muted-soft); }

        /* Empty */
        .empty-state { text-align: center; padding: 80px 20px; color: var(--muted-soft); }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 15px; }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 32px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            padding: 10px 20px;
            border-radius: 10px;
            background: var(--black);
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.22,1,0.36,1);
            z-index: 2000;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        .footer { text-align: center; padding: 40px 0 0; font-size: 12px; color: var(--muted-soft); }
        .footer a { color: var(--muted); text-decoration: none; }
        .footer a:hover { color: var(--accent); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        @media (max-width: 480px) {
            .shell { padding: 0 12px 60px; }
            .header { margin: 0 -12px; padding-left: 12px; padding-right: 12px; }
            .nav-links a:not(.btn-create):not(.active) { display: none; }
            .brand-name { font-size: 15px; }
            .hero-section h1 { font-size: 22px; }
            .card-body { padding: 10px 12px 12px; }
        }
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
                <a href="/explore" class="active">灵感广场</a>
                <a href="/" class="btn-create">+ 开始创作</a>
            </nav>
        </header>

        <section class="hero-section">
            <h1>灵感广场</h1>
            <p>探索社区创作，发现无限可能</p>
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="搜索提示词..." value="{{ request('q') }}">
            </div>
            <div class="stats-row">
                <span class="stat-chip"><strong>{{ $total }}</strong> 件作品</span>
                <span class="stat-chip">✨ 社区共创</span>
            </div>
        </section>

        @if($items->isEmpty())
            <div class="empty-state">
                <div style="font-size:48px;margin-bottom:12px;">🎨</div>
                <p style="font-size:15px;color:var(--muted-soft);">还没有公开作品，去创作第一幅吧</p>
            </div>
        @else
            <div class="masonry-container" id="masonry"></div>
            <div class="load-more" id="loadMore" style="display:none;">
                <div class="spinner"></div>
            </div>
            <div class="end-msg" id="endMsg" style="display:none;text-align:center;padding:40px 0;color:var(--muted-soft);font-size:13px;">已经到底啦 ✨</div>
        @endif

        <div class="footer" style="text-align:center;padding:40px 0 0;font-size:12px;color:var(--muted-soft);">
            <a href="/" style="color:var(--muted);text-decoration:none;">CANG-AI</a> · 灵感广场
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <button class="lightbox-close" onclick="closeLightbox()">✕</button>
        <div class="lightbox-inner">
            <div class="lightbox-img">
                <button class="lb-nav prev" onclick="lbPrev()">‹</button>
                <img id="lb-img" src="" alt="">
                <button class="lb-nav next" onclick="lbNext()">›</button>
                <div class="lb-dots" id="lb-dots"></div>
            </div>
            <div class="lightbox-info">
                <div class="lb-author">
                    <div class="lb-avatar" id="lb-avatar"></div>
                    <div class="lb-author-info">
                        <div class="lb-author-name" id="lb-author-name"></div>
                        <div class="lb-author-time" id="lb-author-time"></div>
                    </div>
                </div>
                <h3>提示词</h3>
                <div class="lightbox-prompt" id="lb-prompt"></div>
                <div class="lightbox-tags" id="lb-tags"></div>
                <div class="lightbox-actions">
                    <button onclick="copyPrompt()">📋 复制</button>
                    <button onclick="downloadImg()">⬇ 下载</button>
                    <button class="primary" onclick="usePrompt()">✨ 使用</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    <script>
        let allItems = @json($items);
        let currentPage = {{ $page }};
        let totalPages = {{ $totalPages }};
        let loading = false;
        let currentIndex = -1;
        let currentImgIdx = 0;

        const container = document.getElementById('masonry');
        const loadMore = document.getElementById('loadMore');
        const endMsg = document.getElementById('endMsg');

        function renderCards(items, append) {
            const html = items.map((item, idx) => {
                const globalIdx = append ? allItems.indexOf(item) : idx;
                const lazy = (append || idx >= 4) ? ' loading="lazy"' : '';
                const avatar = item.author.avatar
                    ? `<img src="${item.author.avatar}" alt="">`
                    : item.author.name.charAt(0);
                const badge = item.image_count > 1
                    ? `<div class="card-badge">🖼 ${item.image_count}</div>`
                    : '';

                return `<div class="card" data-idx="${globalIdx}" onclick="openLightbox(${globalIdx})">
                    <div class="card-img-wrap">
                        <img src="${item.thumb}" alt=""${lazy} onload="this.classList.add('loaded');this.parentElement.classList.add('loaded');">
                        ${badge}
                    </div>
                    <div class="card-body">
                        <div class="card-author">
                            <div class="card-avatar">${avatar}</div>
                            <span class="card-author-name">${item.author.name}</span>
                            <span class="card-time">${item.time_ago || ''}</span>
                        </div>
                        <div class="card-prompt">${escHtml(item.prompt || '')}</div>
                        <div class="card-meta">
                            <span class="tag">${item.model}</span>
                            <span>${item.quality} · ${item.size}</span>
                        </div>
                    </div>
                </div>`;
            }).join('');

            if (append) {
                container.insertAdjacentHTML('beforeend', html);
            } else {
                container.innerHTML = html;
            }
        }

        function escHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // Initial render
        if (allItems.length) renderCards(allItems, false);

        // Infinite scroll
        const scrollObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !loading && currentPage < totalPages) {
                loadNextPage();
            }
        }, { rootMargin: '400px' });

        if (loadMore) {
            loadMore.style.display = 'flex';
            if (currentPage >= totalPages) {
                loadMore.style.display = 'none';
                if (endMsg) endMsg.style.display = 'block';
            } else {
                scrollObserver.observe(loadMore);
            }
        }

        async function loadNextPage() {
            loading = true;
            currentPage++;
            const q = document.getElementById('searchInput')?.value || '';
            const url = `/explore?page=${currentPage}&q=${encodeURIComponent(q)}`;
            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.ok && data.items.length) {
                    allItems = allItems.concat(data.items);
                    renderCards(data.items, true);
                }
                if (currentPage >= data.total_pages) {
                    loadMore.style.display = 'none';
                    if (endMsg) endMsg.style.display = 'block';
                    scrollObserver.disconnect();
                }
            } catch(e) { console.error(e); }
            loading = false;
        }

        // Search
        let searchTimer;
        document.getElementById('searchInput')?.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const q = this.value.trim();
                window.location.href = '/explore' + (q ? '?q=' + encodeURIComponent(q) : '');
            }, 600);
        });

        // Lightbox
        function openLightbox(idx) {
            currentIndex = idx;
            currentImgIdx = 0;
            updateLightbox();
            document.getElementById('lightbox').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function updateLightbox() {
            const item = allItems[currentIndex];
            if (!item) return;
            const images = item.images || [item.thumb];
            document.getElementById('lb-img').src = images[currentImgIdx] || item.thumb;

            // Author
            const avatarEl = document.getElementById('lb-avatar');
            avatarEl.innerHTML = item.author.avatar
                ? `<img src="${item.author.avatar}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`
                : item.author.name.charAt(0);
            document.getElementById('lb-author-name').textContent = item.author.name;
            document.getElementById('lb-author-time').textContent = item.time_ago || '';

            document.getElementById('lb-prompt').textContent = item.prompt || '无提示词';
            document.getElementById('lb-tags').innerHTML =
                `<span>${item.model}</span><span>${item.quality}</span><span>${item.size}</span>`;

            // Dots
            const dotsEl = document.getElementById('lb-dots');
            if (images.length > 1) {
                dotsEl.innerHTML = images.map((_, i) =>
                    `<span class="${i === currentImgIdx ? 'active' : ''}" onclick="goToImg(${i})"></span>`
                ).join('');
                dotsEl.style.display = 'flex';
                document.querySelector('.lb-nav.prev').style.display = 'grid';
                document.querySelector('.lb-nav.next').style.display = 'grid';
            } else {
                dotsEl.style.display = 'none';
                document.querySelector('.lb-nav.prev').style.display = 'none';
                document.querySelector('.lb-nav.next').style.display = 'none';
            }
        }

        function goToImg(i) { currentImgIdx = i; updateLightbox(); }

        function lbPrev() {
            const images = allItems[currentIndex]?.images || [];
            if (images.length <= 1) {
                // Navigate to prev item
                if (currentIndex > 0) { currentIndex--; currentImgIdx = 0; updateLightbox(); }
            } else {
                currentImgIdx = (currentImgIdx - 1 + images.length) % images.length;
                updateLightbox();
            }
        }

        function lbNext() {
            const images = allItems[currentIndex]?.images || [];
            if (images.length <= 1) {
                if (currentIndex < allItems.length - 1) { currentIndex++; currentImgIdx = 0; updateLightbox(); }
            } else {
                currentImgIdx = (currentImgIdx + 1) % images.length;
                updateLightbox();
            }
        }

        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('open');
            document.body.style.overflow = '';
            currentIndex = -1;
        }

        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) closeLightbox();
        });

        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('lightbox').classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') lbPrev();
            if (e.key === 'ArrowRight') lbNext();
        });

        function copyPrompt() {
            if (currentIndex < 0) return;
            const text = allItems[currentIndex].prompt;
            navigator.clipboard.writeText(text).then(() => showToast('已复制提示词')).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
                showToast('已复制提示词');
            });
        }

        function usePrompt() {
            if (currentIndex < 0) return;
            window.location.href = '/?prompt=' + encodeURIComponent(allItems[currentIndex].prompt);
        }

        function downloadImg() {
            if (currentIndex < 0) return;
            const item = allItems[currentIndex];
            const images = item.images || [item.thumb];
            const url = images[currentImgIdx] || item.thumb;
            const a = document.createElement('a');
            a.href = url; a.download = `cang-ai-${item.task_id}-${currentImgIdx}.png`;
            a.target = '_blank'; a.click();
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg; t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2000);
        }

    </script>
</body>
</html>
