@extends('admin.layouts.app')

@section('title', 'AI 渠道管理')

@section('header')
    <h1 class="page-title">AI 渠道管理</h1>
    <p class="page-subtitle">管理图像生成 API 渠道</p>
@endsection

@push('styles')
<style>
    .ch-stats { display:inline-flex; gap:8px; font-size:11px; align-items:center; }
    .ch-stats .pill { padding:2px 7px; border-radius:4px; }
    .ch-stats .pill-ok { background:#ecfdf5; color:#047857; }
    .ch-stats .pill-fail { background:#fef2f2; color:#b91c1c; }
    .ch-stats .pill-total { background:#f3f4f6; color:#374151; }
    .cooldown-badge { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:4px; font-size:11px; background:#fef3c7; color:#92400e; }
    .cooldown-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:#f59e0b; animation:blink 1.5s infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .test-btn { padding:3px 8px; border:1px solid #d1d5db; border-radius:5px; font-size:11px; cursor:pointer; background:var(--card-bg,#fff); color:inherit; transition:all .15s; }
    .test-btn:hover { border-color:#6366f1; color:#6366f1; }
    .test-btn.loading { opacity:.6; pointer-events:none; }
    .test-result { font-size:11px; margin-top:3px; }
    .test-result.ok { color:#059669; }
    .test-result.error { color:#dc2626; }
    .auto-refresh { position:relative; }
    .auto-refresh .gear-btn { width:28px; height:28px; border:1px solid #d1d5db; border-radius:6px; background:var(--card-bg,#fff); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; color:#6b7280; transition:all .15s; }
    .auto-refresh .gear-btn:hover { border-color:#6366f1; color:#6366f1; }
    .auto-refresh .gear-btn.active { border-color:#10b981; color:#10b981; }
    .auto-refresh .dropdown { position:absolute; right:0; top:34px; background:var(--card-bg,#fff); border:1px solid var(--border-color,#e5e7eb); border-radius:8px; padding:8px; box-shadow:0 4px 12px rgba(0,0,0,.1); z-index:10; min-width:120px; }
    .auto-refresh .dropdown label { display:flex; align-items:center; gap:6px; padding:4px 6px; border-radius:4px; font-size:12px; cursor:pointer; white-space:nowrap; }
    .auto-refresh .dropdown label:hover { background:#f3f4f6; }
    .ch-rate { font-size:11px; color:#6b7280; }
</style>
@endpush

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;"
         x-data="{ interval: 30, timer: null }" x-init="
            if(interval > 0) timer = setInterval(() => window.location.reload(), interval * 1000);
            $watch('interval', v => { clearInterval(timer); if(v > 0) timer = setInterval(() => window.location.reload(), v * 1000); })
         ">
        <a href="{{ route('admin.image-gen.channels.create') }}" class="btn btn-primary btn-sm">添加渠道</a>
        <div class="auto-refresh" x-data="{ open: false }" @click.away="open=false">
            <button class="gear-btn" :class="{ active: interval > 0 }" @click="open=!open" title="自动刷新设置">⟳</button>
            <div class="dropdown" x-show="open" x-cloak>
                @foreach([0=>'关闭', 15=>'15秒', 30=>'30秒', 60=>'60秒'] as $v=>$label)
                    <label><input type="radio" :checked="interval==={{ $v }}" @click="interval={{ $v }}; open=false" style="accent-color:#10b981;"> {{ $label }}</label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>渠道</th>
                        <th>24h 可用性</th>
                        <th>优先级</th>
                        <th>状态</th>
                        <th>连通性</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($channels as $channel)
                        @php
                            $st = $channelStats[$channel->id] ?? ['total'=>0,'ok'=>0,'fail'=>0,'cooldown'=>false];
                            $rate = $st['total'] > 0 ? round($st['ok'] / $st['total'] * 100) : null;
                        @endphp
                        <tr x-data="{ result: '', cls: '' }">
                            <td>
                                <div style="font-weight:500;">{{ $channel->display_name ?: $channel->name }}</div>
                                <div style="font-size:11px; color:#6b7280;">{{ $channel->provider }} · {{ $channel->model ?: 'default' }} · {{ ['sync'=>'同步','async'=>'异步轮询dm','stream'=>'流式'][$channel->request_mode] ?? '同步' }}</div>
                                <div style="font-size:11px; color:#9ca3af; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $channel->base_url }}</div>
                            </td>
                            <td>
                                @if($st['total'] > 0)
                                    <div class="ch-stats">
                                        <span class="pill pill-total">{{ $st['total'] }}次</span>
                                        <span class="pill pill-ok">✓{{ $st['ok'] }}</span>
                                        @if($st['fail'] > 0)<span class="pill pill-fail">✗{{ $st['fail'] }}</span>@endif
                                    </div>
                                    <div class="ch-rate" style="margin-top:2px;">
                                        成功率 <strong style="color:{{ $rate >= 90 ? '#059669' : ($rate >= 70 ? '#d97706' : '#dc2626') }}">{{ $rate }}%</strong>
                                    </div>
                                @else
                                    <span style="font-size:11px; color:#9ca3af;">暂无数据</span>
                                @endif
                                @if($st['cooldown'])
                                    <div style="margin-top:3px;"><span class="cooldown-badge">冷却中</span></div>
                                @endif
                            </td>
                            <td>{{ $channel->priority }}</td>
                            <td>
                                @if($channel->status === 'active')
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-danger">禁用</span>
                                @endif
                            </td>
                            <td>
                                <button class="test-btn" :class="{ loading: result === '...' }"
                                    @click="result='...'; cls='';
                                        fetch('{{ route('admin.image-gen.channels.test', $channel) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
                                        .then(r=>r.json()).then(d=>{ result=d.msg; cls=d.status; })
                                        .catch(()=>{ result='请求失败'; cls='error'; })">
                                    测试
                                </button>
                                <div class="test-result" :class="cls" x-show="result" x-text="result"></div>
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('admin.image-gen.channels.edit', $channel) }}" class="btn btn-ghost btn-sm">编辑</a>
                                <form method="POST" action="{{ route('admin.image-gen.channels.toggle', $channel) }}" style="display:inline;"
                                      x-data @submit.prevent="$dispatch('confirm', { title: '{{ $channel->status === 'active' ? '禁用渠道' : '启用渠道' }}', message: '确定{{ $channel->status === 'active' ? '禁用' : '启用' }}渠道「{{ addslashes($channel->name) }}」？', form: $el })">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $channel->status === 'active' ? 'btn-ghost' : 'btn-primary' }}" data-no-loading>
                                        {{ $channel->status === 'active' ? '禁用' : '启用' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.image-gen.channels.destroy', $channel) }}" style="display:inline;"
                                      x-data @submit.prevent="$dispatch('confirm', { title: '确认删除', message: '确定要删除渠道「{{ addslashes($channel->name) }}」吗？此操作不可恢复。', form: $el })">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger,#ef4444);" data-no-loading>删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">📡</div><div class="empty-state-text">暂无渠道配置</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
