@extends('admin.layouts.app')

@section('title', '云存储配置')

@section('header')
    <h1 class="page-title">云存储配置</h1>
    <p class="page-subtitle">管理图片对象存储服务（OSS / R2）</p>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.storage.update') }}">
        @csrf

        <div class="card" style="margin-bottom: 20px;">
            <div class="card-header">
                <span class="card-title">存储驱动</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">驱动类型</label>
                    <select class="form-input" name="storage_driver" id="storage_driver">
                        @php $currentDriver = \App\Models\SiteSetting::get('storage_driver', 'local'); @endphp
                        <option value="local" {{ $currentDriver === 'local' ? 'selected' : '' }}>本地存储 (local)</option>
                        <option value="oss" {{ $currentDriver === 'oss' ? 'selected' : '' }}>阿里云 OSS</option>
                        <option value="r2" {{ $currentDriver === 'r2' ? 'selected' : '' }}>Cloudflare R2</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;" id="cloud-config">
            <div class="card-header">
                <span class="card-title">云存储凭证</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Access Key ID</label>
                    <input class="form-input" type="text" name="storage_access_key"
                           value="{{ \App\Models\SiteSetting::get('storage_access_key', '') }}"
                           placeholder="输入 Access Key">
                </div>
                <div class="form-group">
                    <label class="form-label">Secret Access Key</label>
                    <input class="form-input" type="password" name="storage_secret_key"
                           value=""
                           placeholder="留空则不修改">
                </div>
                <div class="form-group">
                    <label class="form-label">Bucket 名称</label>
                    <input class="form-input" type="text" name="storage_bucket"
                           value="{{ \App\Models\SiteSetting::get('storage_bucket', '') }}"
                           placeholder="my-bucket">
                </div>
                <div class="form-group">
                    <label class="form-label">Endpoint</label>
                    <input class="form-input" type="text" name="storage_endpoint"
                           value="{{ \App\Models\SiteSetting::get('storage_endpoint', '') }}"
                           placeholder="https://oss-cn-hangzhou.aliyuncs.com 或 https://<account_id>.r2.cloudflarestorage.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Region</label>
                    <input class="form-input" type="text" name="storage_region"
                           value="{{ \App\Models\SiteSetting::get('storage_region', '') }}"
                           placeholder="oss-cn-hangzhou / auto">
                </div>
                <div class="form-group">
                    <label class="form-label">自定义域名（CDN）</label>
                    <input class="form-input" type="text" name="storage_url"
                           value="{{ \App\Models\SiteSetting::get('storage_url', '') }}"
                           placeholder="https://cdn.example.com">
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary">保存配置</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.storage.test') }}" style="margin-top: 12px;">
        @csrf
        <button type="submit" class="btn btn-secondary">测试连接</button>
    </form>

    <script>
        document.getElementById('storage_driver').addEventListener('change', function() {
            document.getElementById('cloud-config').style.display = this.value === 'local' ? 'none' : '';
        });
        // 初始化
        if (document.getElementById('storage_driver').value === 'local') {
            document.getElementById('cloud-config').style.display = 'none';
        }
    </script>
@endsection
