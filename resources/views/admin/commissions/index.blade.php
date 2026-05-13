@extends('admin.layouts.app')

@section('title', '佣金明细')

@section('header')
    <h1 class="page-title">佣金明细</h1>
    <p class="page-subtitle">下级用户消费产生的佣金记录（佣金比例：{{ $rate * 100 }}%）</p>
@endsection

@section('content')
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>下级用户</th>
                            <th>消费金额</th>
                            <th>佣金金额</th>
                            @if(auth()->user()->isAdmin())<th>代理商</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $log->user->name ?? '-' }}</td>
                            <td>¥{{ number_format($log->cost_balance, 2) }}</td>
                            <td><span style="color: var(--success);">+¥{{ number_format($log->cost_balance * $rate, 2) }}</span></td>
                            @if(auth()->user()->isAdmin())<td>{{ $log->user->parent->name ?? '-' }}</td>@endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isAdmin() ? 5 : 4 }}">
                                <div class="empty-state">
                                    <div class="empty-state-icon">💰</div>
                                    <div class="empty-state-text">暂无佣金记录</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="margin-top: 16px;">{{ $logs->links() }}</div>
@endsection
