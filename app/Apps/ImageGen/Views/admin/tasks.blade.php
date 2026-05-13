@extends('admin.layouts.app')

@section('title', '生成任务')

@section('header')
    <h1 class="page-title">生成任务</h1>
    <p class="page-subtitle">AI 绘图任务监控 · 完整错误详情仅管理员可见</p>
@endsection

@push('styles')
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 10px;
        padding: 16px 18px;
    }
    .stat-label { font-size: 12px; color: #6b7280; margin-bottom: 6px; }
    .stat-value { font-size: 24px; font-weight: 600; }
    .stat-sub   { font-size: 11px; color: #9ca3af; margin-top: 4px; }
    .stat-value.ok  { color: #10b981; }
    .stat-value.bad { color: #ef4444; }
    .stat-value.warn{ color: #f59e0b; }

    .filter-row {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color, #e5e7eb);
    }
    .filter-row input[type=text], .filter-row select {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
    }
    .filter-row .range-pills a {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        color: #6b7280;
        text-decoration: none;
        border: 1px solid transparent;
    }
    .filter-row .range-pills a.active {
        background: #eef2ff;
        color: #3730a3;
        border-color: #c7d2fe;
    }

    .refund-chip {
        display: inline-block;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 11px;
        margin-left: 4px;
    }
    .refund-chip.done { background: #ecfdf5; color: #047857; }
    .refund-chip.none { background: #fef2f2; color: #b91c1c; }

    .err-preview {
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-family: ui-monospace, Menlo, monospace;
        font-size: 11px;
        color: #dc2626;
    }
    .attempts-pill {
        display: inline-block;
        min-width: 18px;
        text-align: center;
        background: #f3f4f6;
        border-radius: 4px;
        padding: 1px 6px;
        font-size: 11px;
    }
    .attempts-pill.many { background: #fef3c7; color: #92400e; }
</style>
@endpush

@section('content')
    {{-- ── 看板 ── --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">任务总数</div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-sub">
                @switch($filters['range'])
                    @case('today') 今日 @break
                    @case('30d') 近 30 天 @break
                    @case('all') 全部 @break
                    @default 近 7 天
                @endswitch
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">成功</div>
            <div class="stat-value ok">{{ $stats['completed'] }}</div>
            <div class="stat-sub">成功率 {{ $stats['success_rate'] }}%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">失败</div>
            <div class="stat-value bad">{{ $stats['failed'] }}</div>
            <div class="stat-sub">含自动超时</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">进行中</div>
            <div class="stat-value warn">{{ $stats['processing'] }}</div>
            <div class="stat-sub">pending + processing</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">退款笔数</div>
            <div class="stat-value">{{ $stats['refund_count'] }}</div>
            <div class="stat-sub">credits +{{ $stats['refund_credits'] }} · ¥{{ number_format($stats['refund_balance'], 2) }}</div>
        </div>
    </div>

    <div class="card">
        {{-- ── 筛选栏 ── --}}
        <form class="filter-row" method="GET" action="{{ route('admin.image-gen.tasks') }}">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="搜索 task_id / 邮箱 / 昵称" style="width: 260px;">
            <select name="status">
                <option value="">全部状态</option>
                @foreach(['pending'=>'排队中','processing'=>'生成中','completed'=>'完成','failed'=>'失败'] as $s=>$label)
                    <option value="{{ $s }}" @selected($filters['status']===$s)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="hidden" name="range" value="{{ $filters['range'] }}">
            <button type="submit" class="btn btn-primary btn-sm">筛选</button>

            <div class="range-pills" style="margin-left:auto; display:flex; gap:4px;">
                @foreach(['today'=>'今日','7d'=>'7天','30d'=>'30天','all'=>'全部'] as $r=>$lbl)
                    <a href="{{ route('admin.image-gen.tasks', array_merge($filters, ['range'=>$r])) }}"
                       class="{{ $filters['range']===$r ? 'active' : '' }}">{{ $lbl }}</a>
                @endforeach
            </div>
        </form>

        @if (session('success')) <div class="alert alert-success" style="margin:12px 16px;">{{ session('success') }}</div> @endif
        @if (session('error'))   <div class="alert alert-danger"  style="margin:12px 16px;">{{ session('error') }}</div>   @endif

        {{-- ── 列表 ── --}}
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>任务</th>
                            <th>用户</th>
                            <th>状态</th>
                            <th>模型</th>
                            <th>画质×数量</th>
                            <th>重试</th>
                            <th>退款</th>
                            <th>错误摘要</th>
                            <th>耗时</th>
                            <th>创建</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            @php
                                $log = $logsByTask[$task->task_id] ?? null;
                                $elapsed = $task->completed_at
                                    ? $task->created_at->diffInSeconds($task->completed_at) . 's'
                                    : ($task->status === 'processing' ? $task->created_at->diffForHumans(now(), true) : '—');
                            @endphp
                            <tr>
                                <td><code style="font-size:11px;">{{ substr($task->task_id, 0, 12) }}…</code></td>
                                <td>
                                    @if($task->user)
                                        <div style="font-size:12px;">{{ $task->user->nickname ?: $task->user->name ?: '—' }}</div>
                                        <div style="font-size:11px; color:#9ca3af;">{{ $task->user->email }}</div>
                                    @else — @endif
                                </td>
                                <td>
                                    @switch($task->status)
                                        @case('completed') <span class="badge badge-success">完成</span> @break
                                        @case('failed')    <span class="badge badge-danger">失败</span>  @break
                                        @case('processing')<span class="badge badge-warning">生成中</span>@break
                                        @case('pending')   <span class="badge badge-secondary">排队</span>@break
                                        @default <span class="badge">{{ $task->status }}</span>
                                    @endswitch
                                </td>
                                <td style="font-size:12px;">{{ $task->model }}</td>
                                <td style="font-size:12px;">{{ $task->quality }} × {{ $task->count }}</td>
                                <td>
                                    <span class="attempts-pill {{ ($task->attempts ?? 0) > 1 ? 'many' : '' }}">
                                        {{ $task->attempts ?? 0 }}
                                    </span>
                                </td>
                                <td>
                                    @if($log)
                                        @if($log->refunded_at)
                                            <span class="refund-chip done" title="{{ $log->refunded_at }}">已退 +{{ $log->cost_credits }}</span>
                                        @elseif($task->status === 'failed')
                                            <span class="refund-chip none">未退款!</span>
                                        @else
                                            <span style="color:#9ca3af; font-size:11px;">—</span>
                                        @endif
                                    @else
                                        <span style="color:#9ca3af; font-size:11px;">无计费</span>
                                    @endif
                                </td>
                                <td>
                                    @if($task->error)
                                        <div class="err-preview" title="{{ $task->error }}">{{ $task->error }}</div>
                                    @else
                                        <span style="color:#9ca3af; font-size:11px;">—</span>
                                    @endif
                                </td>
                                <td style="font-size:12px; white-space:nowrap;">{{ $elapsed }}</td>
                                <td style="white-space:nowrap; font-size:11px; color:#6b7280;">{{ $task->created_at?->format('m-d H:i:s') }}</td>
                                <td style="white-space:nowrap;">
                                    <a href="{{ route('admin.image-gen.tasks.show', $task->task_id) }}" class="btn btn-sm btn-link">详情</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11"><div class="empty-state"><div class="empty-state-icon">🖼️</div><div class="empty-state-text">暂无任务</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="padding: 12px 16px;">
                {{ $tasks->links() }}
            </div>
        </div>
    </div>
@endsection
