@extends('admin.layouts.app')

@section('title', '分站管理')

@section('header')
    <h1 class="page-title">分站管理</h1>
    <p class="page-subtitle">管理所有代理商的独立分站</p>
@endsection

@section('content')
    {{-- 概览 --}}
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-label">代理商总数</div>
            <div class="stat-value">{{ $totalAgents }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">分站总数</div>
            <div class="stat-value">{{ $totalSites }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">运营中</div>
            <div class="stat-value" style="color:var(--success)">{{ $activeSites }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">待审核</div>
            <div class="stat-value" style="color:var(--warning)">{{ $pendingSites }}</div>
        </div>
    </div>

    {{-- 筛选 + 搜索 --}}
    <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; align-items:center;">
        <div style="display:flex; gap:6px; flex:1;">
            @php $st = request('status'); @endphp
            <a href="{{ route('admin.agent-sites.index') }}" class="btn btn-sm {{ !$st ? 'btn-primary' : 'btn-ghost' }}">全部</a>
            <a href="{{ route('admin.agent-sites.index', ['status'=>'pending']) }}" class="btn btn-sm {{ $st==='pending' ? 'btn-primary' : 'btn-ghost' }}">待审核 @if($pendingSites)<span class="badge badge-warning" style="margin-left:4px;">{{ $pendingSites }}</span>@endif</a>
            <a href="{{ route('admin.agent-sites.index', ['status'=>'approved']) }}" class="btn btn-sm {{ $st==='approved' ? 'btn-primary' : 'btn-ghost' }}">已通过</a>
            <a href="{{ route('admin.agent-sites.index', ['status'=>'rejected']) }}" class="btn btn-sm {{ $st==='rejected' ? 'btn-primary' : 'btn-ghost' }}">已拒绝</a>
            <a href="{{ route('admin.agent-sites.index', ['status'=>'disabled']) }}" class="btn btn-sm {{ $st==='disabled' ? 'btn-primary' : 'btn-ghost' }}">已禁用</a>
        </div>
        <form style="display:flex; gap:8px;" method="GET">
            @if($st)<input type="hidden" name="status" value="{{ $st }}">@endif
            <input class="form-input" type="text" name="q" value="{{ request('q') }}" placeholder="搜索站点名/域名/代理商..." style="max-width:240px;">
            <button type="submit" class="btn btn-ghost btn-sm">搜索</button>
        </form>
        <a href="{{ route('admin.agent-sites.levels') }}" class="btn btn-ghost btn-sm">代理等级</a>
    </div>

    {{-- 表格 --}}
    <div class="card">
        <div class="card-body" style="padding:0;">
            <form id="batchForm" method="POST" action="{{ route('admin.agent-sites.batch') }}">
                @csrf
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="checkAll"></th>
                                <th>站点</th>
                                <th>代理商</th>
                                <th>用户数</th>
                                <th>域名</th>
                                <th>等级</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sites as $site)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $site->id }}" class="batch-check"></td>
                                <td>
                                    <a href="{{ route('admin.agent-sites.show', $site) }}" style="font-weight:600; color:var(--text); text-decoration:none;">
                                        <span style="display:inline-block; width:8px; height:8px; border-radius:2px; background:{{ $site->theme_color }}; margin-right:6px;"></span>{{ $site->site_name }}
                                    </a>
                                    <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">/s/{{ $site->slug }}</div>
                                </td>
                                <td>
                                    <div style="font-weight:500;">{{ $site->agent->name ?? '-' }}</div>
                                    <div style="font-size:12px; color:var(--text-muted);">{{ $site->agent->email ?? '' }}</div>
                                </td>
                                <td>{{ $childCounts[$site->user_id] ?? 0 }}</td>
                                <td>
                                    @if($site->custom_domain)
                                        <span style="font-size:12px; font-family:monospace;">{{ $site->custom_domain }}</span>
                                    @elseif($site->subdomain)
                                        <span style="font-size:12px; font-family:monospace;">{{ $site->subdomain }}.*</span>
                                    @else
                                        <span style="color:var(--text-muted)">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php $level = $site->agent && $site->agent->agent_level_id ? $agentLevels->firstWhere('id', $site->agent->agent_level_id) : null; @endphp
                                    @if($level)
                                        <span class="badge badge-info">{{ $level->name }}</span>
                                    @else
                                        <span style="color:var(--text-muted)">默认</span>
                                    @endif
                                </td>
                                <td>
                                    @if($site->status === 'pending')
                                        <span class="badge badge-warning">待审核</span>
                                    @elseif($site->status === 'rejected')
                                        <span class="badge badge-danger">已拒绝</span>
                                    @elseif(!$site->is_active)
                                        <span class="badge badge-danger">已禁用</span>
                                    @else
                                        <span class="badge badge-success">正常</span>
                                    @endif
                                </td>
                                <td style="font-size:13px; color:var(--text-secondary);">{{ $site->created_at->format('Y-m-d') }}</td>
                                <td style="white-space:nowrap;">
                                    @if($site->status === 'pending')
                                        <form method="POST" action="{{ route('admin.agent-sites.approve', $site) }}" style="display:inline;">
                                            @csrf
                                            <button class="btn btn-sm btn-primary" style="height:28px; font-size:11px;">通过</button>
                                        </form>
                                        <button class="btn btn-sm btn-ghost" style="height:28px; font-size:11px; color:var(--danger);" x-data @click="$dispatch('reject-site', {{ $site->id }})">拒绝</button>
                                    @else
                                        <a href="{{ route('admin.agent-sites.show', $site) }}" class="btn btn-ghost btn-sm" style="height:28px; font-size:11px;">详情</a>
                                        <form method="POST" action="{{ route('admin.agent-sites.toggle', $site) }}" style="display:inline;">
                                            @csrf
                                            <button class="btn btn-ghost btn-sm" style="height:28px; font-size:11px;">{{ $site->is_active ? '禁用' : '启用' }}</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.agent-sites.destroy', $site) }}" style="display:inline;"
                                          x-data @submit.prevent="$dispatch('confirm', { title: '删除分站', message: '确定删除「{{ addslashes($site->site_name) }}」？此操作不可恢复。', form: $el })">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="height:28px; font-size:11px; color:var(--danger);" data-no-loading>删除</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🏪</div>
                                        <div class="empty-state-text">暂无分站数据</div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- 批量操作栏 --}}
                <div id="batchBar" style="display:none; padding:12px 24px; border-top:1px solid var(--line); align-items:center; gap:12px; background:var(--accent-soft);">
                    <span style="font-size:13px; font-weight:500;">已选 <strong id="batchCount">0</strong> 项</span>
                    <button type="submit" name="action" value="enable" class="btn btn-sm btn-ghost">批量启用</button>
                    <button type="submit" name="action" value="disable" class="btn btn-sm btn-ghost">批量禁用</button>
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-ghost" style="color:var(--danger);" onclick="return confirm('确定批量删除所选分站？')">批量删除</button>
                </div>
            </form>
        </div>
    </div>

    @if($sites->hasPages())
    <div style="margin-top:16px; display:flex; justify-content:flex-end;">
        {{ $sites->links() }}
    </div>
    @endif
