@extends('admin.layouts.app')

@section('title', '计费规则')

@section('header')
    <h1 class="page-title">计费规则</h1>
    <p class="page-subtitle">按模型和质量设置不同价格</p>
@endsection

@section('content')
    <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
        <button class="btn btn-primary btn-sm" x-data @click="$dispatch('edit-rule', { id: null, app_name: 'image-gen', model_pattern: '', quality: '', cost_credits: 1, cost_balance: '0.10' })">新增规则</button>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>应用</th>
                            <th>模型匹配</th>
                            <th>质量</th>
                            <th>扣除次数</th>
                            <th>扣除余额</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td>{{ $rule->app_name }}</td>
                                <td><code>{{ $rule->model_pattern }}</code></td>
                                <td>{{ $rule->quality ?? '全部' }}</td>
                                <td>{{ $rule->cost_credits }}</td>
                                <td>¥{{ $rule->cost_balance }}</td>
                                <td>
                                    <button class="btn btn-ghost btn-sm" x-data @click="$dispatch('edit-rule', {
                                        id: {{ $rule->id }},
                                        app_name: '{{ addslashes($rule->app_name) }}',
                                        model_pattern: '{{ addslashes($rule->model_pattern) }}',
                                        quality: '{{ $rule->quality ?? '' }}',
                                        cost_credits: {{ $rule->cost_credits }},
                                        cost_balance: '{{ $rule->cost_balance }}'
                                    })">编辑</button>
                                    <form method="POST" action="{{ route('admin.billing-rules.destroy', $rule) }}" style="display:inline;"
                                          x-data @submit.prevent="$dispatch('confirm', { title: '删除规则', message: '确定删除此计费规则？', form: $el })">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);" data-no-loading>删除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center; padding: 24px; color: var(--muted);">暂无规则，将使用站点设置中的默认价格</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit/Create Rule Modal -->
    <div x-data="editRuleModal()" @edit-rule.window="open($event.detail)"
         x-show="show" x-cloak style="display:none;">
        <div class="modal-backdrop" @click.self="show = false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="modal-box" style="max-width: 460px; text-align: left;"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="modal-title" style="text-align:left; margin-bottom:16px;" x-text="ruleId ? '编辑计费规则' : '新增计费规则'"></div>
                <form :action="ruleId ? '/admin/billing-rules/' + ruleId : '/admin/billing-rules'" method="POST">
                    @csrf
                    <template x-if="ruleId"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="form-group">
                        <label class="form-label">应用名称</label>
                        <input type="text" name="app_name" class="form-input" x-model="form.app_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">模型匹配</label>
                        <input type="text" name="model_pattern" class="form-input" x-model="form.model_pattern" required>
                        <small class="form-hint">精确模型名或 * 匹配全部</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">质量</label>
                        <select name="quality" class="form-select" x-model="form.quality">
                            <option value="">全部质量</option>
                            <option value="low">low</option>
                            <option value="medium">medium</option>
                            <option value="high">high</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">扣除次数</label>
                            <input type="number" name="cost_credits" class="form-input" x-model="form.cost_credits" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">扣除余额 (¥)</label>
                            <input type="number" name="cost_balance" class="form-input" x-model="form.cost_balance" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px; margin-top: 8px;">
                        <button type="submit" class="btn btn-primary">保存</button>
                        <button type="button" class="btn btn-ghost" @click="show = false">取消</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function editRuleModal() {
    return {
        show: false,
        ruleId: null,
        form: { app_name: '', model_pattern: '', quality: '', cost_credits: 1, cost_balance: '0.10' },
        open(data) {
            this.ruleId = data.id;
            this.form = { app_name: data.app_name, model_pattern: data.model_pattern, quality: data.quality, cost_credits: data.cost_credits, cost_balance: data.cost_balance };
            this.show = true;
        }
    };
}
</script>
@endpush
