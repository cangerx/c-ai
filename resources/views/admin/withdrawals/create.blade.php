@extends('admin.layouts.app')

@section('title', '申请提现')

@section('header')
    <h1 class="page-title">申请提现</h1>
    <p class="page-subtitle">当前可提现余额：¥{{ number_format(auth()->user()->commission_balance, 2) }}</p>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.withdrawals.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">提现金额</label>
                    <input class="form-input" type="number" name="amount" value="{{ old('amount') }}" min="1" max="{{ auth()->user()->commission_balance }}" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">收款方式</label>
                    <select class="form-select" name="payment_method" required>
                        <option value="alipay" {{ old('payment_method') === 'alipay' ? 'selected' : '' }}>支付宝</option>
                        <option value="wechat" {{ old('payment_method') === 'wechat' ? 'selected' : '' }}>微信</option>
                        <option value="bank" {{ old('payment_method') === 'bank' ? 'selected' : '' }}>银行卡</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">收款账号</label>
                    <input class="form-input" type="text" name="payment_account" value="{{ old('payment_account') }}" placeholder="支付宝/微信账号或银行卡号" required>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">提交申请</button>
                    <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
