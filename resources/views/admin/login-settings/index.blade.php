@extends('admin.layouts.app')

@section('title', '登录设置')

@section('header')
    <h1 class="page-title">登录设置</h1>
    <p class="page-subtitle">管理第三方登录方式</p>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.login-settings.update') }}">
    @csrf
    @php $idx = 0; @endphp

    {{-- GitHub --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span class="card-title">GitHub 登录</span>
            <label class="toggle-switch">
                <input type="hidden" name="settings[{{ $idx }}][key]" value="login_github_enabled">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
                <input type="checkbox" name="settings[{{ $idx }}][value]" value="1" {{ \App\Models\SiteSetting::get('login_github_enabled', '0') === '1' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="card-body">
            @php $idx++; @endphp
            <div class="form-group">
                <label class="form-label">Client ID</label>
                <input class="form-input" type="text" name="settings[{{ $idx }}][value]" value="{{ \App\Models\SiteSetting::get('github_client_id', '') }}" placeholder="GitHub OAuth App Client ID">
                <input type="hidden" name="settings[{{ $idx }}][key]" value="github_client_id">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
            </div>
            @php $idx++; @endphp
            <div class="form-group">
                <label class="form-label">Client Secret</label>
                <input class="form-input" type="password" name="settings[{{ $idx }}][value]" value="{{ \App\Models\SiteSetting::get('github_client_secret', '') }}" placeholder="GitHub OAuth App Client Secret">
                <input type="hidden" name="settings[{{ $idx }}][key]" value="github_client_secret">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
            </div>
        </div>
    </div>

    {{-- WeChat --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
            <span class="card-title">微信扫码登录</span>
            <label class="toggle-switch">
                @php $idx++; @endphp
                <input type="hidden" name="settings[{{ $idx }}][key]" value="login_wechat_enabled">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
                <input type="checkbox" name="settings[{{ $idx }}][value]" value="1" {{ \App\Models\SiteSetting::get('login_wechat_enabled', '0') === '1' ? 'checked' : '' }}>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="card-body">
            @php $idx++; @endphp
            <div class="form-group">
                <label class="form-label">AppID</label>
                <input class="form-input" type="text" name="settings[{{ $idx }}][value]" value="{{ \App\Models\SiteSetting::get('wechat_appid', '') }}" placeholder="微信开放平台 AppID">
                <input type="hidden" name="settings[{{ $idx }}][key]" value="wechat_appid">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
            </div>
            @php $idx++; @endphp
            <div class="form-group">
                <label class="form-label">AppSecret</label>
                <input class="form-input" type="password" name="settings[{{ $idx }}][value]" value="{{ \App\Models\SiteSetting::get('wechat_secret', '') }}" placeholder="微信开放平台 AppSecret">
                <input type="hidden" name="settings[{{ $idx }}][key]" value="wechat_secret">
                <input type="hidden" name="settings[{{ $idx }}][group]" value="login">
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">保存</button>
</form>

<style>
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:var(--bg-tertiary); border-radius:24px; transition:.2s; }
.toggle-slider:before { content:""; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:var(--accent); }
.toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
</style>
@endsection
