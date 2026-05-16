@extends('agent.layout')

@section('title', '数据统计')

@section('content')
    <h1 class="page-title">数据统计</h1>
    <p class="page-subtitle">下级用户数据概览</p>

    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:24px;">
        <div class="card">
            <div class="card-header"><span class="card-title">今日</span></div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div><span style="font-size:12px; color:var(--text-muted);">新增用户</span><div style="font-size:20px; font-weight:700;">{{ $todayUsers }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">消费积分</span><div style="font-size:20px; font-weight:700;">{{ number_format($todayCredits) }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">佣金收入</span><div style="font-size:20px; font-weight:700;">{{ number_format($todayCommission) }}</div></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">近7日</span></div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div><span style="font-size:12px; color:var(--text-muted);">新增用户</span><div style="font-size:20px; font-weight:700;">{{ $weekUsers }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">消费积分</span><div style="font-size:20px; font-weight:700;">{{ number_format($weekCredits) }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">佣金收入</span><div style="font-size:20px; font-weight:700;">{{ number_format($weekCommission) }}</div></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">近30日</span></div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div><span style="font-size:12px; color:var(--text-muted);">新增用户</span><div style="font-size:20px; font-weight:700;">{{ $monthUsers }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">消费积分</span><div style="font-size:20px; font-weight:700;">{{ number_format($monthCredits) }}</div></div>
                    <div><span style="font-size:12px; color:var(--text-muted);">佣金收入</span><div style="font-size:20px; font-weight:700;">{{ number_format($monthCommission) }}</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">30日趋势</span></div>
        <div class="card-body">
            <canvas id="trendChart" height="200"></canvas>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const data = @json($chartData);
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: data.map(d => d.date),
        datasets: [
            { label: '新增用户', data: data.map(d => d.users), borderColor: '#2d5bf0', tension: 0.3, borderWidth: 2, pointRadius: 0 },
            { label: '消费积分', data: data.map(d => d.credits), borderColor: '#22c55e', tension: 0.3, borderWidth: 2, pointRadius: 0, yAxisID: 'y1' }
        ]
    },
    options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: {
            y: { beginAtZero: true, position: 'left' },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
        },
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
@endpush
