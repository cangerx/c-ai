@extends('admin.layouts.app')

@section('title', '任务详情 · ' . substr($task->task_id, 0, 12))

@section('header')
    <h1 class="page-title">任务详情</h1>
    <p class="page-subtitle">
        <a href="{{ route('admin.image-gen.tasks') }}">← 返回任务列表</a>
    </p>
@endsection

@push('styles')
<style>
    .td-status-bar { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 16px 20px; }
    .td-status-bar .status-msg { font-size: 14px; color: #374151; line-height: 1.4; }
    .td-status-bar .actions { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .td-status-bar .actions form { display: inline-flex; align-items: center; gap: 6px; }

    .td-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; }
    @media (max-width: 768px) { .td-meta { grid-template-columns: repeat(2, 1fr); } }
    .td-meta-item { padding: 14px 18px; border-bottom: 1px solid #f3f4f6; }
    .td-meta-item:not(:nth-child(3n)) { border-right: 1px solid #f3f4f6; }
    @media (max-width: 768px) { .td-meta-item:not(:nth-child(3n)) { border-right: none; } .td-meta-item:nth-child(odd) { border-right: 1px solid #f3f4f6; } }
    .td-meta-label { font-size: 11px; color: #9ca3af; letter-spacing: 0.5px; margin-bottom: 4px; }
    .td-meta-value { font-size: 14px; color: #111827; font-weight: 500; word-break: break-all; line-height: 1.4; }

    .td-prompt { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 14px; line-height: 1.7; white-space: pre-wrap; max-height: 200px; overflow: auto; color: #1f2937; }

    .td-img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .td-img-grid img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; cursor: pointer; transition: transform .15s; }
    .td-img-grid img:hover { transform: scale(1.03); }

    .td-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
    @media (max-width: 768px) { .td-two-col { grid-template-columns: 1fr; } }
    .td-two-col > div:first-child { border-right: 1px solid #f3f4f6; }
    @media (max-width: 768px) { .td-two-col > div:first-child { border-right: none; border-bottom: 1px solid #f3f4f6; } }
    .td-two-col .col-inner { padding: 18px 20px; }
    .td-two-col .col-title { font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .td-two-col .col-row { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f9fafb; }
    .td-two-col .col-row:last-child { border-bottom: none; }
    .td-two-col .col-row .label { color: #6b7280; }
    .td-two-col .col-row .value { color: #111827; font-weight: 500; }

    .td-error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; font-size: 12px; color: #991b1b; white-space: pre-wrap; max-height: 300px; overflow: auto; }

    details.td-raw summary { cursor: pointer; font-size: 13px; color: #6b7280; padding: 12px 16px; user-select: none; }
    details.td-raw summary:hover { color: #374151; }
    details.td-raw pre { margin: 0; padding: 12px 16px; font-size: 12px; background: #f9fafb; border-top: 1px solid #f3f4f6; max-height: 400px; overflow: auto; white-space: pre-wrap; }

    .badge-lg { font-size: 13px; padding: 4px 10px; }
    .td-taskid { font-size: 12px; color: #9ca3af; font-family: ui-monospace, monospace; }
</style>
@endpush

@section('content')
    @php($displayTimezone = config('app.display_timezone'))
    @if (session('success')) <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('success') }}</div> @endif
    @if (session('error'))   <div class="alert alert-danger"  style="margin-bottom: 16px;">{{ session('error') }}</div>   @endif

    {{-- 1. 状态栏 --}}
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-body td-status-bar">
            <div>
                @switch($task->status)
                    @case('completed') <span class="badge badge-success badge-lg">✓ 完成</span> @break
                    @case('failed')    <span class="badge badge-danger badge-lg">✗ 失败</span> @break
                    @case('processing')<span class="badge badge-warning badge-lg">⟳ 生成中</span> @break
                    @default           <span class="badge badge-secondary badge-lg">{{ $task->status }}</span>
                @endswitch
            </div>
            <div class="status-msg">{{ $task->message ?: '—' }}</div>
            <span class="td-taskid">{{ $task->task_id }}</span>

            <div class="actions">
                @if(in_array($task->status, ['failed', 'completed']))
                    <form method="POST" action="{{ route('admin.image-gen.tasks.retry', $task->task_id) }}"
                          x-data @submit.prevent="if(!$el.querySelector('input[name=api_key]').value.trim()){alert('请输入 API Key');return;} $dispatch('confirm', { title: '确认重跑', message: '确定使用该 API Key 重新执行此任务？', form: $el })">
                        @csrf
                        <input type="password" name="api_key" placeholder="API Key" style="padding:5px 8px; border:1px solid #d1d5db; border-radius:6px; font-size:12px; width:160px;">
                        <button type="submit" class="btn btn-primary btn-sm" data-no-loading>重跑</button>
                    </form>
                @endif
                @if($usageLog && !$usageLog->refunded_at)
                    <form method="POST" action="{{ route('admin.image-gen.tasks.refund', $task->task_id) }}"
                          x-data @submit.prevent="$dispatch('confirm', { title: '确认退款', message: '退款 credits+{{ $usageLog->cost_credits }}, balance+¥{{ number_format($usageLog->cost_balance, 2) }}？', form: $el })">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm" data-no-loading>退款</button>
                    </form>
                @endif
                @if(in_array($task->status, ['pending', 'processing']))
                    <form method="POST" action="{{ route('admin.image-gen.tasks.force-fail', $task->task_id) }}"
                          x-data @submit.prevent="$dispatch('confirm', { title: '强制失败', message: '强制标记为失败？不会自动退款。', form: $el })">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm" data-no-loading>强制失败</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- 2. 核心信息 --}}
    <div class="card" style="margin-bottom: 16px;">
        <div class="td-meta">
            <div class="td-meta-item">
                <div class="td-meta-label">用户</div>
                <div class="td-meta-value">
                    @if($task->user) {{ $task->user->nickname ?: $task->user->name ?: $task->user->email }} <span style="color:#9ca3af;">#{{ $task->user->id }}</span> @else — @endif
                </div>
            </div>
            <div class="td-meta-item">
                <div class="td-meta-label">模式</div>
                <div class="td-meta-value">{{ $task->mode }}</div>
            </div>
            <div class="td-meta-item">
                <div class="td-meta-label">模型</div>
                <div class="td-meta-value">{{ \App\Models\AiModel::where('model_id', $task->model)->value('display_name') ?: $task->model }}</div>
            </div>
            <div class="td-meta-item">
                <div class="td-meta-label">画质</div>
                <div class="td-meta-value">{{ ['low' => '标清 1K', 'medium' => '高清 2K', 'high' => '超清 4K'][$task->quality] ?? $task->quality }}</div>
            </div>
            <div class="td-meta-item">
                <div class="td-meta-label">尺寸</div>
                <div class="td-meta-value">{{ $task->size }}</div>
            </div>
            <div class="td-meta-item">
                <div class="td-meta-label">数量</div>
                <div class="td-meta-value">{{ $task->count }} 请求 / {{ is_array($task->items) ? count(array_filter($task->items)) : 0 }} 实得</div>
            </div>
        </div>
    </div>

    {{-- 3. 提示词 --}}
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header"><span class="card-title">提示词</span></div>
        <div class="card-body">
            <div class="td-prompt">{{ $task->prompt }}</div>
        </div>
    </div>

    {{-- 4. 图片区域 --}}
    @php
        $hasFiles = is_array($task->files) && count($task->files) > 0;
        $hasItems = is_array($task->items) && count(array_filter($task->items)) > 0;
    @endphp
    @if($hasFiles || $hasItems)
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-body">
            @if($hasFiles)
                <div style="margin-bottom: {{ $hasItems ? '16px' : '0' }};">
                    <div style="font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 8px;">参考图（{{ count($task->files) }} 张）</div>
                    <div class="td-img-grid">
                        @foreach($task->files as $file)
                            @if(!empty($file['url']))
                                <a href="{{ $file['url'] }}" target="_blank"><img src="{{ $file['url'] }}" alt="{{ $file['name'] ?? '' }}"></a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            @if($hasItems)
                <div>
                    <div style="font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 8px;">生成结果（{{ count(array_filter($task->items)) }} 张）</div>
                    <div class="td-img-grid">
                        @foreach($task->items as $i => $item)
                            @php
                                $url = is_array($item) ? ($item['url'] ?? $item['path'] ?? null) : $item;
                                $originUrl = is_array($item) ? ($item['origin_url'] ?? null) : null;
                            @endphp
                            @if($url)
                                <div>
                                    <a href="{{ $url }}" target="_blank"><img src="{{ $url }}" alt=""></a>
                                    <div style="margin-top:6px; display:flex; gap:6px; flex-wrap:wrap;">
                                        <a href="/api/download?url={{ urlencode($url) }}" style="font-size:11px; color:#6366f1;">⬇ 下载</a>
                                        @if($originUrl)
                                            <a href="/api/download?url={{ urlencode($originUrl) }}" style="font-size:11px; color:#10b981;">⚡ 加速下载</a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- 5. 计费 + 时间线 --}}
    <div class="card" style="margin-bottom: 16px;">
        <div class="td-two-col">
            <div>
                <div class="col-inner">
                    <div class="col-title">计费信息</div>
                    @if($usageLog)
                        <div class="col-row"><span class="label">渠道</span><span class="value">{{ $usageLog->channel?->display_name ?: $usageLog->channel?->name ?: '#'.$usageLog->channel_id }}</span></div>
                        <div class="col-row"><span class="label">扣次数</span><span class="value">{{ $usageLog->cost_credits }}</span></div>
                        <div class="col-row"><span class="label">扣额度</span><span class="value">¥{{ number_format($usageLog->cost_balance, 2) }}</span></div>
                        <div class="col-row"><span class="label">退款</span><span class="value">
                            @if($usageLog->refunded_at) <span class="badge badge-success">已退款</span> {{ $usageLog->refunded_at->timezone($displayTimezone)->format('m-d H:i') }}
                            @else <span class="badge badge-secondary">未退款</span> @endif
                        </span></div>
                    @else
                        <div style="color: #9ca3af; font-size: 13px;">无计费记录</div>
                    @endif
                </div>
            </div>
            <div>
                <div class="col-inner">
                    <div class="col-title">时间线</div>
                    <div class="col-row"><span class="label">创建</span><span class="value">{{ $task->created_at->timezone($displayTimezone)->format('m-d H:i:s') }}</span></div>
                    <div class="col-row"><span class="label">更新</span><span class="value">{{ $task->updated_at->timezone($displayTimezone)->format('m-d H:i:s') }}</span></div>
                    <div class="col-row"><span class="label">完成</span><span class="value">{{ $task->completed_at ? $task->completed_at->timezone($displayTimezone)->format('m-d H:i:s') : '—' }}</span></div>
                    <div class="col-row"><span class="label">重试</span><span class="value">{{ $task->attempts ?? 0 }} 次</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- 6. 错误详情 --}}
    @if($task->error || $task->status === 'failed')
    <div class="card" style="margin-bottom: 16px;">
        <div class="card-header"><span class="card-title" style="color:#dc2626;">错误详情</span></div>
        <div class="card-body">
            <div class="td-error">{{ $task->error ?: '(无)' }}</div>
        </div>
    </div>
    @endif

    {{-- 7. Raw JSON --}}
    <div class="card">
        <details class="td-raw">
            <summary>▶ 查看原始数据 (items JSON)</summary>
            <pre>{{ json_encode($task->items, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
        </details>
    </div>
@endsection
