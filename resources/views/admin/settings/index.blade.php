@extends('admin.layouts.app')

@section('title', '站点设置')

@section('header')
    <h1 class="page-title">站点设置</h1>
    <p class="page-subtitle">管理平台基础配置</p>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <span class="card-title">基础信息</span>
            </div>
            <div class="card-body">
                @php
                    $general = [
                        ['key' => 'site_name', 'label' => '站点名称', 'placeholder' => 'CANG-AI', 'group' => 'general'],
                        ['key' => 'site_description', 'label' => '站点描述', 'placeholder' => 'AI 图像生成平台', 'group' => 'general'],
                        ['key' => 'announcement', 'label' => '公告内容', 'placeholder' => '欢迎使用 CANG-AI', 'group' => 'general'],
                    ];
                @endphp
                @foreach($general as $i => $field)
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

        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <span class="card-title">计费设置</span>
            </div>
            <div class="card-body">
                @php
                    $billing = [
                        ['key' => 'billing_low_credits', 'label' => '标清(low) 每次扣次数', 'placeholder' => '1', 'group' => 'billing'],
                        ['key' => 'billing_low_balance', 'label' => '标清(low) 每次扣额度', 'placeholder' => '0.10', 'group' => 'billing'],
                        ['key' => 'billing_medium_credits', 'label' => '高清(medium) 每次扣次数', 'placeholder' => '2', 'group' => 'billing'],
                        ['key' => 'billing_medium_balance', 'label' => '高清(medium) 每次扣额度', 'placeholder' => '0.30', 'group' => 'billing'],
                        ['key' => 'billing_high_credits', 'label' => '超清(high) 每次扣次数', 'placeholder' => '4', 'group' => 'billing'],
                        ['key' => 'billing_high_balance', 'label' => '超清(high) 每次扣额度', 'placeholder' => '1.00', 'group' => 'billing'],
                    ];
                    $offset = count($general);
                @endphp
                @foreach($billing as $j => $field)
                    <div class="form-group">
                        <label class="form-label">{{ $field['label'] }}</label>
                        <input class="form-input" type="text" name="settings[{{ $offset + $j }}][value]"
                               value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                               placeholder="{{ $field['placeholder'] }}">
                        <input type="hidden" name="settings[{{ $offset + $j }}][key]" value="{{ $field['key'] }}">
                        <input type="hidden" name="settings[{{ $offset + $j }}][group]" value="{{ $field['group'] }}">
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <span class="card-title">注册与代理</span>
            </div>
            <div class="card-body">
                @php
                    $regAgent = [
                        ['key' => 'register_gift_credits', 'label' => '注册赠送次数', 'placeholder' => '5', 'group' => 'billing'],
                        ['key' => 'register_gift_balance', 'label' => '注册赠送余额', 'placeholder' => '0', 'group' => 'billing'],
                        ['key' => 'agent_commission_rate', 'label' => '代理佣金比例（0~1）', 'placeholder' => '0.10', 'group' => 'agent'],
                    ];
                    $offset2 = $offset + count($billing);
                @endphp
                @foreach($regAgent as $k => $field)
                    <div class="form-group">
                        <label class="form-label">{{ $field['label'] }}</label>
                        <input class="form-input" type="text" name="settings[{{ $offset2 + $k }}][value]"
                               value="{{ \App\Models\SiteSetting::get($field['key'], '') }}"
                               placeholder="{{ $field['placeholder'] }}">
                        <input type="hidden" name="settings[{{ $offset2 + $k }}][key]" value="{{ $field['key'] }}">
                        <input type="hidden" name="settings[{{ $offset2 + $k }}][group]" value="{{ $field['group'] }}">
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary">保存设置</button>
    </form>
@endsection
