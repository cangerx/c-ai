@extends('admin.layouts.app')

@section('title', '批量生成兑换码')

@section('header')
    <h1 class="page-title">批量生成兑换码</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.redeem-codes.generate.submit') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">生成数量</label>
                    <input class="form-input" type="number" name="count" value="{{ old('count', 10) }}" min="1" max="500" required>
                    <div class="form-hint">单次最多 500 个</div>
                </div>
                <div class="form-group">
                    <label class="form-label">类型</label>
                    <select class="form-select" name="type" id="codeType">
                        <option value="mixed">混合（次数 + 额度）</option>
                        <option value="credits">仅次数</option>
                        <option value="balance">仅额度</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" id="creditsGroup">
                        <label class="form-label">次数</label>
                        <input class="form-input" type="number" name="credits" value="{{ old('credits', 10) }}" min="0">
                    </div>
                    <div class="form-group" id="balanceGroup">
                        <label class="form-label">额度 (¥)</label>
                        <input class="form-input" type="number" name="balance" value="{{ old('balance', 0) }}" min="0" step="0.01">
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
        document.getElementById('codeType').addEventListener('change', function() {
            const t = this.value;
            document.getElementById('creditsGroup').style.display = t === 'balance' ? 'none' : '';
            document.getElementById('balanceGroup').style.display = t === 'credits' ? 'none' : '';
        });
    </script>
@endsection
