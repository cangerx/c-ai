@extends('admin.layouts.app')

@section('title', '代理等级管理')

@section('header')
    <h1 class="page-title">代理等级管理</h1>
    <p class="page-subtitle">设置不同等级的进货价和自动升级条件</p>
@endsection

@section('content')
    <div style="margin-bottom:16px; display:flex; gap:8px; justify-content:flex-end;">
        <a href="{{ route('admin.agent-sites.index') }}" class="btn btn-ghost btn-sm">返回分站管理</a>
        <button class="btn btn-primary btn-sm" x-data @click="$dispatch('open-level-modal', {})">添加等级</button>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>排序</th>
                            <th>等级名称</th>
                            <th>最低累计充值</th>
                            <th>进货价/积分</th>
                            <th>代理商数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($levels as $level)
                        <tr>
                            <td>{{ $level->sort_order }}</td>
                            <td><span style="font-weight:600;">{{ $level->name }}</span></td>
                            <td>¥{{ number_format($level->min_recharge, 2) }}</td>
                            <td>¥{{ $level->price_per_credit }}</td>
                            <td>{{ $agentCountByLevel[$level->id] ?? 0 }}</td>
                            <td style="white-space:nowrap;">
                                <button class="btn btn-ghost btn-sm" x-data @click="$dispatch('open-level-modal', {
                                    id: {{ $level->id }},
                                    name: '{{ addslashes($level->name) }}',
                                    min_recharge: '{{ $level->min_recharge }}',
                                    price_per_credit: '{{ $level->price_per_credit }}',
                                    sort_order: {{ $level->sort_order }}
                                })">编辑</button>
                                <form method="POST" action="{{ route('admin.agent-sites.levels.destroy', $level) }}" style="display:inline;"
                                      x-data @submit.prevent="$dispatch('confirm', { title: '删除等级', message: '删除「{{ addslashes($level->name) }}」后，该等级下的代理商将恢复为默认等级。', form: $el })">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" data-no-loading>删除</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🏅</div>
                                    <div class="empty-state-text">暂无等级，点击右上角添加</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">自动升级规则</span></div>
        <div class="card-body" style="font-size:13px; color:var(--text-secondary); line-height:2;">
            <p>• 代理商每次充值后，系统自动检查其累计充值金额，匹配最高可达等级并自动升级。</p>
            <p>• 等级越高，进货价越低，代理商利润空间越大。</p>
            <p>• 管理员也可在分站编辑页手动调整代理商等级。</p>
            <p>• 删除等级后，该等级下的代理商将恢复为默认等级（无等级优惠）。</p>
        </div>
    </div>
@endsection

@push('modals')
<div x-data="levelModal()" @open-level-modal.window="open($event.detail)"
     x-show="show" x-cloak style="display:none; position:fixed; inset:0; z-index:9999;">
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;" @click.self="show = false">
        <div class="modal-box" style="max-width:440px; text-align:left;">
            <h3 style="font-size:16px; font-weight:600; margin-bottom:16px;" x-text="isEdit ? '编辑等级' : '添加等级'"></h3>
            <form :action="isEdit ? '/admin/agent-sites/levels/' + form.id : '{{ route('admin.agent-sites.levels.store') }}'" method="POST">
                @csrf
                <template x-if="isEdit"><input type="hidden" name="_method" value="PUT"></template>
                <div class="form-group">
                    <label class="form-label">等级名称</label>
                    <input type="text" name="name" class="form-input" x-model="form.name" placeholder="如：银牌代理" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">最低累计充值 (¥)</label>
                        <input type="number" name="min_recharge" class="form-input" x-model="form.min_recharge" step="0.01" min="0" required>
                        <div class="form-hint">达到此金额自动升级</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">进货价 (¥/积分)</label>
                        <input type="number" name="price_per_credit" class="form-input" x-model="form.price_per_credit" step="0.0001" min="0.0001" required>
                        <div class="form-hint">代理商购买积分单价</div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-input" x-model="form.sort_order">
                    <div class="form-hint">数字越小越靠前</div>
                </div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">保存</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="show = false">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function levelModal() {
    return {
        show: false,
        isEdit: false,
        form: { id: null, name: '', min_recharge: '0', price_per_credit: '0.1000', sort_order: 0 },
        open(data) {
            if (data && data.id) {
                this.isEdit = true;
                this.form = { ...data };
            } else {
                this.isEdit = false;
                this.form = { id: null, name: '', min_recharge: '0', price_per_credit: '0.1000', sort_order: 0 };
            }
            this.show = true;
        }
    };
}
</script>
@endpush
