@extends('admin.layouts.app')

@section('title', '生成任务')

@section('header')
    <h1 class="page-title">生成任务</h1>
    <p class="page-subtitle">AI 绘图任务监控</p>
@endsection

@push('styles')
<style>
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(155px,1fr)); gap:14px; margin-bottom:24px; }
    .stat-card { background:var(--panel); border:1px solid var(--line); border-radius:var(--radius-md); padding:18px 20px; transition:box-shadow .2s,transform .2s; }
    .stat-card:hover { box-shadow:var(--shadow-md); transform:translateY(-1px); }
    .stat-label { font-size:12px; color:var(--text-muted); margin-bottom:6px; font-weight:500; letter-spacing:.02em; }
    .stat-value { font-size:24px; font-weight:700; color:var(--text); }
    .stat-sub { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .stat-value.ok { color:var(--success); }
    .stat-value.bad { color:var(--danger); }
    .stat-value.warn { color:var(--warning); }

    .filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:14px 20px; border-bottom:1px solid var(--line); }
    .filter-bar input[type=text],.filter-bar select { padding:7px 12px; border:1px solid var(--line-strong); border-radius:var(--radius-sm); font-size:13px; background:var(--panel); color:var(--text); transition:border-color .15s,box-shadow .15s; outline:none; }
    .filter-bar input[type=text]:focus,.filter-bar select:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
    .range-pills { display:flex; gap:4px; }
    .range-pills a { padding:5px 12px; border-radius:999px; font-size:12px; font-weight:500; color:var(--text-secondary); text-decoration:none; border:1px solid transparent; transition:all .15s; }
    .range-pills a:hover { background:var(--accent-soft); color:var(--accent); }
    .range-pills a.active { background:var(--accent-soft); color:var(--accent); border-color:var(--accent); font-weight:600; }

    .status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:500; }
    .status-badge.completed { background:rgba(34,197,94,.08); color:#16a34a; }
    .status-badge.failed { background:rgba(239,68,68,.08); color:#dc2626; }
    .status-badge.processing { background:rgba(245,158,11,.08); color:#d97706; }
    .status-badge.pending { background:rgba(161,161,170,.08); color:#71717a; }
    .status-dot { display:inline-block; width:7px; height:7px; border-radius:50%; }
    .status-dot.completed { background:#16a34a; }
    .status-dot.failed { background:#dc2626; }
    .status-dot.processing { background:#d97706; animation:blink 1.5s infinite; }
    .status-dot.pending { background:#a1a1aa; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

    .refund-chip { display:inline-flex; align-items:center; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:500; }
    .refund-chip.done { background:rgba(34,197,94,.08); color:#16a34a; }
    .refund-chip.none { background:rgba(239,68,68,.08); color:#dc2626; }

    .err-preview { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:ui-monospace,Menlo,monospace; font-size:11px; color:var(--danger); cursor:help; padding:3px 6px; background:rgba(239,68,68,.04); border-radius:4px; }
    .task-meta { font-size:11px; color:var(--text-muted); margin-top:3px; display:flex; gap:4px; flex-wrap:wrap; }
    .task-meta span { background:var(--accent-soft); color:var(--accent); padding:2px 7px; border-radius:4px; font-weight:500; }

    .auto-refresh { position:relative; margin-left:auto; }
    .auto-refresh .gear-btn { width:32px; height:32px; border:1px solid var(--line-strong); border-radius:var(--radius-sm); background:var(--panel); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:15px; color:var(--text-muted); transition:all .15s; }
    .auto-refresh .gear-btn:hover { border-color:var(--accent); color:var(--accent); }
    .auto-refresh .gear-btn.active { border-color:var(--success); color:var(--success); background:rgba(34,197,94,.05); }
    .auto-refresh .dropdown { position:absolute; right:0; top:38px; background:var(--panel); border:1px solid var(--line); border-radius:var(--radius-md); padding:8px; box-shadow:var(--shadow-lg); z-index:10; min-width:130px; }
    .auto-refresh .dropdown label { display:flex; align-items:center; gap:8px; padding:6px 8px; border-radius:6px; font-size:12px; cursor:pointer; white-space:nowrap; color:var(--text-secondary); transition:background .1s; }
    .auto-refresh .dropdown label:hover { background:var(--accent-soft); color:var(--accent); }

    /* 分页 */
    .pagination-wrap { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-top:1px solid var(--line); }
    .pagination-info { font-size:13px; color:var(--text-muted); }
    .pagination-info strong { color:var(--text); font-weight:600; }
    .pagination { display:flex; gap:4px; flex-wrap:wrap; list-style:none; padding:0; margin:0; }
    .page-item.disabled .page-link { color:var(--text-muted); pointer-events:none; opacity:.5; }
    .page-item.active .page-link { background:var(--accent); border-color:var(--accent); color:#fff; font-weight:600; box-shadow:0 2px 8px rgba(45,91,240,.25); }
    .page-link { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 10px; font-size:13px; font-weight:500; border-radius:var(--radius-sm); text-decoration:none; border:1px solid var(--line-strong); color:var(--text); transition:all .15s; background:var(--panel); }
    .page-link:hover { background:var(--accent-soft); border-color:var(--accent); color:var(--accent); }
    .page-link svg { width:14px; height:14px; }

    .detail-link { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:500; color:var(--accent); text-decoration:none; padding:4px 10px; border-radius:6px; transition:background .15s; }
    .detail-link:hover { background:var(--accent-soft); }
</style>
@endpush

@section('content')
    @php($displayTimezone = config('app.display_timezone'))
    {{-- 统计看板 --}}
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">总任务</div><div class="stat-value">{{ number_format($stats['total']) }}</div></div>
        <div class="stat-card"><div class="stat-label">已完成</div><div class="stat-value ok">{{ number_format($stats['completed']) }}</div><div class="stat-sub">{{ $stats['total'] > 0 ? round($stats['completed']/$stats['total']*100) : 0 }}% 完成</div></div>
        <div class="stat-card"><div class="stat-label">失败</div><div class="stat-value bad">{{ $stats['failed'] }}</div></div>
        <div class="stat-card"><div class="stat-label">进行中</div><div class="stat-value warn">{{ $stats['processing'] }}</div></div>
        <div class="stat-card"><div class="stat-label">成功率</div><div class="stat-value {{ $stats['success_rate'] >= 90 ? 'ok' : ($stats['success_rate'] >= 70 ? 'warn' : 'bad') }}">{{ $stats['success_rate'] }}%</div></div>
        <div class="stat-card"><div class="stat-label">退款统计</div><div class="stat-value" style="font-size:18px;">{{ $stats['refund_count'] }} 笔</div><div class="stat-sub">{{ $stats['refund_credits'] }} 次 / ¥{{ number_format($stats['refund_balance'], 2) }}</div></div>
    </div>

    <div class="card" x-data="{ interval: 10, timer: null }" x-init="
        if(interval > 0) timer = setInterval(() => window.location.reload(), interval * 1000);
        $watch('interval', v => { clearInterval(timer); if(v > 0) timer = setInterval(() => window.location.reload(), v * 1000); })
    ">
        <div class="filter-bar">
            <form method="GET" style="display:contents;">
                <select name="status" onchange="this.form.submit()">
                    <option value="">全部状态</option>
                    <option value="pending" {{ $filters['status']==='pending'?'selected':'' }}>排队中</option>
                    <option value="processing" {{ $filters['status']==='processing'?'selected':'' }}>生成中</option>
                    <option value="completed" {{ $filters['status']==='completed'?'selected':'' }}>已完成</option>
                    <option value="failed" {{ $filters['status']==='failed'?'selected':'' }}>失败</option>
                </select>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="ID / 邮箱 / 昵称" style="width:160px;">
                <input type="hidden" name="range" value="{{ $filters['range'] }}">
                <button type="submit" class="btn btn-sm btn-primary">搜索</button>
            </form>

            <div class="range-pills">
                @foreach(['today'=>'今天','7d'=>'7天','30d'=>'30天','all'=>'全部'] as $k=>$v)
                    <a href="?{{ http_build_query(array_merge($filters, ['range'=>$k])) }}" class="{{ $filters['range']===$k?'active':'' }}">{{ $v }}</a>
                @endforeach
            </div>

            <div class="auto-refresh" x-data="{ open: false }" @click.away="open=false">
                <button class="gear-btn" :class="{ active: interval > 0 }" @click="open=!open" title="自动刷新设置">⟳</button>
                <div class="dropdown" x-show="open" x-cloak>
                    @foreach([0=>'关闭', 5=>'5秒', 10=>'10秒', 30=>'30秒'] as $v=>$label)
                        <label><input type="radio" :checked="interval==={{ $v }}" @click="interval={{ $v }}; open=false" style="accent-color:#10b981;"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>状态</th>
                        <th>用户</th>
                        <th>参数</th>
                        <th>退款</th>
                        <th>错误</th>
                        <th>耗时</th>
                        <th>时间</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $log = $logsByTask[$task->task_id] ?? null;
                            if ($task->completed_at && $task->created_at) {
                                $elapsed = $task->created_at->diffForHumans($task->completed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true);
                            } elseif ($task->status === 'processing') {
                                $elapsed = $task->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) . '...';
                            } else {
                                $elapsed = '—';
                            }
                        @endphp
                        <tr>
                            <td>
                                <span class="status-badge {{ $task->status }}">
                                    <span class="status-dot {{ $task->status }}"></span>
                                    {{ ['pending'=>'排队','processing'=>'生成中','completed'=>'完成','failed'=>'失败'][$task->status] ?? $task->status }}
                                </span>
                            </td>
                            <td style="font-size:13px; font-weight:500;">{{ $task->user?->nickname ?: $task->user?->email ?: '—' }}</td>
                            <td>
                                <div class="task-meta">
                                    <span>{{ $task->model }}</span>
                                    <span>{{ $task->size }}</span>
                                    <span>{{ $task->quality }}</span>
                                    @if($task->count > 1)<span>×{{ $task->count }}</span>@endif
                                </div>
                            </td>
                            <td>
                                @if($log)
                                    @if($log->refunded_at)
                                        <span class="refund-chip done">✓ 已退 +{{ $log->cost_credits }}</span>
                                    @elseif($task->status === 'failed')
                                        <span class="refund-chip none">✗ 未退</span>
                                    @else
                                        <span style="color:var(--text-muted);font-size:11px;">—</span>
                                    @endif
                                @else
                                    <span style="color:var(--text-muted);font-size:11px;">—</span>
                                @endif
                            </td>
                            <td>
                                @if($task->error)
                                    <div class="err-preview" title="{{ $task->error }}">{{ $task->error }}</div>
                                @else
                                    <span style="color:var(--text-muted);font-size:11px;">—</span>
                                @endif
                            </td>
                            <td style="font-size:12px; white-space:nowrap; color:var(--text-secondary);">{{ $elapsed }}</td>
                            <td style="white-space:nowrap; font-size:12px; color:var(--text-muted);">{{ $task->created_at?->timezone($displayTimezone)->format('m-d H:i:s') }}</td>
                            <td><a href="{{ route('admin.image-gen.tasks.show', $task->task_id) }}" class="detail-link">详情 →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">🖼️</div><div class="empty-state-text">暂无生成任务</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tasks->hasPages())
        <div class="pagination-wrap">
            <div class="pagination-info">
                共 <strong>{{ $tasks->total() }}</strong> 条，第 <strong>{{ $tasks->currentPage() }}</strong> / {{ $tasks->lastPage() }} 页
            </div>
            <nav>
                <ul class="pagination">
                    <li class="page-item {{ $tasks->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="{{ $tasks->previousPageUrl() }}" rel="prev">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </a>
                    </li>
                    @php
                        $current = $tasks->currentPage();
                        $last = $tasks->lastPage();
                        $window = 2;
                        $pages = [];
                        for ($i = max(1, $current - $window); $i <= min($last, $current + $window); $i++) {
                            $pages[] = $i;
                        }
                        if ($pages[0] > 1) { array_unshift($pages, 1); if ($pages[1] > 2) array_splice($pages, 1, 0, ['...']); }
                        if (end($pages) < $last) { if (end($pages) < $last - 1) $pages[] = '...'; $pages[] = $last; }
                    @endphp
                    @foreach($pages as $page)
                        @if($page === '...')
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        @else
                            <li class="page-item {{ $page == $current ? 'active' : '' }}">
                                <a class="page-link" href="{{ $tasks->url($page) }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                    <li class="page-item {{ $tasks->hasMorePages() ? '' : 'disabled' }}">
                        <a class="page-link" href="{{ $tasks->nextPageUrl() }}" rel="next">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        @endif
    </div>
@endsection
