@extends('agent.layout')

@section('title', '流水记录')

@section('content')
    <h1 class="page-title">流水记录</h1>
    <p class="page-subtitle">所有积分和余额变动明细</p>

    <div style="margin-bottom:16px; display:flex; gap:8px;">
        <a href="{{ route('agent.transactions') }}" class="btn btn-sm {{ !request('type') ? 'btn-primary' : 'btn-ghost' }}">全部</a>
        <a href="{{ route('agent.transactions', ['type'=>'recharge']) }}" class="btn btn-sm {{ request('type')==='recharge' ? 'btn-primary' : 'btn-ghost' }}">充值</a>
        <a href="{{ route('agent.transactions', ['type'=>'generate']) }}" class="btn btn-sm {{ request('type')==='generate' ? 'btn-primary' : 'btn-ghost' }}">生码</a>
        <a href="{{ route('agent.transactions', ['type'=>'commission']) }}" class="btn btn-sm {{ request('type')==='commission' ? 'btn-primary' : 'btn-ghost' }}">佣金</a>
        <a href="{{ route('agent.transactions', ['type'=>'withdraw']) }}" class="btn btn-sm {{ request('type')==='withdraw' ? 'btn-primary' : 'btn-ghost' }}">提现</a>
    </div>

    <div class="card">
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr><th>时间</th><th>类型</th><th>积分变动</th><th>余额变动</th><th>积分余</th><th>余额余</th><th>说明</th></tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('m-d H:i') }}</td>
                        <td>
                            @switch($t->type)
                                @case('recharge') <span class="badge badge-success">充值</span> @break
                                @case('generate') <span class="badge badge-warning">生码</span> @break
                                @case('commission') <span class="badge badge-info">佣金</span> @break
                                @case('withdraw') <span class="badge badge-danger">提现</span> @break
                            @endswitch
                        </td>
                        <td style="color:{{ $t->credits >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $t->credits >= 0 ? '+' : '' }}{{ $t->credits }}</td>
                        <td style="color:{{ $t->balance >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ $t->balance >= 0 ? '+' : '' }}{{ number_format($t->balance, 2) }}</td>
                        <td>{{ number_format($t->credits_after) }}</td>
                        <td>¥{{ number_format($t->balance_after, 2) }}</td>
                        <td style="font-size:12px; color:var(--text-secondary);">{{ $t->description }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="empty-state"><div class="empty-state-icon">📄</div><div class="empty-state-text">暂无流水记录</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $transactions->withQueryString()->links() }}
@endsection
