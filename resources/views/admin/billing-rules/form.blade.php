@extends('admin.layouts.app')

@section('title', $rule ? '编辑计费规则' : '新增计费规则')

@section('header')
    <h1 class="page-title">{{ $rule ? '编辑计费规则' : '新增计费规则' }}</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form method="POST" action="{{ $rule ? route('admin.billing-rules.update', $rule) : route('admin.billing-rules.store') }}">
                @csrf
                @if($rule) @method('PUT') @endif

                <div class="form-group">
                    <label class="form-label">应用名称</label>
                    <input type="text" name="app_name" class="form-input" value="{{ old('app_name', $rule?->app_name ?? 'image-gen') }}" required>
                    <small class="form-hint">如 image-gen</small>
                </div>

                <div class="form-group">
                    <label class="form-label">模型匹配</label>
                    <input type="text" name="model_pattern" class="form-input" value="{{ old('model_pattern', $rule?->model_pattern ?? '') }}" required>
                    <small class="form-hint">精确模型名或 * 匹配全部</small>
                </div>

                <div class="form-group">
                    <label class="form-label">质量</label>
                    <select name="quality" class="form-select">
                        <option value="" {{ old('quality', $rule?->quality) === null ? 'selected' : '' }}>全部质量</option>
                        <option value="low" {{ old('quality', $rule?->quality) === 'low' ? 'selected' : '' }}>low</option>
                        <option value="medium" {{ old('quality', $rule?->quality) === 'medium' ? 'selected' : '' }}>medium</option>
                        <option value="high" {{ old('quality', $rule?->quality) === 'high' ? 'selected' : '' }}>high</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">扣除次数</label>
                    <input type="number" name="cost_credits" class="form-input" value="{{ old('cost_credits', $rule?->cost_credits ?? 1) }}" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">扣除余额 (¥)</label>
                    <input type="number" name="cost_balance" class="form-input" value="{{ old('cost_balance', $rule?->cost_balance ?? '0.10') }}" min="0" step="0.01" required>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="{{ route('admin.billing-rules.index') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
