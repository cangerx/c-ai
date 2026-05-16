@extends('admin.layouts.app')

@section('title', '分销管理')

@section('header')
    <h1 class="page-title">分销管理</h1>
    <p class="page-subtitle">分销者列表、邀请记录与返利明细（返利比例：{{ $rate * 100 }}%）</p>
@endsection

@section('content')
    {{-- 分销者概览 --}}
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header"><span class="card-title">分销者列表（{{ $distributors->count() }} 人）</span></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>邀请码</th>
                            <th>邀请人数</th>
                            <th>累计返利</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($distributors as $d)
                        <tr>
                            <td>{{ $d->id }}</td>
                            <td>{{ $d->nickname ?? $d->name ?? $d->email }}</td>
                            <td><code>{{ $d->invite_code }}</code></td>
                            <td>{{ $d->children_count }}</td>
                            <td style="color: var(--success); font-weight: 600;">{{ $d->commission_credits }} 积分</td>
                            <td><a href="{{ route('admin.commissions.index', ['user_id' => $d->id]) }}" class="btn btn-ghost btn-sm">查看明细</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">👥</div><div class="empty-state-text">暂无分销者</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 返利明细 --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">返利明细</span>
            @if(request('user_id'))
                <a href="{{ route('admin.commissions.index') }}" style="font-size:12px; margin-left:12px;">← 查看全部</a>
            @endif
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>分销者</th>
                            <th>来自用户</th>
                            <th>返利积分</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->user->nickname ?? $log->user->name ?? $log->user->email ?? '-' }}</td>
                            <td>{{ $log->fromUser->nickname ?? $log->fromUser->name ?? $log->fromUser->email ?? '-' }}</td>
                            <td><span style="color: var(--success); font-weight: 600;">+{{ $log->credits }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon">💰</div><div class="empty-state-text">暂无返利记录</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if($logs->hasPages())
    <div style="margin-top: 16px;">{{ $logs->links() }}</div>
    @endif
@endsection
