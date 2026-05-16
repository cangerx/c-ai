@extends('agent.layout')

@section('title', '套餐管理')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 class="page-title">套餐管理</h1>
            <p class="page-subtitle" style="margin:0;">管理分站定价套餐</p>
        </div>
        <button class="btn btn-primary" @click="$dispatch('open-plan-modal', {})">新建套餐</button>
    </div>

    <div class="card">
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr><th>名称</th><th>售价</th><th>积分</th><th>余额</th><th>推荐</th><th>排序</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td>¥{{ number_format($plan->price, 2) }}</td>
                        <td>{{ $plan->credits }}</td>
                        <td>¥{{ number_format($plan->balance, 2) }}</td>
                        <td>{!! $plan->is_featured ? '<span class="badge badge-warning">推荐</span>' : '—' !!}</td>
                        <td>{{ $plan->sort_order }}</td>
                        <td>{!! $plan->is_active ? '<span class="badge badge-success">启用</span>' : '<span class="badge badge-danger">停用</span>' !!}</td>
                        <td style="display:flex; gap:6px;">
                            <button class="btn btn-sm btn-ghost" @click="$dispatch('open-plan-modal', {{ $plan->toJson() }})">编辑</button>
                            <form method="POST" action="{{ route('agent.plans.destroy', $plan) }}"
                                  x-data @submit.prevent="$dispatch('confirm', { title:'删除套餐', message:'确定删除此套餐？', form: $el })">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">删除</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="empty-state"><div class="empty-state-icon">📋</div><div class="empty-state-text">暂无套餐</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Plan Modal -->
    <div x-data="planModal()" @open-plan-modal.window="open($event.detail)" x-cloak>
        <div class="modal-backdrop" x-show="show" @click="show=false">
            <div class="modal-box" @click.stop>
                <h3 style="font-size:16px; font-weight:600; margin-bottom:16px;" x-text="editing ? '编辑套餐' : '新建套餐'"></h3>
                <form :action="editing ? '/agent/plans/' + id : '{{ route('agent.plans.store') }}'" method="POST">
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="form-group">
                        <label class="form-label">名称</label>
                        <input type="text" name="name" class="form-input" x-model="form.name" required>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">售价 (¥)</label>
                            <input type="number" name="price" class="form-input" x-model="form.price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">积分</label>
                            <input type="number" name="credits" class="form-input" x-model="form.credits" min="0" required>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div class="form-group">
                            <label class="form-label">余额 (¥)</label>
                            <input type="number" name="balance" class="form-input" x-model="form.balance" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">排序</label>
                            <input type="number" name="sort_order" class="form-input" x-model="form.sort_order">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">特性（每行一条）</label>
                        <textarea name="features" class="form-input" rows="3" x-model="form.features" placeholder="每行一条特性说明"></textarea>
                    </div>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <label style="display:flex; align-items:center; gap:6px; font-size:13px;">
                            <input type="hidden" name="is_featured" value="0">
                            <input type="checkbox" name="is_featured" value="1" :checked="form.is_featured"> 推荐
                        </label>
                        <label x-show="editing" style="display:flex; align-items:center; gap:6px; font-size:13px;">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" :checked="form.is_active"> 启用
                        </label>
                    </div>
                    <div style="display:flex; gap:12px; margin-top:20px; justify-content:flex-end;">
                        <button type="button" class="btn btn-ghost" @click="show=false">取消</button>
                        <button type="submit" class="btn btn-primary" x-text="editing ? '保存' : '创建'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function planModal() {
    return {
        show: false, editing: false, id: null,
        form: { name:'', price:0, credits:0, balance:0, sort_order:0, features:'', is_featured:false, is_active:true },
        open(plan) {
            if (plan && plan.id) {
                this.editing = true; this.id = plan.id;
                this.form = { name:plan.name, price:plan.price, credits:plan.credits, balance:plan.balance, sort_order:plan.sort_order, features:plan.features||'', is_featured:!!plan.is_featured, is_active:!!plan.is_active };
            } else {
                this.editing = false; this.id = null;
                this.form = { name:'', price:0, credits:0, balance:0, sort_order:0, features:'', is_featured:false, is_active:true };
            }
            this.show = true;
        }
    };
}
</script>
@endpush
