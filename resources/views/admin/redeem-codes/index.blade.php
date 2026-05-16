@extends('admin.layouts.app')

@section('title', '兑换码管理')

@section('header')
    <h1 class="page-title">兑换码管理</h1>
    <p class="page-subtitle">创建和管理兑换码</p>
@endsection

@section('content')
    <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;">
        <form style="display: flex; gap: 8px; flex: 1; min-width: 200px;" method="GET">
            <select class="form-select" name="status" style="max-width: 130px;" onchange="this.form.submit()">
                <option value="">全部状态</option>
                <option value="unused" {{ request('status') === 'unused' ? 'selected' : '' }}>未使用</option>
                <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>已使用</option>
                <option value="disabled" {{ request('status') === 'disabled' ? 'selected' : '' }}>已禁用</option>
            </select>
            @if(request('batch_id'))
                <span class="badge badge-info" style="align-self:center;">批次: {{ request('batch_id') }}</span>
                <a href="{{ route('admin.redeem-codes.index') }}" class="btn btn-ghost btn-sm">清除筛选</a>
            @endif
        </form>
        <a href="{{ route('admin.redeem-codes.export', request()->query()) }}" class="btn btn-ghost btn-sm">导出 CSV</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('generateModal').showModal()">批量生成</button>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>兑换码</th>
                            <th>类型</th>
                            <th>积分</th>
                            <th>额度</th>
                            <th>状态</th>
                            <th>创建者</th>
                            <th>使用者</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($codes as $code)
                        <tr>
                            <td><code style="font-size:12px; background:var(--accent-soft); padding:2px 8px; border-radius:6px;">{{ $code->code }}</code></td>
                            <td><span class="badge badge-info">{{ $code->type }}</span></td>
                            <td>{{ $code->credits }}</td>
                            <td>¥{{ number_format($code->balance, 2) }}</td>
                            <td>
                                @if($code->status === 'unused')
                                    <span class="badge badge-success">未使用</span>
                                @elseif($code->status === 'used')
                                    <span class="badge badge-warning">已使用</span>
                                @else
                                    <span class="badge badge-danger">已禁用</span>
                                @endif
                            </td>
                            <td>{{ $code->creator->name ?? '-' }}</td>
                            <td>{{ $code->user->name ?? '-' }}</td>
                            <td>{{ $code->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if($code->status === 'unused')
                                    <form method="POST" action="{{ route('admin.redeem-codes.disable', $code) }}" style="display:inline;"
                                          x-data @submit.prevent="$dispatch('confirm', { title: '作废兑换码', message: '确定作废此兑换码「{{ $code->code }}」？此操作不可撤销。', form: $el })">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm" data-no-loading>作废</button>
                                    </form>
                                @else
                                    <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🎟️</div>
                                    <div class="empty-state-text">暂无兑换码</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="margin-top: 16px;">{{ $codes->links() }}</div>

    {{-- 批量生成弹窗 --}}
    <dialog id="generateModal" style="border:none; border-radius:12px; padding:0; max-width:480px; width:90%; box-shadow: 0 20px 60px rgba(0,0,0,.3);">
        <div style="padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; font-size:18px;">批量生成兑换码</h3>
                <button type="button" onclick="document.getElementById('generateModal').close()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-muted);">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.redeem-codes.generate.submit') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">生成数量</label>
                    <input class="form-input" type="number" name="count" value="10" min="1" max="500" required>
                    <div class="form-hint">单次最多 500 个</div>
                </div>
                <div class="form-group">
                    <label class="form-label">类型</label>
                    <select class="form-select" name="type" id="modalCodeType">
                        <option value="mixed">混合（积分 + 额度）</option>
                        <option value="credits">仅积分</option>
                        <option value="balance">仅额度</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" id="modalCreditsGroup">
                        <label class="form-label">积分</label>
                        <input class="form-input" type="number" name="credits" value="10" min="0">
                    </div>
                    <div class="form-group" id="modalBalanceGroup">
                        <label class="form-label">额度 (¥)</label>
                        <input class="form-input" type="number" name="balance" value="0" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">有效期（天）</label>
                    <input class="form-input" type="number" name="expires_days" min="1" placeholder="留空则永不过期">
                </div>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="submit" class="btn btn-primary">生成</button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('generateModal').close()">取消</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        document.getElementById('modalCodeType').addEventListener('change', function() {
            const t = this.value;
            document.getElementById('modalCreditsGroup').style.display = t === 'balance' ? 'none' : '';
            document.getElementById('modalBalanceGroup').style.display = t === 'credits' ? 'none' : '';
        });
    </script>
@endsection
