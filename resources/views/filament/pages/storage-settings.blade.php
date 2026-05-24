<x-filament-panels::page>
    <style>
        /* 1. SaaS 双栏栅格排版 (SaaS Split Dual-Column Layout) */
        .ss-split-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (min-width: 1024px) {
            .ss-split-layout {
                grid-template-columns: 2.2fr 1fr;
                gap: 1.75rem;
            }
        }
 
        /* 2. 驱动 Radio 卡片化高端样式 */
        .storage-driver-radio {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
            gap: 0.75rem !important;
            margin-top: 0.35rem;
        }
        .storage-driver-radio .fi-fo-radio-label {
            position: relative;
            border: 1px solid rgba(226, 232, 240, 0.8);
            background: #ffffff;
            border-radius: 12px;
            padding: 1.15rem 1rem !important;
            cursor: pointer;
            margin: 0 !important;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .dark .storage-driver-radio .fi-fo-radio-label {
            border-color: rgba(255, 255, 255, 0.06);
            background: rgba(17, 24, 39, 0.55);
            backdrop-filter: blur(8px);
        }
        .storage-driver-radio .fi-fo-radio-label:hover {
            border-color: rgba(96, 165, 250, 0.8);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03), 0 2px 4px rgba(96, 165, 250, 0.03);
            transform: translateY(-1px);
        }
        .storage-driver-radio .fi-fo-radio-label:has(input:checked) {
            border-color: rgb(59, 130, 246);
            background: linear-gradient(to bottom right, rgb(239, 246, 255), #ffffff);
            box-shadow: 0 4px 18px rgba(59, 130, 246, 0.06), 0 0 0 3px rgba(59, 130, 246, 0.12);
        }
        .dark .storage-driver-radio .fi-fo-radio-label:has(input:checked) {
            border-color: rgb(96, 165, 250);
            background: linear-gradient(to bottom right, rgba(30, 58, 138, 0.25), rgba(17, 24, 39, 0.6));
            box-shadow: 0 4px 18px rgba(96, 165, 250, 0.08), 0 0 0 3px rgba(96, 165, 250, 0.15);
        }
        .storage-driver-radio .fi-fo-radio-label-text > p:first-child {
            font-weight: 600;
            color: rgb(17, 24, 39);
            font-size: 0.925rem;
            letter-spacing: -0.01em;
        }
        .dark .storage-driver-radio .fi-fo-radio-label-text > p:first-child {
            color: rgb(243, 244, 246);
        }
        .storage-driver-radio .fi-fo-radio-label-description {
            margin-top: 0.3rem !important;
            font-size: 0.8rem !important;
            color: rgb(100, 116, 139) !important;
            line-height: 1.4 !important;
        }
        .dark .storage-driver-radio .fi-fo-radio-label-description {
            color: rgb(148, 163, 184) !important;
        }
 
        /* 3. 右侧悬浮控制面板 (Sticky Control Sidebar) */
        .ss-sidebar-sticky {
            position: sticky;
            top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .ss-sidebar-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.015), 0 1px 3px rgba(0, 0, 0, 0.01);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            transition: all 0.3s ease;
        }
        .dark .ss-sidebar-card {
            background: rgba(17, 24, 39, 0.7);
            border-color: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
 
        /* 侧边栏标题样式 */
        .ss-sidebar-title {
            font-size: 13px;
            font-weight: 700;
            color: rgb(71, 85, 105);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-left: 3px solid #3b82f6;
            padding-left: 8px;
            line-height: 1.2;
        }
        .dark .ss-sidebar-title {
            color: rgb(148, 163, 184);
            border-left-color: #60a5fa;
        }
 
        /* 侧边栏状态 */
        .ss-status-glow {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 12px;
            padding: 0.75rem 1rem;
        }
        .dark .ss-status-glow {
            background: rgba(30, 41, 59, 0.4);
            border-color: rgba(255, 255, 255, 0.04);
        }
 
        /* 4. 路由拓扑网络流 (Topology Stream Layout) */
        .ss-topo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0.875rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.6);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .dark .ss-topo-item {
            background: rgba(30, 41, 59, 0.4);
            border-color: rgba(255, 255, 255, 0.04);
        }
        .ss-topo-item:hover {
            transform: translateX(3px);
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.01);
        }
        .ss-topo-flow {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #475569;
        }
        .dark .ss-topo-flow {
            color: #94a3b8;
        }
        .ss-topo-flow-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #cbd5e1;
        }
        .ss-topo-dest {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 700;
            color: #1e293b;
        }
        .dark .ss-topo-dest {
            color: #e2e8f0;
        }
 
        /* 微徽章 (Mini Badge) */
        .ss-mini-badge {
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 999px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .ss-mini-badge-emerald { background: rgba(16, 185, 129, 0.12); color: #10b981; }
        .ss-mini-badge-amber   { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
        .ss-mini-badge-gray    { background: rgba(148, 148, 148, 0.12); color: #94a3b8; }
 
        /* 呼吸微发光 */
        @keyframes pulse-dot-emerald {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 8px 3px rgba(16, 185, 129, 0.6); }
        }
        @keyframes pulse-dot-rose {
            0%, 100% { box-shadow: 0 0 0 0 rgba(244, 63, 94, 0.4); }
            50% { box-shadow: 0 0 8px 3px rgba(244, 63, 94, 0.6); }
        }
        .dot-emerald { background: #10b981; animation: pulse-dot-emerald 2s infinite ease-in-out; }
        .dot-rose    { background: #f43f5e; animation: pulse-dot-rose 2s infinite ease-in-out; }
        .dot-amber   { background: #f59e0b; }
 
        /* 5. 侧边栏按钮排布 */
        .ss-action-list {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .ss-btn-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        /* 强制覆写侧栏 Filament 按钮为全宽且平整化 */
        .ss-action-list form button,
        .ss-action-list div button {
            width: 100% !important;
            justify-content: center !important;
            border-radius: 12px !important;
            padding: 0.5rem 1rem !important;
        }
    </style>
 
    @php($s = $summary)
    @php($d = $s['driver'] ?? 'local')
    @php($mode = $s['mode'] ?? 'cloud')
 
    <div class="ss-split-layout">
        <!-- 左侧核心配置区 (Left Column - Form Area) -->
        <div class="ss-main-form-flow">
            <form wire:submit="save">
                {{ $this->form }}
            </form>
        </div>
 
        <!-- 右侧悬浮控制面板 (Right Column - Floating Sidebar) -->
        <div class="ss-sidebar-sticky">
            {{-- 1. 运行状态卡片 (Status Center Card) --}}
            <div class="ss-sidebar-card">
                <div class="ss-sidebar-title">服务运行状态</div>
                
                <div class="ss-status-glow">
                    <div style="display: flex; align-items: center; gap: 0.625rem;">
                        <x-dynamic-component :component="$s['driver_icon'] ?? 'heroicon-o-server-stack'" style="width: 1.5rem; height: 1.5rem; color: #3b82f6;" />
                        <div style="line-height: 1.2;">
                            <div style="font-size: 14px; font-weight: 700;" class="dark:text-white">{{ $s['driver_label'] }}</div>
                            <div style="font-size: 11px; color: #64748b; margin-top: 1px;">方案: {{ $s['mode_label'] }}</div>
                        </div>
                    </div>
                    
                    <div>
                        @if ($d === 'local')
                            <span class="ss-mini-badge ss-mini-badge-gray" style="display:inline-flex; align-items:center; gap: 4px;">
                                <span class="ss-topo-flow-dot"></span>
                                本地免试
                            </span>
                        @elseif ($s['last_test_ok'] && !empty($s['last_test_at']))
                            <span class="ss-mini-badge ss-mini-badge-emerald" style="display:inline-flex; align-items:center; gap: 4px;">
                                <span class="ss-topo-flow-dot dot-emerald"></span>
                                连接正常
                            </span>
                        @elseif (!empty($s['last_test_at']))
                            <span class="ss-mini-badge ss-mini-badge-rose" style="display:inline-flex; align-items:center; gap: 4px;">
                                <span class="ss-topo-flow-dot dot-rose"></span>
                                连接失败
                            </span>
                        @else
                            <span class="ss-mini-badge ss-mini-badge-amber" style="display:inline-flex; align-items:center; gap: 4px;">
                                <span class="ss-topo-flow-dot dot-amber"></span>
                                待验证
                            </span>
                        @endif
                    </div>
                </div>
 
                @if($d !== 'local')
                    <div style="font-size: 12px; color: #64748b; line-height: 1.45; word-break: break-all;">
                        <span style="font-weight: 600;">映射存储桶：</span><span style="font-family: ui-monospace, monospace; color: #334155;" class="dark:text-slate-300">{{ $s['bucket'] }}</span>
                        @if(!empty($s['last_test_at']))
                            <div style="margin-top: 6px; font-size: 11px;">上次校验：{{ $s['last_test_at'] }}</div>
                        @endif
                    </div>
                @endif
            </div>
 
            {{-- 2. 流量用途诊断池拓扑 (Routing Topology Pool) --}}
            @if(!empty($diagnostics['profiles']))
                <div class="ss-sidebar-card">
                    <div class="ss-sidebar-title">流量路由池拓扑</div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                        @foreach($diagnostics['profiles'] as $profile)
                            <div class="ss-topo-item">
                                <div class="ss-topo-flow">
                                    <span class="ss-topo-flow-dot" style="background: {{ $profile['status'] === 'ready' ? '#10b981' : ($profile['status'] === 'local' ? '#94a3b8' : '#f59e0b') }}; box-shadow: 0 0 6px {{ $profile['status'] === 'ready' ? '#10b981' : ($profile['status'] === 'local' ? '#94a3b8' : '#f59e0b') }};"></span>
                                    <span>{{ $profile['label'] }}</span>
                                </div>
                                <div class="ss-topo-dest">
                                    <span style="font-size: 11px; opacity: 0.8; font-weight: 500;">
                                        {{ $profile['driver_label'] }}
                                    </span>
                                    @if($profile['status'] === 'ready')
                                        <span class="ss-mini-badge ss-mini-badge-emerald">云端</span>
                                    @elseif($profile['status'] === 'incomplete')
                                        <span class="ss-mini-badge ss-mini-badge-amber">异常</span>
                                    @else
                                        <span class="ss-mini-badge ss-mini-badge-gray">本地</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
 
            {{-- 3. 安全操作中心 (Control actions) --}}
            <div class="ss-sidebar-card">
                <div class="ss-sidebar-title">配置操作中心</div>
                
                <div class="ss-action-list">
                    {{-- 3.1 一键保存当前表单 --}}
                    <x-filament::button wire:click="save" icon="heroicon-o-bookmark-square" size="lg" color="primary" style="width: 100%; justify-content: center; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);">
                        保存当前配置
                    </x-filament::button>
 
                    {{-- 3.2 异步连通性校验 + 文件读写校验 (并列排版) --}}
                    @if ($d !== 'local')
                        <div class="ss-btn-row">
                            {{ $this->testConnectionAction }}
                            {{ $this->uploadProbeAction }}
                        </div>
                    @else
                        <div style="font-size: 11px; color: #94a3b8; text-align: center; line-height: 1.3;">
                            本地磁盘为本机持久运行，无需执行远端校验测试。
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
