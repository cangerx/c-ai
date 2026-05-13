@extends('admin.layouts.app')

@section('title', '添加用户')

@section('header')
    <h1 class="page-title">添加用户</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">用户名</label>
                    <input class="form-input" type="text" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input class="form-input" type="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input class="form-input" type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">昵称</label>
                    <input class="form-input" type="text" name="nickname" value="{{ old('nickname') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">角色</label>
                    <select class="form-select" name="role">
                        <option value="user">普通用户</option>
                        <option value="agent">代理商</option>
                        <option value="admin">管理员</option>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">初始次数</label>
                        <input class="form-input" type="number" name="credits" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">初始额度</label>
                        <input class="form-input" type="number" name="balance" value="0" min="0" step="0.01">
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">创建用户</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
