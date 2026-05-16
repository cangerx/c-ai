@extends('admin.layouts.app')

@section('title', '套餐管理')

@section('header')
    <h1 class="page-title">套餐管理</h1>
    <p class="page-subtitle">管理定价套餐，前端展示给用户</p>
@endsection

@section('content')
<div style="margin-bottom:16px; text-align:right;">
    <button class="btn btn-primary btn-sm" x-data @click="$dispatch('open-plan-modal', {})">添加套餐</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>排序</th>
                        <th>名称</th>
                        <th>类型</th>
                        <th>标价</th>
                        <th>次数</th>
                        <th>余额</th>
                        <th>有效期</th>
                        <th>推荐</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                    <tr>
                        <td>{{ $plan->sort_order }}</td>
                        <td>{{ $plan->name }}</td>
                        <td><span class="badge {{ $plan->type === 'subscription' ? 'badge-info' : 'badge-success' }}">{{ $plan->type === 'once' ? '一次性' : '订阅' }}</span></td>
                        <td>¥{{ $plan->price }}</td>
                        <td>{{ $plan->credits }}</td>
                        <td>¥{{ $plan->balance }}</td>
                        <td>{{ $plan->duration_days ? $plan->duration_days.'天' : '-' }}</td>
                        <td>{!! $plan->is_featured ? '<span class="badge badge-warning">推荐</span>' : '-' !!}</td>
                        <td>{!! $plan->is_active ? '<span class="badge badge-success">上架</span>' : '<span class="badge badge-danger">下架</span>' !!}</td>
                        <td style="white-space:nowrap;">
                            <button class="btn btn-ghost btn-sm" x-data @click="$dispatch('open-plan-modal', {
                                id: {{ $plan->id }},
                                name: '{{ addslashes($plan->name) }}',
                                type: '{{ $plan->type }}',
                                price: '{{ $plan->price }}',
                                credits: {{ $plan->credits }},
                                balance: '{{ $plan->balance }}',
                                duration_days: '{{ $plan->duration_days ?? '' }}',
                                is_featured: {{ $plan->is_featured ? 'true' : 'false' }},
                                sort_order: {{ $plan->sort_order }},
                                features: {{ Js::from($plan->features ?? '') }},
                                is_active: {{ $plan->is_active ? 'true' : 'false' }}
                            })">编辑</button>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" style="display:inline;"
                                  x-data @submit.prevent="$dispatch('confirm', { title: '删除套餐', message: '确定删除「{{ addslashes($plan->name) }}」？', form: $el })">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" data-no-loading>删除</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">📦</div><div class="empty-state-text">暂无套餐，点击右上角添加</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div x-data="planModal()" @open-plan-modal.window="open($event.detail)"
     x-show="show" x-cloak style="display:none; position:fixed; inset:0; z-index:9999;">
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;" @click.self="show = false">
        <div class="modal-box" style="max-width:520px; text-align:left; max-height:90vh; overflow-y:auto;">
            <div class="modal-title" style="text-align:left; margin-bottom:16px;" x-text="isEdit ? '编辑套餐' : '添加套餐'"></div>
            <form :action="isEdit ? '/admin/plans/' + form.id : '/admin/plans'" method="POST">
                @csrf
                <template x-if="isEdit"><input type="hidden" name="_method" value="PUT"></template>
                <div class="form-group">
                    <label class="form-label">套餐名称</label>
                    <input class="form-input" type="text" name="name" x-model="form.name" required placeholder="如：基础版 / 专业版">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">类型</label>
                        <select class="form-select" name="type" x-model="form.type">
                            <option value="once">一次性充值</option>
                            <option value="subscription">周期订阅</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">标价 (¥)</label>
                        <input class="form-input" type="number" name="price" x-model="form.price" min="0" step="0.01" required>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">包含次数</label>
                        <input class="form-input" type="number" name="credits" x-model="form.credits" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">包含余额 (¥)</label>
                        <input class="form-input" type="number" name="balance" x-model="form.balance" min="0" step="0.01">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group" x-show="form.type === 'subscription'">
                        <label class="form-label">有效天数</label>
                        <input class="form-input" type="number" name="duration_days" x-model="form.duration_days" min="1" placeholder="30">
                    </div>
                    <div class="form-group">
                        <label class="form-label">排序 (越小越前)</label>
                        <input class="form-input" type="number" name="sort_order" x-model="form.sort_order" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">功能描述 (每行一条)</label>
                    <textarea class="form-input" name="features" x-model="form.features" rows="3" placeholder="每行一条功能描述&#10;如：支持所有模型&#10;优先队列"></textarea>
                </div>
                <div style="display:flex; gap:16px; align-items:center;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:14px;">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" :checked="form.is_featured"> 推荐
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:14px;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" :checked="form.is_active"> 上架
                    </label>
                </div>
                <div style="display:flex; gap:12px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <button type="button" class="btn btn-ghost" @click="show = false">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function planModal() {
    return {
        show: false,
        isEdit: false,
        form: { id: null, name: '', type: 'once', price: '', credits: 0, balance: '0', duration_days: '', is_featured: false, sort_order: 0, features: '', is_active: true },
        open(data) {
            if (data && data.id) {
                this.isEdit = true;
                this.form = { ...data };
            } else {
                this.isEdit = false;
                this.form = { id: null, name: '', type: 'once', price: '', credits: 0, balance: '0', duration_days: '', is_featured: false, sort_order: 0, features: '', is_active: true };
            }
            this.show = true;
        }
    };
}
</script>
@endpush
