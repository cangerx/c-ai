@extends('admin.layouts.app')

@section('title', $channel ? '编辑渠道' : '添加渠道')

@section('header')
    <h1 class="page-title">{{ $channel ? '编辑渠道' : '添加渠道' }}</h1>
@endsection

@section('content')
    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ $channel ? route('admin.image-gen.channels.update', $channel) : route('admin.image-gen.channels.store') }}">
                @csrf
                @if($channel) @method('PUT') @endif

                <div class="form-group">
                    <label class="form-label">渠道名称</label>
                    <input class="form-input" type="text" name="name" value="{{ old('name', $channel?->name) }}" required placeholder="例: 主渠道">
                </div>
                <div class="form-group">
                    <label class="form-label">前端显示名称</label>
                    <input class="form-input" type="text" name="display_name" value="{{ old('display_name', $channel?->display_name) }}" placeholder="留空则使用模型名，如: GPT 绘图">
                    <div class="form-hint">用户在前端看到的模型名称</div>
                </div>
                <div class="form-group">
                    <label class="form-label">服务商</label>
                    <input class="form-input" type="text" name="provider" value="{{ old('provider', $channel?->provider ?? 'openai') }}" required placeholder="openai / azure / custom">
                </div>
                <div class="form-group">
                    <label class="form-label">接口地址</label>
                    <input class="form-input" type="url" name="base_url" value="{{ old('base_url', $channel?->base_url) }}" required placeholder="https://api.openai.com">
                </div>
                <div class="form-group">
                    <label class="form-label">API Key</label>
                    <input class="form-input" type="text" name="api_key" value="{{ old('api_key', $channel?->api_key) }}" required placeholder="sk-...">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">默认模型</label>
                        <input class="form-input" type="text" name="model" value="{{ old('model', $channel?->model ?? 'gpt-image-2') }}" placeholder="gpt-image-2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">请求模式</label>
                        <select class="form-input" name="request_mode">
                            <option value="sync" {{ old('request_mode', $channel?->request_mode ?? 'sync') === 'sync' ? 'selected' : '' }}>同步</option>
                            <option value="async" {{ old('request_mode', $channel?->request_mode) === 'async' ? 'selected' : '' }}>异步轮询 (dm)</option>
                            <option value="stream" {{ old('request_mode', $channel?->request_mode) === 'stream' ? 'selected' : '' }}>流式 (SSE)</option>
                        </select>
                        <div class="form-hint">异步适用于 duomiapi 等接口</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">优先级</label>
                        <input class="form-input" type="number" name="priority" value="{{ old('priority', $channel?->priority ?? 10) }}" min="0" max="100">
                        <div class="form-hint">数字越大优先级越高</div>
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" class="btn btn-primary">{{ $channel ? '保存' : '创建' }}</button>
                    <a href="{{ route('admin.image-gen.channels') }}" class="btn btn-ghost">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
