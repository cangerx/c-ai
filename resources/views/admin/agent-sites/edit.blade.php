@extends('admin.layouts.app')

@section('title', '编辑分站 — ' . $agentSite->site_name)

@section('header')
    <h1 class="page-title">编辑分站</h1>
    <p class="page-subtitle">{{ $agentSite->site_name }} — {{ $agentSite->agent->name ?? '' }} ({{ $agentSite->agent->email ?? '' }})</p>
@endsection

@section('content')
    <div style="margin-bottom:16px; display:flex; gap:8px; justify-content:flex-end;">
        <a href="{{ route('admin.agent-sites.show', $agentSite) }}" class="btn btn-ghost btn-sm">查看详情</a>
        <a href="{{ route('admin.agent-sites.index') }}" class="btn btn-ghost btn-sm">返回列表</a>
    </div>

    <form method="POST" action="{{ route('admin.agent-sites.update', $agentSite) }}">
        @csrf @method('PUT')

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">
            <div>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header"><span class="card-title">基本信息</span></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">站点名称</label>
                            <input type="text" name="site_name" class="form-input" value="{{ old('site_name', $agentSite->site_name) }}" required>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">自定义域名</label>
                                <input type="text" name="custom_domain" class="form-input" value="{{ old('custom_domain', $agentSite->custom_domain) }}" placeholder="agent.example.com">
                                <div class="form-hint">代理商独立域名，需 CNAME 到本站</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">主题色</label>
                                <input type="color" name="theme_color" class="form-input" value="{{ old('theme_color', $agentSite->theme_color ?: '#2d5bf0') }}" style="height:42px; padding:4px;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header"><span class="card-title">运营参数</span></div>
                    <div class="card-body">
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                            <div class="form-group">
                                <label class="form-label">单次生成扣费</label>
                                <input type="number" name="cost_per_generation" class="form-input" value="{{ old('cost_per_generation', $agentSite->cost_per_generation) }}" min="1" placeholder="留空=默认">
                                <div class="form-hint">积分</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">佣金比例</label>
                                <input type="number" name="commission_rate" class="form-input" value="{{ old('commission_rate', $agentSite->commission_rate) }}" min="0" max="100" placeholder="留空=默认">
                                <div class="form-hint">%</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">状态</label>
                                <select name="is_active" class="form-select">
                                    <option value="1" {{ $agentSite->is_active ? 'selected' : '' }}>启用</option>
                                    <option value="0" {{ !$agentSite->is_active ? 'selected' : '' }}>禁用</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header"><span class="card-title">代理商等级</span></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">当前等级</label>
                            <select name="agent_level_id" class="form-select">
                                <option value="">默认（无等级）</option>
                                @foreach($agentLevels as $level)
                                    <option value="{{ $level->id }}" {{ ($agentSite->agent->agent_level_id ?? null) == $level->id ? 'selected' : '' }}>
                                        {{ $level->name }} — ¥{{ $level->price_per_credit }}/积分
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-hint">等级决定代理商的进货价</div>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted); padding-top:8px; border-top:1px solid var(--line);">
                            累计充值：¥{{ number_format($agentSite->agent->total_recharged ?? 0, 2) }}
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><span class="card-title">站点信息</span></div>
                    <div class="card-body" style="font-size:13px; color:var(--text-secondary); line-height:2;">
                        <div><strong>Slug：</strong><code>/s/{{ $agentSite->slug }}</code></div>
                        <div><strong>子域名：</strong>{{ $agentSite->subdomain ?: '未设置' }}</div>
                        <div><strong>创建时间：</strong>{{ $agentSite->created_at->format('Y-m-d H:i') }}</div>
                        <div><strong>审核状态：</strong>
                            @if($agentSite->status === 'pending') <span class="badge badge-warning">待审核</span>
                            @elseif($agentSite->status === 'approved') <span class="badge badge-success">已通过</span>
                            @else <span class="badge badge-danger">已拒绝</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px; display:flex; gap:12px;">
            <button type="submit" class="btn btn-primary">保存修改</button>
            <a href="{{ route('admin.agent-sites.index') }}" class="btn btn-ghost">取消</a>
        </div>
    </form>
@endsection
