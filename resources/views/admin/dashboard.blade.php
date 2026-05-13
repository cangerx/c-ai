@extends('admin.layouts.app')

@section('title', '仪表盘')

@section('header')
    <h1 class="page-title">仪表盘</h1>
    <p class="page-subtitle">平台运营数据概览</p>
@endsection

@section('content')
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">总用户</div>
            <div class="stat-value" x-data="countUp({{ $stats['users'] ?? 0 }})" x-text="value"></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">今日生成</div>
            <div class="stat-value" x-data="countUp({{ $stats['today_tasks'] ?? 0 }})" x-text="value"></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">兑换码总数</div>
            <div class="stat-value" x-data="countUp({{ $stats['redeem_codes'] ?? 0 }})" x-text="value"></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">AI 渠道</div>
            <div class="stat-value" x-data="countUp({{ $stats['channels'] ?? 0 }})" x-text="value"></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="card">
            <div class="card-header"><span class="card-title">最近用户</span></div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>用户</th><th>角色</th><th>状态</th><th>注册时间</th></tr></thead>
                        <tbody>
                            @forelse($recentUsers as $user)
                            <tr>
                                <td>{{ $user->nickname ?? $user->name }}</td>
                                <td><span class="badge badge-info">{{ $user->role }}</span></td>
                                <td>
                                    @if($user->status === 'active')
                                        <span class="badge badge-success">正常</span>
                                    @else
                                        <span class="badge badge-danger">禁用</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at->format('m-d H:i') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">暂无用户</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">最近使用</span></div>
            <div class="card-body" style="padding: 0;">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>用户</th><th>画质</th><th>扣次数</th><th>扣额度</th><th>时间</th></tr></thead>
                        <tbody>
                            @forelse($recentUsage ?? [] as $log)
                            <tr>
                                <td>{{ $log->user->name ?? '—' }}</td>
                                <td>{{ $log->quality }}</td>
                                <td>{{ $log->cost_credits }}</td>
                                <td>¥{{ number_format($log->cost_balance, 2) }}</td>
                                <td>{{ $log->created_at->format('m-d H:i') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" style="text-align:center; padding:24px; color:var(--text-muted);">暂无记录</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
