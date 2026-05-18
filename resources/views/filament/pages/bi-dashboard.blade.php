<x-filament-panels::page>
    @php
        $stats = $this->getSummaryStats();
        $finance = $this->getFinanceStats();
        $userGrowth = $this->getUserGrowthData();
        $revenue = $this->getRevenueData();
        $rechargeChart = $this->getRechargeChartData();
        $agents = $this->getAgentLeaderboard();
        $distributors = $this->getDistributorLeaderboard();
    @endphp

    {{-- Platform Summary --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">总用户</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ number_format($stats['totalUsers']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">代理商</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ $stats['totalAgents'] }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">分销员</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ $stats['totalDistributors'] }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">30天活跃用户</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ number_format($finance['activeUsers30']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">30天消耗积分</div>
            <div style="font-size:1.5rem;font-weight:700;">{{ number_format($stats['monthCredits']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <div style="font-size:0.75rem;color:#6b7280;">待处理提现</div>
            <div style="font-size:1.5rem;font-weight:700;color:#ef4444;">{{ $stats['pendingWithdrawals'] }}</div>
        </div>
    </div>

    {{-- Financial Overview --}}
    <div style="background:linear-gradient(135deg,#1e293b,#334155);border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;color:#fff;">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:#94a3b8;">💰 财务概览（近30天）</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1.5rem;">
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">代理充值积分</div>
                <div style="font-size:1.4rem;font-weight:700;color:#34d399;">{{ number_format($finance['totalRecharge']) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">代理充值余额</div>
                <div style="font-size:1.4rem;font-weight:700;color:#34d399;">¥{{ number_format($finance['totalRechargeBalance'], 2) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">用户消耗积分</div>
                <div style="font-size:1.4rem;font-weight:700;color:#60a5fa;">{{ number_format($finance['creditsConsumed']) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">用户消耗余额</div>
                <div style="font-size:1.4rem;font-weight:700;color:#60a5fa;">¥{{ number_format($finance['balanceConsumed'], 2) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">佣金发放</div>
                <div style="font-size:1.4rem;font-weight:700;color:#fbbf24;">{{ number_format($stats['monthCommission']) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">已批准提现</div>
                <div style="font-size:1.4rem;font-weight:700;color:#f87171;">{{ number_format($finance['withdrawApproved']) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">待处理提现</div>
                <div style="font-size:1.4rem;font-weight:700;color:#fb923c;">{{ number_format($finance['withdrawPending']) }}</div>
            </div>
            <div>
                <div style="font-size:0.75rem;color:#94a3b8;">ARPU (人均消耗)</div>
                <div style="font-size:1.4rem;font-weight:700;color:#a78bfa;">{{ $finance['arpu'] }} 积分</div>
            </div>
        </div>
    </div>

    {{-- Credits Health --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-left:4px solid #10b981;">
            <div style="font-size:0.75rem;color:#6b7280;">30天积分发行量</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ number_format($finance['creditsIssued']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-left:4px solid #3b82f6;">
            <div style="font-size:0.75rem;color:#6b7280;">30天积分消耗量</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ number_format($finance['creditsConsumed']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-left:4px solid #f59e0b;">
            <div style="font-size:0.75rem;color:#6b7280;">全平台积分池余额</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ number_format($finance['totalUserCredits']) }}</div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);border-left:4px solid #8b5cf6;">
            <div style="font-size:0.75rem;color:#6b7280;">全平台余额池</div>
            <div style="font-size:1.3rem;font-weight:700;">¥{{ number_format($finance['totalUserBalance'], 2) }}</div>
        </div>
    </div>

    {{-- Charts --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1.5rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">用户增长趋势（30天）</h3>
            <canvas id="userGrowthChart" height="200"></canvas>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">积分消耗趋势（30天）</h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr;gap:1.5rem;margin-bottom:1.5rem;">
        <div style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">代理充值趋势（30天）</h3>
            <canvas id="rechargeChart" height="160"></canvas>
        </div>
    </div>

    {{-- Leaderboards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:1.5rem;">
        <div style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">代理业绩排行（30天消耗）</h3>
            <table style="width:100%;font-size:0.8rem;border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb;">
                        <th style="padding-bottom:0.5rem;">#</th>
                        <th style="padding-bottom:0.5rem;">代理</th>
                        <th style="padding-bottom:0.5rem;">用户数</th>
                        <th style="padding-bottom:0.5rem;text-align:right;">消耗积分</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agents as $i => $agent)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.4rem 0;font-weight:500;">{{ $i + 1 }}</td>
                        <td style="padding:0.4rem 0;">{{ $agent['name'] }}</td>
                        <td style="padding:0.4rem 0;">{{ $agent['children_count'] }}</td>
                        <td style="padding:0.4rem 0;text-align:right;font-weight:600;">{{ number_format($agent['total_consumed']) }}</td>
                    </tr>
                    @endforeach
                    @if(empty($agents))
                    <tr><td colspan="4" style="padding:1rem 0;text-align:center;color:#9ca3af;">暂无数据</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">分销员排行（30天佣金）</h3>
            <table style="width:100%;font-size:0.8rem;border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left;color:#6b7280;border-bottom:1px solid #e5e7eb;">
                        <th style="padding-bottom:0.5rem;">#</th>
                        <th style="padding-bottom:0.5rem;">分销员</th>
                        <th style="padding-bottom:0.5rem;text-align:right;">佣金积分</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($distributors as $i => $d)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.4rem 0;font-weight:500;">{{ $i + 1 }}</td>
                        <td style="padding:0.4rem 0;">{{ $d['name'] }}</td>
                        <td style="padding:0.4rem 0;text-align:right;font-weight:600;color:#d97706;">{{ number_format($d['total_commission']) }}</td>
                    </tr>
                    @endforeach
                    @if(empty($distributors))
                    <tr><td colspan="3" style="padding:1rem 0;text-align:center;color:#9ca3af;">暂无数据</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chartOpts = { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };

            const userGrowth = @json($userGrowth);
            new Chart(document.getElementById('userGrowthChart'), {
                type: 'line',
                data: { labels: userGrowth.labels, datasets: [{ label: '新增用户', data: userGrowth.data, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.3 }] },
                options: chartOpts
            });

            const revenue = @json($revenue);
            new Chart(document.getElementById('revenueChart'), {
                type: 'line',
                data: { labels: revenue.labels, datasets: [{ label: '积分消耗', data: revenue.data, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 }] },
                options: chartOpts
            });

            const recharge = @json($rechargeChart);
            new Chart(document.getElementById('rechargeChart'), {
                type: 'bar',
                data: {
                    labels: recharge.labels,
                    datasets: [
                        { label: '充值积分', data: recharge.credits, backgroundColor: 'rgba(16,185,129,0.7)', yAxisID: 'y' },
                        { label: '充值余额(¥)', data: recharge.balance, backgroundColor: 'rgba(99,102,241,0.7)', yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: true, position: 'top' } },
                    scales: {
                        y: { position: 'left', beginAtZero: true, title: { display: true, text: '积分' } },
                        y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: '¥' } }
                    }
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
