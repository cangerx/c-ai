@extends('admin.layouts.app')

@section('title', 'AI 渠道管理')

@section('header')
    <h1 class="page-title">AI 渠道管理</h1>
    <p class="page-subtitle">管理图像生成 API 渠道</p>
@endsection

@section('content')
    <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
        <a href="{{ route('admin.image-gen.channels.create') }}" class="btn btn-primary btn-sm">添加渠道</a>
    </div>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>名称</th>
                            <th>服务商</th>
                            <th>接口地址</th>
                            <th>模型</th>
                            <th>优先级</th>
                            <th>模式</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($channels as $channel)
                        <tr>
                            <td>{{ $channel->name }}</td>
                            <td>{{ $channel->provider }}</td>
                            <td style="font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis;">{{ $channel->base_url }}</td>
                            <td>{{ $channel->model ?: '—' }}</td>
                            <td>{{ $channel->priority }}</td>
                            <td><span class="badge {{ $channel->request_mode === 'stream' ? 'badge-info' : 'badge-default' }}">{{ $channel->request_mode === 'stream' ? '流式' : '同步' }}</span></td>
                            <td>
                                @if($channel->status === 'active')
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-danger">禁用</span>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="{{ route('admin.image-gen.channels.edit', $channel) }}" class="btn btn-ghost btn-sm">编辑</a>
                                <form method="POST" action="{{ route('admin.image-gen.channels.toggle', $channel) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $channel->status === 'active' ? 'btn-ghost' : 'btn-primary' }}">
                                        {{ $channel->status === 'active' ? '禁用' : '启用' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon">�</div>
                                    <div class="empty-state-text">暂无渠道配置</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
