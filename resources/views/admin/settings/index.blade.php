@extends('admin.layouts.app')

@section('title', '站点设置')

@section('header')
    <h1 class="page-title">站点设置</h1>
    <p class="page-subtitle">管理平台基础配置</p>
@endsection

@section('content')
<div x-data="{ tab: {{ Js::from(request('tab', 'seo')) }} }">
    <div style="display:flex; gap:4px; margin-bottom:20px; border-bottom:1px solid var(--line); padding-bottom:0;">
        <button type="button" class="tab-btn" :class="tab === 'seo' && 'active'" @click="tab = 'seo'">SEO / 基础信息</button>
        <button type="button" class="tab-btn" :class="tab === 'model' && 'active'" @click="tab = 'model'">模型设置</button>
        <button type="button" class="tab-btn" :class="tab === 'billing' && 'active'" @click="tab = 'billing'">计费设置</button>
        <button type="button" class="tab-btn" :class="tab === 'register' && 'active'" @click="tab = 'register'">注册与代理</button>
        <button type="button" class="tab-btn" :class="tab === 'oauth' && 'active'" @click="tab = 'oauth'">第三方登录</button>
    </div>

    {{-- SEO / 基础信息 --}}
    <div x-show="tab === 'seo'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="seo">
            <div class="card">
                <div class="card-header"><span class="card-title">SEO / 基础信息</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'site_name', 'label' => '站点名称', 'placeholder' => 'CANG-AI'],
                        ['key' => 'site_description', 'label' => '站点描述（SEO Description）', 'placeholder' => 'AI 图像生成平台'],
                        ['key' => 'site_keywords', 'label' => 'SEO 关键词', 'placeholder' => 'AI,图像生成,绘画'],
                    ]; @endphp
                    @foreach($fields as $i => $field)
                        <div class="form-group">
                            <label class="form-label">{{ $field['label'] }}</label>
                            <input class="form-input" type="text" name="settings[{{ $i }}][value]"
                                   value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                   placeholder="{{ $field['placeholder'] }}">
                            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                            <input type="hidden" name="settings[{{ $i }}][group]" value="general">
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>

    {{-- 模型设置 --}}
    <div x-show="tab === 'model'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="model">
            <div class="card">
                <div class="card-header"><span class="card-title">模型设置</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'prompt_tool_model', 'label' => '提示词工具模型（反推/优化/翻译）', 'placeholder' => 'gpt-5.4-mini'],
                    ]; @endphp
                    @foreach($fields as $i => $field)
                        <div class="form-group">
                            <label class="form-label">{{ $field['label'] }}</label>
                            <input class="form-input" type="text" name="settings[{{ $i }}][value]"
                                   value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                   placeholder="{{ $field['placeholder'] }}">
                            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                            <input type="hidden" name="settings[{{ $i }}][group]" value="model">
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>

    {{-- 计费设置 --}}
    <div x-show="tab === 'billing'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="billing">
            <div class="card">
                <div class="card-header"><span class="card-title">计费设置</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'billing_low_credits', 'label' => '标清(low) 每次扣次数', 'placeholder' => '1'],
                        ['key' => 'billing_low_balance', 'label' => '标清(low) 每次扣额度 (¥)', 'placeholder' => '0.10'],
                        ['key' => 'billing_medium_credits', 'label' => '高清(medium) 每次扣次数', 'placeholder' => '2'],
                        ['key' => 'billing_medium_balance', 'label' => '高清(medium) 每次扣额度 (¥)', 'placeholder' => '0.30'],
                        ['key' => 'billing_high_credits', 'label' => '超清(high) 每次扣次数', 'placeholder' => '4'],
                        ['key' => 'billing_high_balance', 'label' => '超清(high) 每次扣额度 (¥)', 'placeholder' => '1.00'],
                    ]; @endphp
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        @foreach($fields as $i => $field)
                            <div class="form-group">
                                <label class="form-label">{{ $field['label'] }}</label>
                                <input class="form-input" type="text" name="settings[{{ $i }}][value]"
                                       value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                       placeholder="{{ $field['placeholder'] }}">
                                <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                                <input type="hidden" name="settings[{{ $i }}][group]" value="billing">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>

    {{-- 注册与代理 --}}
    <div x-show="tab === 'register'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="register">
            <div class="card">
                <div class="card-header"><span class="card-title">注册与代理</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'register_gift_credits', 'label' => '注册赠送次数', 'placeholder' => '5', 'group' => 'billing'],
                        ['key' => 'register_gift_balance', 'label' => '注册赠送余额 (¥)', 'placeholder' => '0', 'group' => 'billing'],
                        ['key' => 'agent_commission_rate', 'label' => '代理佣金比例（0~1）', 'placeholder' => '0.10', 'group' => 'agent'],
                        ['key' => 'agent_register_enabled', 'label' => '开放代理注册（1=开启 0=关闭）', 'placeholder' => '0', 'group' => 'agent'],
                        ['key' => 'agent_register_url', 'label' => '代理注册地址（留空使用默认）', 'placeholder' => '/agent/register', 'group' => 'agent'],
                    ]; @endphp
                    @foreach($fields as $i => $field)
                        <div class="form-group">
                            <label class="form-label">{{ $field['label'] }}</label>
                            <input class="form-input" type="text" name="settings[{{ $i }}][value]"
                                   value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                   placeholder="{{ $field['placeholder'] }}">
                            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                            <input type="hidden" name="settings[{{ $i }}][group]" value="{{ $field['group'] }}">
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>

    {{-- 第三方登录 --}}
    <div x-show="tab === 'oauth'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="oauth">
            <div class="card">
                <div class="card-header"><span class="card-title">GitHub OAuth 登录</span></div>
                <div class="card-body">
                    <p style="font-size:12px; color:var(--text-secondary); margin-bottom:16px;">
                        前往 <a href="https://github.com/settings/developers" target="_blank" style="color:var(--accent);">GitHub Developer Settings</a> 创建 OAuth App，回调地址填写：<code>{{ url('/api/auth/github/callback') }}</code>
                    </p>
                    @php $fields = [
                        ['key' => 'github_client_id', 'label' => 'Client ID', 'placeholder' => 'Ov23li...'],
                        ['key' => 'github_client_secret', 'label' => 'Client Secret', 'placeholder' => 'secret...'],
                    ]; @endphp
                    @foreach($fields as $i => $field)
                        <div class="form-group">
                            <label class="form-label">{{ $field['label'] }}</label>
                            <input class="form-input" type="text" name="settings[{{ $i }}][value]"
                                   value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                   placeholder="{{ $field['placeholder'] }}">
                            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                            <input type="hidden" name="settings[{{ $i }}][group]" value="oauth">
                        </div>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>
</div>

<style>
    .tab-btn { padding: 10px 18px; font: inherit; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.15s; margin-bottom: -1px; }
    .tab-btn:hover { color: var(--accent); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>
@endsection
