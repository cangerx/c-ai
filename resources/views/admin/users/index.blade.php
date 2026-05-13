@extends('admin.layouts.app')

@section('title', '用户管理')

@section('header')
    <h1 class="page-title">用户管理</h1>
    <p class="page-subtitle">管理平台所有用户账号</p>
@endsection

@section('content')
    <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;">
        <form style="display: flex; gap: 8px; flex: 1; min-width: 200px;" method="GET">
            <input class="form-input" type="text" name="q" value="{{ request('q') }}" placeholder="搜索邮箱/用户名..." style="max-width: 280px;">
            <select class="form-select" name="role" style="max-width: 130px;" onchange="this.form.submit()">
                <option value="">全部角色</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>管理员</option>
                <option value="agent" {{ request('role') === 'agent' ? 'selected' : '' }}>代理商</option>
                <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>用户</option>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">搜索</button>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">添加用户</a>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>昵称</th>
                            <th>邮箱</th>
                            <th>角色</th>
                            <th>余额</th>
                            <th>次数</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->nickname ?? $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge badge-info">{{ $user->role }}</span></td>
                            <td>¥{{ number_format($user->balance, 2) }}</td>
                            <td>{{ $user->credits }}</td>
                            <td>
                                @if($user->status === 'active')
                                    <span class="badge badge-success">正常</span>
                                @else
                                    <span class="badge badge-danger">禁用</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td style="white-space: nowrap;">
                                <button class="btn btn-ghost btn-sm" x-data @click="$dispatch('edit-user', {
                                    id: {{ $user->id }},
                                    name: '{{ addslashes($user->name) }}',
                                    nickname: '{{ addslashes($user->nickname ?? '') }}',
                                    role: '{{ $user->role }}',
                                    credits: {{ $user->credits }},
                                    balance: {{ $user->balance }}
                                })">编辑</button>
                                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" style="display:inline;"
                                      x-data @submit.prevent="$dispatch('confirm', { title: '{{ $user->status === 'active' ? '禁用用户' : '启用用户' }}', message: '确定{{ $user->status === 'active' ? '禁用' : '启用' }}用户「{{ $user->nickname ?? $user->name }}」？', form: $el })">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $user->status === 'active' ? 'btn-ghost' : 'btn-primary' }}" data-no-loading>
                                        {{ $user->status === 'active' ? '禁用' : '启用' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">👤</div>
                                    <div class="empty-state-text">暂无用户</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="margin-top: 16px;">{{ $users->links() }}</div>

    <!-- Edit User Modal -->
    <div x-data="editUserModal()" @edit-user.window="open($event.detail)"
         x-show="show" x-cloak style="display:none;">
        <div class="modal-backdrop" @click.self="show = false"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="modal-box" style="max-width: 480px; text-align: left;"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
                <div class="modal-title" style="text-align:left; margin-bottom:16px;">编辑用户</div>
                <form :action="'/admin/users/' + userId" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="form-label">用户名</label>
                        <input class="form-input" type="text" name="name" x-model="form.name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">昵称</label>
                        <input class="form-input" type="text" name="nickname" x-model="form.nickname">
                    </div>
                    <div class="form-group">
                        <label class="form-label">角色</label>
                        <select class="form-select" name="role" x-model="form.role">
                            <option value="user">普通用户</option>
                            <option value="agent">代理商</option>
                            <option value="admin">管理员</option>
                        </select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">次数</label>
                            <input class="form-input" type="number" name="credits" x-model="form.credits" min="0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">额度</label>
                            <input class="form-input" type="number" name="balance" x-model="form.balance" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">重置密码</label>
                        <input class="form-input" type="password" name="password" placeholder="留空则不修改">
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
function editUserModal() {
    return {
        show: false,
        userId: null,
        form: { name: '', nickname: '', role: 'user', credits: 0, balance: 0 },
        open(data) {
            this.userId = data.id;
            this.form = { name: data.name, nickname: data.nickname, role: data.role, credits: data.credits, balance: data.balance };
            this.show = true;
        }
    };
}
</script>
@endpush
