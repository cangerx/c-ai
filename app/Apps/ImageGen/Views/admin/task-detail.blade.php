@extends('admin.layouts.app')

@section('title', '任务详情 · ' . substr($task->task_id, 0, 12))

@section('header')
    <h1 class="page-title">
        任务详情
        <code style="font-size:14px; color:#6b7280; font-weight:normal;">{{ $task->task_id }}</code>
    </h1>
    <p class="page-subtitle">
        <a href="{{ route('admin.image-gen.tasks') }}">← 返回任务列表</a>
    </p>
@endsection

@push('styles')
<style>
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    @media (max-width: 960px) { .detail-grid { grid-template-columns: 1fr; } }

    .kv-table { width: 100%; font-size: 13px; }
    .kv-table td { padding: 6px 10px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .kv-table td.k { width: 130px; color: #6b7280; }
    .kv-table td.v { font-family: ui-monospace, Menlo, monospace; word-break: break-all; }

    pre.err-block {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 12px;
        max-height: 360px;
        overflow: auto;
        white-space: pre-wrap;
    }
    pre.json-block {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 12px;
        max-height: 400px;
        overflow: auto;
    }

    .img-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }
    .img-grid img {
        width: 100%;
        aspect-ratio: 1;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .badge-lg { font-size: 13px; padding: 4px 10px; }

    .action-bar form { display: inline-block; margin-right: 8px; }
    .action-bar .btn { margin-right: 6px; }
</style>
@endpush

@section('content')
    @if (session('success')) <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div> @endif
    @if (session('error'))   <div class="alert alert-danger"  style="margin-bottom: 16px;">{{ session('error') }}</div>   @endif

    {{-- 状态横幅 --}}
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-body" style="display:flex; align-items:center; gap:16px; padding: 16px 20px;">
            <div>
                @switch($task->status)
                    @case('completed') <span class="badge badge-success badge-lg">✓ 完成</span> @break
                    @case('failed')    <span class="badge badge-danger badge-lg">✗ 失败</span> @break
                    @case('processing')<span class="badge badge-warning badge-lg">⟳ 生成中</span> @break
                    @default           <span class="badge badge-secondary badge-lg">{{ $task->status }}</span>
                @endswitch
            </div>
            <div style="flex: 1;">
                <div style="font-size: 14px; color: #374151;">{{ $task->message ?: '—' }}</div>
                <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                    创建 {{ $task->created_at }} ·
                    @if($task->completed_at) 完成 {{ $task->completed_at }} · 耗时 {{ $task->created_at?->diffForHumans($task->completed_at, true) }} @else 未完成 @endif
                    · 重试 {{ $task->attempts ?? 0 }} 次
                </div>
            </div>
            <div class="action-bar">
                @if(in_array($task->status, ['failed', 'completed']))
                    <form method="POST" action="{{ route('admin.image-gen.tasks.retry', $task->task_id) }}"
                          x-data @submit.prevent="if(!$el.querySelector('input[name=api_key]').value.trim()){alert('请输入 API Key');return;} $dispatch('confirm', { title: '确认重跑', message: '确定使用该 API Key 重新执行此任务？', form: $el })">
                        @csrf
                        <input type="password" name="api_key" placeholder="API Key（用于重跑）" style="padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:12px; width: 200px;">
                        <button type="submit" class="btn btn-primary btn-sm" data-no-loading>重跑</button>
                    </form>
                @endif

                @if($usageLog && !$usageLog->refunded_at)
                    <form method="POST" action="{{ route('admin.image-gen.tasks.refund', $task->task_id) }}"
                          x-data @submit.prevent="$dispatch('confirm', { title: '确认退款', message: '确认退款 credits+{{ $usageLog->cost_credits }}, balance+¥{{ number_format($usageLog->cost_balance, 2) }}？', form: $el })">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm" data-no-loading>手动退款</button>
                    </form>
                @endif

                @if(in_array($task->status, ['pending', 'processing']))
                    <form method="POST" action="{{ route('admin.image-gen.tasks.force-fail', $task->task_id) }}"
                          x-data @submit.prevent="$dispatch('confirm', { title: '强制失败', message: '强制标记为失败？不会自动退款，请另外操作。', form: $el })">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm" data-no-loading>强制失败</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="detail-grid">
        {{-- 左栏：主要信息 --}}
        <div>
            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header"><span class="card-title">基础信息</span></div>
                <div class="card-body" style="padding: 0;">
                    <table class="kv-table">
                        <tr><td class="k">任务 ID</td><td class="v">{{ $task->task_id }}</td></tr>
                        <tr><td class="k">用户</td><td class="v">
                            @if($task->user)
                                {{ $task->user->nickname ?: $task->user->name ?: $task->user->email }}
                                <span style="color:#9ca3af;">#{{ $task->user->id }} · {{ $task->user->email }}</span>
                            @else — @endif
                        </td></tr>
                        <tr><td class="k">模式</td><td class="v">{{ $task->mode }}</td></tr>
                        <tr><td class="k">模型</td><td class="v">{{ $task->model }}</td></tr>
                        <tr><td class="k">画质</td><td class="v">{{ $task->quality }}</td></tr>
                        <tr><td class="k">尺寸</td><td class="v">{{ $task->size }}</td></tr>
                        <tr><td class="k">数量</td><td class="v">{{ $task->count }}（请求）/ {{ is_array($task->items) ? count($task->items) : 0 }}（实得）</td></tr>
                        <tr><td class="k">是否公开</td><td class="v">{{ $task->is_public ? '是' : '否' }}</td></tr>
                        <tr><td class="k">重试次数</td><td class="v">{{ $task->attempts ?? 0 }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header"><span class="card-title">提示词</span></div>
                <div class="card-body">
                    <pre class="json-block" style="max-height: 200px;">{{ $task->prompt }}</pre>
                </div>
            </div>

            @if($task->error || $task->status === 'failed')
            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header"><span class="card-title" style="color:#dc2626;">错误详情（仅管理员可见）</span></div>
                <div class="card-body">
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">前端展示给用户的消息：</div>
                    <div style="padding: 8px 12px; background: #f9fafb; border-radius: 6px; font-size: 13px; margin-bottom: 12px;">
                        {{ $task->message ?: '—' }}
                    </div>
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 8px;">原始错误（技术）：</div>
                    <pre class="err-block">{{ $task->error ?: '(无)' }}</pre>
                </div>
            </div>
            @endif

            @if(is_array($task->items) && count($task->items) > 0)
            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header"><span class="card-title">生成结果（{{ count($task->items) }} 张）</span></div>
                <div class="card-body">
                    <div class="img-grid">
                        @foreach($task->items as $item)
                            @php $url = is_array($item) ? ($item['url'] ?? $item['path'] ?? null) : $item; @endphp
                            @if($url)
                                <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt=""></a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header"><span class="card-title">items (raw)</span></div>
                <div class="card-body">
                    <pre class="json-block">{{ json_encode($task->items, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        </div>

        {{-- 右栏：计费 & 时间线 --}}
        <div>
            <div class="card" style="margin-bottom: 16px;">
                <div class="card-header"><span class="card-title">计费 & 退款</span></div>
                <div class="card-body" style="padding: 0;">
                    @if($usageLog)
                        <table class="kv-table">
                            <tr><td class="k">渠道</td><td class="v">#{{ $usageLog->channel_id }}</td></tr>
                            <tr><td class="k">扣次数</td><td class="v">{{ $usageLog->cost_credits }}</td></tr>
                            <tr><td class="k">扣额度</td><td class="v">¥{{ number_format($usageLog->cost_balance, 2) }}</td></tr>
                            <tr><td class="k">计费时间</td><td class="v">{{ $usageLog->created_at }}</td></tr>
                            <tr><td class="k">退款状态</td><td class="v">
                                @if($usageLog->refunded_at)
                                    <span class="badge badge-success">已退款</span> {{ $usageLog->refunded_at }}
                                @else
                                    <span class="badge badge-secondary">未退款</span>
                                @endif
                            </td></tr>
                        </table>
                    @else
                        <div style="padding: 16px; color: #9ca3af;">无计费记录</div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title">时间线</span></div>
                <div class="card-body">
                    <table class="kv-table">
                        <tr><td class="k">创建</td><td class="v">{{ $task->created_at }}</td></tr>
                        <tr><td class="k">最后更新</td><td class="v">{{ $task->updated_at }}</td></tr>
                        <tr><td class="k">完成</td><td class="v">{{ $task->completed_at ?: '—' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
