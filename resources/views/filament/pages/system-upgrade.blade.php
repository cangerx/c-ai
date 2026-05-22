<x-filament-panels::page>
    <style>
        .su-pulse { animation: su-pulse-anim 2s infinite; }
        @keyframes su-pulse-anim {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
            70% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .su-pulse-warn { animation: su-pulse-warn-anim 1s infinite; }
        @keyframes su-pulse-warn-anim {
            0% { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
            70% { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
            100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        }
        .su-card { position: relative; overflow: hidden; padding: 1rem; background: #fff; border-radius: 0.75rem; border: 1px solid #e5e7eb; transition: box-shadow 0.2s; }
        .su-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .su-card-bar { position: absolute; top: 0; left: 0; right: 0; height: 3px; border-radius: 0.75rem 0.75rem 0 0; }
        .su-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 500; }
        .su-date { font-size: 11px; color: #9ca3af; margin-top: 6px; font-family: ui-monospace, monospace; }
        .su-label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #9ca3af; }
        .su-btn-primary { display: inline-flex; align-items: center; gap: 10px; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; color: #fff; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
        .su-btn-primary:hover { box-shadow: 0 4px 16px rgba(99,102,241,0.4); }
        .su-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .su-btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #374151; background: #fff; border: 1px solid #d1d5db; cursor: pointer; transition: all 0.2s; }
        .su-btn-secondary:hover { background: #f9fafb; }
        .su-btn-secondary:disabled { opacity: 0.5; cursor: not-allowed; }
        .su-term { border-radius: 0.75rem; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .su-term-header { display: flex; align-items: center; gap: 6px; padding: 10px 14px; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
        .su-term-dot { width: 10px; height: 10px; border-radius: 50%; }
        .su-term-body { padding: 14px 16px; background: #111827; max-height: 420px; overflow-y: auto; font-family: ui-monospace, monospace; font-size: 12px; line-height: 1.7; scrollbar-width: thin; scrollbar-color: #4b5563 #111827; }
        .su-term-body::-webkit-scrollbar { width: 6px; }
        .su-term-body::-webkit-scrollbar-track { background: #111827; }
        .su-term-body::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
        .su-line { white-space: pre-wrap; word-break: break-all; }
        .su-line-ok { color: #4ade80; }
        .su-line-warn { color: #fbbf24; }
        .su-line-err { color: #f87171; }
        .su-line-info { color: #60a5fa; }
        .su-line-dim { color: #6b7280; }
        .su-line-default { color: #d1d5db; }
        @media (prefers-color-scheme: dark) {
            .su-card { background: #1f2937; border-color: #374151; }
            .su-card .su-label { color: #6b7280; }
            .su-card .su-commit { color: #fff; }
            .su-btn-secondary { background: #1f2937; border-color: #4b5563; color: #d1d5db; }
            .su-btn-secondary:hover { background: #374151; }
            .su-term { border-color: #374151; }
            .su-term-header { background: #1f2937; border-color: #374151; }
        }
    </style>

    <div style="display:flex;flex-direction:column;gap:20px">
        {{-- 运行状态横幅 --}}
        <div wire:loading.remove wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;background:#ecfdf5;border:1px solid #a7f3d0">
                <span class="su-pulse" style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block"></span>
                <span style="font-size:14px;font-weight:500;color:#15803d">系统运行正常</span>
                <span style="font-size:12px;color:#16a34a;margin-left:auto;opacity:0.7">就绪</span>
            </div>
        </div>
        <div wire:loading wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a">
                <span class="su-pulse-warn" style="width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block"></span>
                <span style="font-size:14px;font-weight:500;color:#92400e">正在升级，请勿关闭页面...</span>
                <svg style="margin-left:auto;color:#f59e0b;animation:spin 1s linear infinite" width="16" height="16" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
        </div>

        {{-- 版本信息卡片 --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px">
            {{-- 后端 --}}
            <div class="su-card">
                <div class="su-card-bar" style="background:linear-gradient(90deg,#f59e0b,#ef4444)"></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <span class="su-label">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path></svg>
                        后端
                    </span>
                    <span class="su-badge" style="background:#fef3c7;color:#92400e">Laravel</span>
                </div>
                <p class="su-commit" style="font-size:14px;font-family:ui-monospace,monospace;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $versionInfo['backend_commit'] ?? '未知' }}">{{ $versionInfo['backend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['backend_date']))
                    <p class="su-date">{{ $versionInfo['backend_date'] }}</p>
                @endif
            </div>

            {{-- 前端 --}}
            <div class="su-card">
                <div class="su-card-bar" style="background:linear-gradient(90deg,#3b82f6,#8b5cf6)"></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <span class="su-label">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        前端
                    </span>
                    <span class="su-badge" style="background:#dbeafe;color:#1d4ed8">Next.js</span>
                </div>
                <p class="su-commit" style="font-size:14px;font-family:ui-monospace,monospace;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $versionInfo['frontend_commit'] ?? '未知' }}">{{ $versionInfo['frontend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['frontend_date']))
                    <p class="su-date">{{ $versionInfo['frontend_date'] }}</p>
                @endif
            </div>

            {{-- 环境 --}}
            <div class="su-card">
                <div class="su-card-bar" style="background:linear-gradient(90deg,#10b981,#06b6d4)"></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <span class="su-label">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        环境
                    </span>
                    <span class="su-badge" style="background:#d1fae5;color:#065f46">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
                </div>
                <p class="su-commit" style="font-size:14px;color:#111827">{{ app()->environment() }}</p>
                <p class="su-date">Laravel {{ app()->version() }}</p>
            </div>

            {{-- 服务 --}}
            <div class="su-card">
                <div class="su-card-bar" style="background:linear-gradient(90deg,#ec4899,#f43f5e)"></div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <span class="su-label">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        服务
                    </span>
                    <span class="su-badge" style="background:#fce7f3;color:#9d174d">PM2</span>
                </div>
                <p class="su-commit" style="font-size:14px;color:#111827">{{ $versionInfo['worker_count'] ?? '?' }} Workers</p>
                <p class="su-date">前端 + 任务队列</p>
            </div>
        </div>

        {{-- 操作按钮 --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding-top:4px">
            <button wire:click="upgradeAll" wire:loading.attr="disabled" wire:target="upgradeAll, upgradeBackend, upgradeFrontend" class="su-btn-primary">
                <svg width="16" height="16" wire:loading.attr="style" wire:target="upgradeAll" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                一键全栈升级
            </button>

            <button wire:click="upgradeBackend" wire:loading.attr="disabled" wire:target="upgradeAll, upgradeBackend, upgradeFrontend" class="su-btn-secondary">
                <svg width="16" height="16" fill="none" stroke="#f59e0b" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path>
                </svg>
                仅后端
            </button>

            <button wire:click="upgradeFrontend" wire:loading.attr="disabled" wire:target="upgradeAll, upgradeBackend, upgradeFrontend" class="su-btn-secondary">
                <svg width="16" height="16" fill="none" stroke="#3b82f6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                仅前端
            </button>

            @if($log)
                <button wire:click="$set('log', '')" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:8px;font-size:12px;font-weight:500;color:#9ca3af;background:none;border:none;cursor:pointer">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    清除日志
                </button>
            @endif
        </div>

        {{-- 终端日志 --}}
        @if($log)
            <div class="su-term" x-data x-init="$nextTick(() => { const b = $el.querySelector('.su-term-body'); if(b) b.scrollTop = b.scrollHeight })">
                <div class="su-term-header">
                    <span class="su-term-dot" style="background:#ff5f57"></span>
                    <span class="su-term-dot" style="background:#febc2e"></span>
                    <span class="su-term-dot" style="background:#28c840"></span>
                    <span style="font-size:11px;color:#6b7280;margin-left:8px;font-family:ui-monospace,monospace">upgrade — bash</span>
                    <span style="font-size:10px;color:#9ca3af;margin-left:auto;font-family:ui-monospace,monospace">{{ now()->format('H:i:s') }}</span>
                </div>
                <div class="su-term-body">
                    @foreach(explode("\n", $log) as $line)
                        <div class="su-line @if(str_contains($line, '✓')) su-line-ok @elseif(str_contains($line, '⚠')) su-line-warn @elseif(str_contains($line, '✗') || str_contains($line, 'error') || str_contains($line, 'Error')) su-line-err @elseif(str_starts_with($line, '→') || str_starts_with($line, '---') || str_starts_with($line, '===')) su-line-info @elseif(str_starts_with($line, '(无输出)')) su-line-dim @else su-line-default @endif">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
