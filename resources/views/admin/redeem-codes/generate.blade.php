@extends('admin.layouts.app')

@section('title', '批量生成兑换码')

@section('header')
    <h1 class="page-title">批量生成兑换码</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.redeem-codes.generate.submit') }}"
                  x-data="redeemForm()" @submit.prevent="$dispatch('confirm', { title: '确认生成', message: '确定要批量生成兑换码吗？', form: $el })">
                @csrf
                <div class="form-group">
                    <label class="form-label">关联套餐（可选）</label>
                    <select class="form-select" name="plan_id" x-model="planId" @change="onPlanChange()">
                        <option value="">不关联套餐（手动填写）</option>
                        @foreach(\App\Models\Plan::active()->ordered()->get() as $p)
                        <option value="{{ $p->id }}" data-credits="{{ $p->credits }}" data-balance="{{ $p->balance }}">{{ $p->name }} (¥{{ $p->price }})</option>
                        @endforeach
                    </select>
                    <div class="form-hint">选择套餐后自动填充积分和额度</div>
                </div>
                <div class="form-group">
                    <label class="form-label">生成数量</label>
                    <input class="form-input" type="number" name="count" value="{{ old('count', 10) }}" min="1" max="500" required>
                    <div class="form-hint">单次最多 500 个</div>
                </div>
                <div class="form-group">
                    <label class="form-label">类型</label>
                    <select class="form-select" name="type" x-model="codeType">
                        <option value="mixed">混合（积分 + 额度）</option>
                        <option value="credits">仅积分</option>
                        <option value="balance">仅额度</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" x-show="codeType !== 'balance'">
                        <label class="form-label">积分</label>
                        <input class="form-input" type="number" name="credits" x-model="credits" min="0">
                    </div>
                    <div class="form-group" x-show="codeType !== 'credits'">
                        <label class="form-label">额度 (¥)</label>
                        <input class="form-input" type="number" name="balance" x-model="balance" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">有效期（天）</label>
                    <input class="form-input" type="number" name="expires_days" value="{{ old('expires_days') }}" min="1" placeholder="留空则永不过期">
                </div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">生成</button>
                    <a href="{{ route('admin.redeem-codes.index') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function redeemForm() {
            return {
                planId: '',
                codeType: 'mixed',
                credits: {{ old('credits', 10) }},
                balance: {{ old('balance', 0) }},
                onPlanChange() {
                    const opt = this.$el.querySelector(`select[name="plan_id"] option[value="${this.planId}"]`);
                    if (opt && this.planId) {
                        this.credits = opt.dataset.credits || 0;
                        this.balance = opt.dataset.balance || 0;
                    }
                }
            };
        }
    </script>
@endsection
