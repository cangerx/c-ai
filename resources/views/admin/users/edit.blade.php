@extends('admin.layouts.app')

@section('title', '编辑用户')

@section('header')
    <h1 class="page-title">编辑用户 #{{ $user->id }}</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label class="form-label">用户名</label>
                    <input class="form-input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input class="form-input" type="email" value="{{ $user->email }}" disabled>
                    <div class="form-hint">邮箱不可修改</div>
                </div>
                <div class="form-group">
                    <label class="form-label">昵称</label>
                    <input class="form-input" type="text" name="nickname" value="{{ old('nickname', $user->nickname) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">角色</label>
                    <select class="form-select" name="role">
                        <option value="user" {{ $user->role === 'user' ? 'selected' : '' }}>普通用户</option>
                        <option value="agent" {{ $user->role === 'agent' ? 'selected' : '' }}>代理商</option>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>管理员</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">次数</label>
                        <input class="form-input" type="number" name="credits" value="{{ old('credits', $user->credits) }}" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">额度</label>
                        <input class="form-input" type="number" name="balance" value="{{ old('balance', $user->balance) }}" min="0" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">重置密码</label>
                    <input class="form-input" type="password" name="password" placeholder="留空则不修改">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex; align-items:center; gap:8px;">
                        <input type="hidden" name="is_distributor" value="0">
                        <input type="checkbox" name="is_distributor" value="1" {{ $user->is_distributor ? 'checked' : '' }}>
                        分销者
                    </label>
                    <div class="form-hint">开启后用户可获得下级消费返利</div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
