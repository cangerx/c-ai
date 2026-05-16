@extends('agent.layout')

@section('title', '生成兑换码')

@section('content')
    <h1 class="page-title">生成兑换码</h1>
    <p class="page-subtitle">当前积分：{{ number_format($user->credits) }} | 当前余额：¥{{ number_format($user->balance, 2) }}</p>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('agent.redeem-codes.generate.submit') }}" x-data="{ type: 'credits' }">
                @csrf
                <div class="form-group">
                    <label class="form-label">类型</label>
                    <select name="type" class="form-select" x-model="type">
                        <option value="credits">积分</option>
                        <option value="balance">余额</option>
                        <option value="mixed">混合</option>
                    </select>
                </div>

                <div class="form-group" x-show="type === 'credits' || type === 'mixed'">
                    <label class="form-label">每张积分</label>
                    <input type="number" name="credits" class="form-input" value="0" min="0">
                </div>

                <div class="form-group" x-show="type === 'balance' || type === 'mixed'">
                    <label class="form-label">每张余额 (¥)</label>
                    <input type="number" name="balance" class="form-input" value="0" min="0" step="0.01">
                </div>

                <div class="form-group">
                    <label class="form-label">数量</label>
                    <input type="number" name="count" class="form-input" value="10" min="1" max="500" required>
                    <span class="form-hint">最多500张/次</span>
                </div>

                <div class="form-group">
                    <label class="form-label">有效期（天）</label>
                    <input type="number" name="expires_days" class="form-input" placeholder="留空为永不过期" min="1">
                </div>

                @if($plans->count())
                <div class="form-group">
                    <label class="form-label">关联套餐</label>
                    <select name="agent_plan_id" class="form-select">
                        <option value="">不关联</option>
                        @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    <span class="form-hint">关联后用户兑换时可显示套餐信息</span>
                </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                <div style="display:flex; gap:12px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary">生成</button>
                    <a href="{{ route('agent.redeem-codes') }}" class="btn btn-ghost">返回</a>
                </div>
            </form>
        </div>
    </div>
@endsection
