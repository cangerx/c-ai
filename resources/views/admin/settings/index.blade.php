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
        <button type="button" class="tab-btn" :class="tab === 'register' && 'active'" @click="tab = 'register'">注册与分销</button>
        <button type="button" class="tab-btn" :class="tab === 'mail' && 'active'" @click="tab = 'mail'">邮件配置</button>
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
                    <div class="form-group" style="max-width:300px;">
                        <label class="form-label">每次生成扣积分数</label>
                        <input class="form-input" type="number" name="settings[0][value]"
                               value="{{ \App\Models\SiteSetting::get('billing_per_generation', '1') }}"
                               placeholder="1" min="1">
                        <input type="hidden" name="settings[0][key]" value="billing_per_generation">
                        <input type="hidden" name="settings[0][group]" value="billing">
                        <div class="form-hint">不区分模型、尺寸、质量，统一扣固定积分</div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">保存</button>
        </form>
    </div>

    {{-- 注册与分销 --}}
    <div x-show="tab === 'register'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="register">
            <div class="card">
                <div class="card-header"><span class="card-title">注册与分销</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'register_gift_credits', 'label' => '注册赠送积分', 'placeholder' => '5', 'group' => 'billing'],
                        ['key' => 'distributor_threshold', 'label' => '分销申请门槛（累计消费积分数）', 'placeholder' => '100', 'group' => 'distributor'],
                        ['key' => 'distributor_commission_rate', 'label' => '分销返利比例（0~1，如0.1=10%）', 'placeholder' => '0.10', 'group' => 'distributor'],
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
    {{-- 邮件配置 --}}
    <div x-show="tab === 'mail'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            <input type="hidden" name="tab" value="mail">
            <div class="card">
                <div class="card-header"><span class="card-title">SMTP 邮件配置</span></div>
                <div class="card-body">
                    @php $fields = [
                        ['key' => 'mail_host', 'label' => 'SMTP 服务器', 'placeholder' => 'smtp.qq.com', 'type' => 'text'],
                        ['key' => 'mail_port', 'label' => '端口', 'placeholder' => '465', 'type' => 'number'],
                        ['key' => 'mail_username', 'label' => '用户名（邮箱地址）', 'placeholder' => 'noreply@example.com', 'type' => 'text'],
                        ['key' => 'mail_password', 'label' => '密码 / 授权码', 'placeholder' => '', 'type' => 'password'],
                        ['key' => 'mail_encryption', 'label' => '加密方式', 'placeholder' => 'ssl', 'type' => 'text'],
                        ['key' => 'mail_from_address', 'label' => '发件人地址', 'placeholder' => 'noreply@example.com', 'type' => 'text'],
                        ['key' => 'mail_from_name', 'label' => '发件人名称', 'placeholder' => 'CANG-AI', 'type' => 'text'],
                    ]; @endphp
                    @foreach($fields as $i => $field)
                        <div class="form-group">
                            <label class="form-label">{{ $field['label'] }}</label>
                            <input class="form-input" type="{{ $field['type'] }}" name="settings[{{ $i }}][value]"
                                   value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                                   placeholder="{{ $field['placeholder'] }}">
                            <input type="hidden" name="settings[{{ $i }}][key]" value="{{ $field['key'] }}">
                            <input type="hidden" name="settings[{{ $i }}][group]" value="mail">
                        </div>
                    @endforeach
                    <div class="form-hint" style="margin-top:12px;">
                        常见配置：QQ邮箱(smtp.qq.com:465/ssl)、163邮箱(smtp.163.com:465/ssl)、阿里企业邮(smtp.mxhichina.com:465/ssl)
                    </div>
                </div>
            </div>
            <div style="display:flex; gap:12px; align-items:center;">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn btn-secondary" onclick="testMail()">发送测试邮件</button>
                <span id="testMailResult" style="font-size:13px;"></span>
            </div>
        </form>
    </div>
</div>

<script>
async function testMail() {
    const el = document.getElementById('testMailResult');
    el.textContent = '发送中...';
    el.style.color = '#6b7280';
    try {
        const res = await fetch('{{ route("admin.settings.test-mail") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json'},
        });
        const data = await res.json();
        el.textContent = data.message;
        el.style.color = res.ok ? '#16a34a' : '#dc2626';
    } catch { el.textContent = '请求失败'; el.style.color = '#dc2626'; }
}
</script>

<style>
    .tab-btn { padding: 10px 18px; font: inherit; font-size: 13px; font-weight: 600; color: var(--text-secondary); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.15s; margin-bottom: -1px; }
    .tab-btn:hover { color: var(--accent); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
</style>
@endsection
