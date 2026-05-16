<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>定价套餐 - {{ isset($agentSite) ? $agentSite->site_name : config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;600;700;900&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3f0;
            --panel: rgba(255,255,255,0.72);
            --panel-strong: rgba(255,255,255,0.92);
            --line: rgba(0,0,0,0.06);
            --text: #1a1a1a;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            --accent: #2d5bf0;
            --accent-soft: rgba(45,91,240,0.08);
            --black: #111113;
            --shadow-soft: 0 8px 32px -8px rgba(0,0,0,0.08);
            --glass: saturate(1.8) blur(20px);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: "Noto Sans SC", -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        body::before {
            content: "";
            position: fixed; inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 0%, rgba(45,91,240,0.06), transparent),
                radial-gradient(ellipse 60% 40% at 80% 10%, rgba(249,115,22,0.04), transparent),
                radial-gradient(ellipse 50% 60% at 50% 100%, rgba(45,91,240,0.03), transparent);
            pointer-events: none; z-index: 0;
        }
        .nav {
            position: sticky; top: 0; z-index: 100;
            backdrop-filter: var(--glass); -webkit-backdrop-filter: var(--glass);
            background: var(--panel);
            border-bottom: 1px solid var(--line);
            padding: 0 24px;
        }
        .nav-inner {
            max-width: 1080px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            height: 56px;
        }
        .nav-logo {
            font-family: "Space Grotesk", sans-serif;
            font-size: 20px; font-weight: 700;
            color: var(--accent); text-decoration: none;
        }
        .nav-links { display: flex; gap: 16px; align-items: center; }
        .nav-links a {
            font-size: 14px; color: var(--text-secondary);
            text-decoration: none; font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: var(--text); }
        .nav-btn {
            display: inline-flex; align-items: center; justify-content: center;
            height: 34px; padding: 0 16px; border-radius: 999px;
            background: var(--black); color: #fff;
            font-size: 13px; font-weight: 600;
            text-decoration: none; border: 0; cursor: pointer;
        }
        .page {
            position: relative; z-index: 1;
            width: min(1080px, calc(100vw - 48px));
            margin: 0 auto;
            padding: 60px 0 100px;
        }
        .header { text-align: center; margin-bottom: 48px; }
        .header h1 {
            font-size: 32px; font-weight: 800;
            background: linear-gradient(135deg, var(--text) 0%, var(--accent) 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }
        .header p { font-size: 15px; color: var(--text-secondary); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .plan-card {
            position: relative;
            background: var(--panel-strong);
            backdrop-filter: var(--glass); -webkit-backdrop-filter: var(--glass);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px 24px;
            display: flex; flex-direction: column;
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .plan-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-soft);
        }
        .plan-card.featured {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px var(--accent), 0 8px 32px -8px rgba(45,91,240,0.15);
        }
        .plan-badge {
            position: absolute; top: -11px; left: 50%; transform: translateX(-50%);
            background: var(--accent); color: #fff;
            font-size: 11px; font-weight: 700; padding: 3px 12px;
            border-radius: 999px;
        }
        .plan-name { font-size: 17px; font-weight: 700; margin-bottom: 4px; }
        .plan-price {
            font-family: "Space Grotesk", sans-serif;
            font-size: 36px; font-weight: 700; margin-bottom: 4px;
        }
        .plan-price .unit { font-size: 16px; font-weight: 500; color: var(--text-secondary); }
        .plan-period { font-size: 13px; color: var(--text-tertiary); margin-bottom: 16px; }
        .plan-features { list-style: none; flex: 1; margin-bottom: 20px; }
        .plan-features li {
            display: flex; align-items: center; gap: 8px;
            font-size: 14px; color: var(--text-secondary);
            padding: 5px 0;
        }
        .plan-features li::before {
            content: "✓"; display: inline-flex; align-items: center; justify-content: center;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--accent-soft); color: var(--accent);
            font-size: 11px; font-weight: 700; flex-shrink: 0;
        }
        .plan-btn {
            display: flex; align-items: center; justify-content: center;
            height: 42px; border-radius: 12px;
            font-size: 14px; font-weight: 600;
            text-decoration: none; border: 0; cursor: pointer;
            transition: transform 0.2s, opacity 0.2s;
            background: var(--black); color: #fff;
        }
        .plan-btn:hover { transform: scale(0.98); opacity: 0.9; }
        .plan-card.featured .plan-btn { background: var(--accent); }
        .empty { text-align: center; padding: 80px 0; color: var(--text-tertiary); font-size: 15px; }
        @media (max-width: 640px) {
            .grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-inner">
            <a href="/" class="nav-logo">{{ isset($agentSite) ? $agentSite->site_name : 'CANG-AI' }}</a>
            <div class="nav-links">
                <a href="/">主页</a>
                <a href="{{ route('explore') }}">探索</a>
                @guest
                    <a href="{{ route('login') }}" class="nav-btn">登录</a>
                @endguest
                @auth
                    <a href="/" class="nav-btn">开始创作</a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="page">
        <div class="header">
            <h1>选择适合你的套餐</h1>
            <p>购买后获得兑换码，在用户中心兑换激活</p>
        </div>

        @if($plans->isNotEmpty())
        <div class="grid">
            @foreach($plans as $plan)
            <div class="plan-card {{ $plan->is_featured ? 'featured' : '' }}">
                @if($plan->is_featured)
                <span class="plan-badge">推荐</span>
                @endif
                <div class="plan-name">{{ $plan->name }}</div>
                <div class="plan-price">
                    ¥{{ intval($plan->price) }}
                    @if(isset($plan->type) && $plan->type === 'subscription')
                    <span class="unit">/ {{ $plan->duration_days }}天</span>
                    @endif
                </div>
                <div class="plan-period">{{ (isset($plan->type) && $plan->type === 'once') ? '一次性购买' : '充值套餐' }}</div>
                <ul class="plan-features">
                    @if($plan->credits > 0)
                    <li>{{ $plan->credits }} 次生成额度</li>
                    @endif
                    @if($plan->balance > 0)
                    <li>¥{{ $plan->balance }} 账户余额</li>
                    @endif
                    @if(!empty($plan->duration_days))
                    <li>有效期 {{ $plan->duration_days }} 天</li>
                    @endif
                    @foreach($plan->features_list as $feature)
                    <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a href="{{ auth()->check() ? '/?profile=1' : route('login') }}" class="plan-btn">立即购买</a>
            </div>
            @endforeach
        </div>
        @else
        <div class="empty">暂无套餐</div>
        @endif
    </div>
</body>
</html>
