@extends('agent.layout')

@section('title', '分站设置')

@section('content')
    <h1 class="page-title">分站设置</h1>
    <p class="page-subtitle">配置你的独立分站品牌和运营参数</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('agent.site-settings.update') }}">
        @csrf @method('PUT')

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><span class="card-title">基本信息</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">站点名称</label>
                    <input type="text" name="site_name" class="form-input" value="{{ old('site_name', $site->site_name) }}" required>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Logo URL</label>
                        <input type="text" name="logo_url" class="form-input" value="{{ old('logo_url', $site->logo_url) }}" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">主题色</label>
                        <input type="color" name="theme_color" class="form-input" value="{{ old('theme_color', $site->theme_color ?: '#2d5bf0') }}" style="height:40px; padding:4px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><span class="card-title">域名配置</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">路径标识 (slug)</label>
                    <input type="text" name="slug" class="form-input" value="{{ old('slug', $site->slug) }}" required pattern="[a-zA-Z0-9_-]+" maxlength="32">
                    <span class="form-hint">分站地址：{{ config('app.url') }}/s/<strong>{{ $site->slug ?: 'your-slug' }}</strong></span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">子域名</label>
                        <input type="text" name="subdomain" class="form-input" value="{{ old('subdomain', $site->subdomain) }}" placeholder="mysite" maxlength="32">
                        <span class="form-hint">访问：<strong>{{ $site->subdomain ?: 'xxx' }}.{{ config('app.domain', request()->getHost()) }}</strong></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">自定义域名</label>
                        <input type="text" name="custom_domain" class="form-input" value="{{ old('custom_domain', $site->custom_domain) }}" placeholder="www.example.com">
                        <span class="form-hint">需CNAME指向主站域名</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><span class="card-title">SEO 设置</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">SEO 标题</label>
                    <input type="text" name="seo_title" class="form-input" value="{{ old('seo_title', $site->seo_title) }}" maxlength="200">
                </div>
                <div class="form-group">
                    <label class="form-label">SEO 描述</label>
                    <textarea name="seo_description" class="form-input" rows="2" maxlength="500">{{ old('seo_description', $site->seo_description) }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">SEO 关键词</label>
                    <input type="text" name="seo_keywords" class="form-input" value="{{ old('seo_keywords', $site->seo_keywords) }}" maxlength="300" placeholder="逗号分隔">
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><span class="card-title">运营设置</span></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">公告内容</label>
                    <textarea name="announcement" class="form-input" rows="3" maxlength="1000">{{ old('announcement', $site->announcement) }}</textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">每次生成消耗积分</label>
                        <input type="number" name="cost_per_generation" class="form-input" value="{{ old('cost_per_generation', $site->cost_per_generation) }}" min="1" placeholder="留空使用系统默认">
                        <span class="form-hint">覆盖系统默认计费</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">用户分销比例 (%)</label>
                        <input type="number" name="commission_rate" class="form-input" value="{{ old('commission_rate', $site->commission_rate) }}" min="0" max="100" placeholder="留空使用系统默认">
                        <span class="form-hint">用户邀请好友消费的返利比例</span>
                    </div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif

        <button type="submit" class="btn btn-primary">保存设置</button>
    </form>
@endsection
