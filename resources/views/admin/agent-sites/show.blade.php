@extends('admin.layouts.app')

@section('title', $agentSite->site_name . ' — 分站详情')

@section('header')
    <h1 class="page-title">{{ $agentSite->site_name }}</h1>
    <p class="page-subtitle">代理商：{{ $agent->name }} ({{ $agent->email }})</p>
@endsection

@section('content')
    {{-- 状态 + 操作 --}}
    <div style="display:flex; gap:12px; margin-bottom:24px; align-items:center; flex-wrap:wrap;">
        @if($agentSite->status === 'pending')
            <span class="badge badge-warning" style="font-size:12px; padding:4px 12px;">待审核</span>
            <form method="POST" action="{{ route('admin.agent-sites.approve', $agentSite) }}" style="display:inline;">@csrf <button class="btn btn-primary btn-sm">通过审核</button></form>
            <button class="btn btn-ghost btn-sm" style="color:var(--danger);" x-data @click="$dispatch('reject-site', {{ $agentSite->id }})">拒绝</button>
        @elseif($agentSite->status === 'rejected')
            <span class="badge badge-danger" style="font-size:12px; padding:4px 12px;">已拒绝</span>
            <span style="font-size:13px; color:var(--text-muted);">原因：{{ $agentSite->reject_reason }}</span>
        @elseif(!$agentSite->is_active)
            <span class="badge badge-danger" style="font-size:12px; padding:4px 12px;">已禁用</span>
        @else
            <span class="badge badge-success" style="font-size:12px; padding:4px 12px;">运营中</span>
        @endif
        @if($agentSite->approved_at)
            <span style="font-size:12px; color:var(--text-muted);">通过时间：{{ $agentSite->approved_at->format('Y-m-d H:i') }}</span>
        @endif
        <div style="margin-left:auto; display:flex; gap:8px;">
            <a href="{{ route('admin.agent-sites.edit', $agentSite) }}" class="btn btn-ghost btn-sm">编辑</a>
            <a href="{{ route('admin.agent-sites.index') }}" class="btn btn-ghost btn-sm">返回列表</a>
        </div>
    </div>

    {{-- 数据统计 --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">下级用户</div>
            <div class="stat-value">{{ $stats['sub_users'] }}</div>
            <div class="stat-change up">今日 +{{ $stats['today_users'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">总生成次数</div>
            <div class="stat-value">{{ number_format($stats['total_usage']) }}</div>
            <div class="stat-change">今日 {{ $stats['today_usage'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">总消耗积分</div>
            <div class="stat-value">{{ number_format($stats['total_credits']) }}</div>
            <div class="stat-change">佣金 {{ number_format($stats['total_commission']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">累计充值</div>
            <div class="stat-value">¥{{ number_format($stats['total_recharged'], 2) }}</div>
            <div class="stat-change">余额 ¥{{ number_format($stats['balance'], 2) }} / {{ $stats['credits'] }} 积分</div>
        </div>
    </div>

    {{-- 趋势图 --}}
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><span class="card-title">30天趋势</span></div>
        <div class="card-body">
            <canvas id="trendChart" height="80"></canvas>
        </div>
    </div>

    {{-- 分站信息 + 预览 --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><span class="card-title">分站配置</span></div>
            <div class="card-body" style="padding:0;">
                <table>
                    <tbody>
                        <tr><td style="color:var(--text-muted); width:120px;">Slug</td><td><code style="background:var(--accent-soft); padding:2px 8px; border-radius:4px; font-size:12px;">/s/{{ $agentSite->slug }}</code></td></tr>
                        <tr><td style="color:var(--text-muted);">子域名</td><td>{{ $agentSite->subdomain ?: '—' }}</td></tr>
                        <tr>
                            <td style="color:var(--text-muted);">自定义域名</td>
                            <td>
                                {{ $agentSite->custom_domain ?: '—' }}
                                @if($agentSite->custom_domain)
                                    <span id="dnsStatus" style="margin-left:8px; font-size:11px;">检测中...</span>
                                @endif
                            </td>
                        </tr>
                        <tr><td style="color:var(--text-muted);">主题色</td><td><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:{{ $agentSite->theme_color }}; vertical-align:middle; margin-right:6px;"></span>{{ $agentSite->theme_color }}</td></tr>
                        <tr><td style="color:var(--text-muted);">单次扣费</td><td>{{ $agentSite->cost_per_generation ?? '默认' }}</td></tr>
                        <tr><td style="color:var(--text-muted);">佣金比例</td><td>{{ $agentSite->commission_rate !== null ? $agentSite->commission_rate . '%' : '默认' }}</td></tr>
                        <tr><td style="color:var(--text-muted);">代理等级</td><td>{{ $agent->agentLevel->name ?? '默认' }}</td></tr>
                        <tr><td style="color:var(--text-muted);">SEO标题</td><td>{{ $agentSite->seo_title ?: '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">前端预览</span>
                @php
                    $previewUrl = $agentSite->custom_domain
                        ? 'https://' . $agentSite->custom_domain
                        : url('/s/' . $agentSite->slug);
                @endphp
                <a href="{{ $previewUrl }}" target="_blank" class="btn btn-ghost btn-sm" style="height:26px; font-size:11px;">新窗口打开 ↗</a>
            </div>
            <div style="height:380px; overflow:hidden; border-radius:0 0 var(--radius-xl) var(--radius-xl); background:var(--bg);">
                <iframe src="{{ $previewUrl }}" style="width:133%; height:133%; border:none; transform:scale(0.75); transform-origin:top left;"></iframe>
            </div>
        </div>
    </div>

    {{-- 域名配置指引 --}}
    @if($agentSite->custom_domain)
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><span class="card-title">域名配置指引</span></div>
        <div class="card-body">
            <p style="margin-bottom:12px; font-size:13px; color:var(--text-secondary);">代理商需要将域名 <strong>{{ $agentSite->custom_domain }}</strong> 做以下 DNS 解析：</p>
            <table>
                <thead><tr><th>类型</th><th>主机记录</th><th>记录值</th><th>TTL</th></tr></thead>
                <tbody>
                    <tr>
                        <td><span class="badge badge-info">CNAME</span></td>
                        <td><code>{{ explode('.', $agentSite->custom_domain)[0] }}</code></td>
                        <td><code>{{ request()->getHost() }}</code></td>
                        <td>600</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

@push('modals')
@if($agentSite->status === 'pending')
<div x-data="rejectModal()" @reject-site.window="open($event.detail)"
     x-show="show" x-cloak style="display:none; position:fixed; inset:0; z-index:9999;">
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;" @click.self="show = false">
        <div class="modal-box" style="max-width:420px; text-align:left;">
            <h3 style="font-size:16px; font-weight:600; margin-bottom:16px;">拒绝分站申请</h3>
            <form method="POST" action="{{ route('admin.agent-sites.reject', $agentSite) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">拒绝原因</label>
                    <textarea name="reject_reason" class="form-textarea" rows="3" placeholder="请输入拒绝原因..." required></textarea>
                </div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-danger btn-sm">确认拒绝</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="show = false">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
@if($agentSite->status === 'pending')
function rejectModal() {
    return { show: false, open(id) { this.show = true; } };
}
@endif

const chartData = @json($chartData);
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: chartData.map(d => d.date),
        datasets: [
            { label: '新用户', data: chartData.map(d => d.users), borderColor: '#2d5bf0', backgroundColor: 'rgba(45,91,240,0.08)', fill: true, tension: 0.4, borderWidth: 2 },
            { label: '生成次数', data: chartData.map(d => d.usage), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)', fill: true, tension: 0.4, borderWidth: 2 },
        ]
    },
    options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20 } } },
        scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } }
    }
});

@if($agentSite->custom_domain)
fetch('https://dns.google/resolve?name={{ $agentSite->custom_domain }}&type=CNAME')
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('dnsStatus');
        if (data.Answer && data.Answer.length > 0) {
            el.innerHTML = '<span class="badge badge-success">✓ 已解析</span>';
        } else {
            el.innerHTML = '<span class="badge badge-warning">⚠ 未解析</span>';
        }
    }).catch(() => {
        document.getElementById('dnsStatus').innerHTML = '<span class="badge" style="background:var(--line);">检测失败</span>';
    });
@endif
</script>
@endpush
