@extends('agent.layout')

@section('title', '提现管理')

@section('content')
    <h1 class="page-title">提现管理</h1>
    <p class="page-subtitle">当前佣金余额：¥{{ number_format($user->commission_balance, 2) }}</p>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><span class="card-title">发起提现</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('agent.withdrawals.store') }}">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:12px; align-items:flex-end;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">金额 (¥)</label>
                        <input type="number" name="amount" class="form-input" min="10" step="0.01" required placeholder="最低10元">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">收款方式</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="alipay">支付宝</option>
                            <option value="wechat">微信</option>
                            <option value="bank">银行卡</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">收款账号</label>
                        <input type="text" name="payment_account" class="form-input" required placeholder="账号/卡号">
                    </div>
                    <button type="submit" class="btn btn-primary">提交</button>
                </div>
                @if($errors->any())
                    <div class="alert alert-danger" style="margin-top:12px;">{{ $errors->first() }}</div>
                @endif
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">提现记录</span></div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr><th>金额</th><th>收款方式</th><th>收款账号</th><th>状态</th><th>申请时间</th></tr>
                </thead>
                <tbody>
                    @forelse($withdrawals as $w)
                    <tr>
                        <td>¥{{ number_format($w->amount, 2) }}</td>
                        <td>{{ ['alipay'=>'支付宝','wechat'=>'微信','bank'=>'银行卡'][$w->payment_method] ?? $w->payment_method }}</td>
                        <td>{{ $w->payment_account }}</td>
                        <td>
                            @if($w->status === 'pending') <span class="badge badge-warning">待审核</span>
                            @elseif($w->status === 'approved') <span class="badge badge-success">已通过</span>
                            @else <span class="badge badge-danger">已拒绝</span>
                            @endif
                        </td>
                        <td>{{ $w->created_at->format('m-d H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="empty-state"><div class="empty-state-icon">💰</div><div class="empty-state-text">暂无提现记录</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $withdrawals->links() }}
@endsection
