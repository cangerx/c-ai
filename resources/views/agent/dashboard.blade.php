@extends('agent.layout')

@section('title', '代理概览')

@section('content')
    <h1 class="page-title">概览</h1>
    <p class="page-subtitle">欢迎回来，{{ $agent->name }}</p>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">我的积分</div>
            <div class="stat-value">{{ number_format($agent->credits) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">我的余额</div>
            <div class="stat-value">¥{{ number_format($agent->balance, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">下级用户</div>
            <div class="stat-value">{{ $stats['sub_users'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">今日使用</div>
            <div class="stat-value">{{ $stats['today_usage'] }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">佣金余额</div>
            <div class="stat-value">¥{{ number_format($stats['commission'], 2) }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header"><span class="card-title">自助充值</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('agent.recharge') }}" style="display:flex; gap:12px; align-items:flex-end;">
                @csrf
                <div class="form-group" style="flex:1; margin:0;">
                    <label class="form-label">兑换码</label>
                    <input type="text" name="code" class="form-input" placeholder="输入32位兑换码" maxlength="32" required>
                </div>
                <button type="submit" class="btn btn-primary">充值</button>
            </form>
            @if($errors->any())
                <div class="alert alert-danger" style="margin-top:12px;">{{ $errors->first() }}</div>
            @endif
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header">
            <span class="card-title">邀请码</span>
        </div>
        <div class="card-body">
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">分享此邀请码给用户注册时填写，他们将自动成为你的子用户。</p>
            <div class="invite-box">{{ $agent->invite_code }}</div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header"><span class="card-title">最近注册用户</span></div>
        <div class="card-body" style="padding: 0;">
            <table>
                <thead><tr><th>用户</th><th>邮箱</th><th>积分</th><th>余额</th><th>注册时间</th></tr></thead>
                <tbody>
                    @forelse($recentUsers as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->credits }}</td>
                        <td>¥{{ number_format($u->balance, 2) }}</td>
                        <td>{{ $u->created_at->format('m-d H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">暂无子用户</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($recentUsage->count())
    <div class="card">
        <div class="card-header"><span class="card-title">最近使用记录</span></div>
        <div class="card-body" style="padding: 0;">
            <table>
                <thead><tr><th>用户</th><th>画质</th><th>扣积分</th><th>扣余额</th><th>时间</th></tr></thead>
                <tbody>
                    @foreach($recentUsage as $log)
                    <tr>
                        <td>{{ $log->user->name ?? '—' }}</td>
                        <td>{{ $log->quality }}</td>
                        <td>{{ $log->cost_credits }}</td>
                        <td>¥{{ number_format($log->cost_balance, 2) }}</td>
                        <td>{{ $log->created_at->format('m-d H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