@endsection

@push('modals')
{{-- 拒绝弹窗 --}}
<div x-data="rejectModal()" @reject-site.window="open($event.detail)"
     x-show="show" x-cloak style="display:none; position:fixed; inset:0; z-index:9999;">
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.4); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center;" @click.self="show = false">
        <div class="modal-box" style="max-width:420px; text-align:left;">
            <h3 style="font-size:16px; font-weight:600; margin-bottom:16px;">拒绝分站申请</h3>
            <form :action="'/admin/agent-sites/' + siteId + '/reject'" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">拒绝原因</label>
                    <textarea name="reject_reason" class="form-textarea" rows="3" placeholder="请输入拒绝原因..." required></textarea>
                </div>
                <div style="display:flex; gap:12px; margin-top:8px;">
                    <button type="submit" class="btn btn-danger btn-sm">确认拒绝</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="show = false">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function rejectModal() {
    return {
        show: false,
        siteId: null,
        open(id) { this.siteId = id; this.show = true; }
    };
}

document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.batch-check').forEach(cb => cb.checked = this.checked);
    updateBatchBar();
});
document.querySelectorAll('.batch-check').forEach(cb => cb.addEventListener('change', updateBatchBar));

function updateBatchBar() {
    const checked = document.querySelectorAll('.batch-check:checked').length;
    document.getElementById('batchCount').textContent = checked;
    document.getElementById('batchBar').style.display = checked > 0 ? 'flex' : 'none';
}
</script>
@endpush
