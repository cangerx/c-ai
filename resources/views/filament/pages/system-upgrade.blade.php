<x-filament-panels::page>
    <style>
        .upgrade-card {
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .upgrade-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .upgrade-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 6px 6px 0 0;
        }
        .upgrade-card.backend::before { background: linear-gradient(90deg, #f59e0b, #ef4444); }
        .upgrade-card.frontend::before { background: linear-gradient(90deg, #3b82f6, #8b5cf6); }
        .upgrade-card.env::before { background: linear-gradient(90deg, #10b981, #06b6d4); }
        .upgrade-card.pm2::before { background: linear-gradient(90deg, #ec4899, #f43f5e); }

        .terminal {
            background: #0d1117;
            border: 1px solid #21262d;
            font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', 'Consolas', monospace;
        }
        .terminal-header {
            background: #161b22;
            border-bottom: 1px solid #21262d;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
        }
        .terminal-dot { width: 10px; height: 10px; border-radius: 50%; }
        .terminal-dot.red { background: #ff5f57; }
        .terminal-dot.yellow { background: #febc2e; }
        .terminal-dot.green { background: #28c840; }
        .terminal-body {
            padding: 14px 16px;
            max-height: 420px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #30363d #0d1117;
        }
        .terminal-body::-webkit-scrollbar { width: 6px; }
        .terminal-body::-webkit-scrollbar-track { background: #0d1117; }
        .terminal-body::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }
        .terminal-line { line-height: 1.7; font-size: 12px; white-space: pre-wrap; word-break: break-all; }
        .terminal-line .success { color: #3fb950; }
        .terminal-line .warn { color: #d29922; }
        .terminal-line .error { color: #f85149; }
        .terminal-line .info { color: #58a6ff; }
        .terminal-line .dim { color: #484f58; }

        .btn-upgrade {
            position: relative;
            overflow: hidden;
        }
        .btn-upgrade::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.4s, height 0.4s;
        }
        .btn-upgrade:active::after {
            width: 200px;
            height: 200px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .pulse-dot.online {
            background: #3fb950;
            box-shadow: 0 0 0 0 rgba(63,185,80,0.6);
            animation: pulse-green 2s infinite;
        }
        .pulse-dot.building {
            background: #d29922;
            box-shadow: 0 0 0 0 rgba(210,153,34,0.6);
            animation: pulse-yellow 1s infinite;
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(63,185,80,0.6); }
            70% { box-shadow: 0 0 0 6px rgba(63,185,80,0); }
            100% { box-shadow: 0 0 0 0 rgba(63,185,80,0); }
        }
        @keyframes pulse-yellow {
            0% { box-shadow: 0 0 0 0 rgba(210,153,34,0.6); }
            70% { box-shadow: 0 0 0 6px rgba(210,153,34,0); }
            100% { box-shadow: 0 0 0 0 rgba(210,153,34,0); }
        }

        .status-banner {
            transition: all 0.3s ease;
        }
    </style>

    <div class="space-y-5">
        {{-- 运行状态横幅 --}}
        <div wire:loading.remove wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div class="status-banner flex items-center gap-3 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <span class="pulse-dot online"></span>
                <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">系统运行正常</span>
                <span class="text-xs text-emerald-500 dark:text-emerald-500/70 ml-auto">就绪</span>
            </div>
        </div>
        <div wire:loading wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div class="status-banner flex items-center gap-3 px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                <span class="pulse-dot building"></span>
                <span class="text-sm font-medium text-amber-700 dark:text-amber-400">正在升级，请勿关闭页面...</span>
                <svg class="animate-spin h-4 w-4 text-amber-500 ml-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
        </div>

        {{-- 版本信息卡片 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="upgrade-card backend p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path></svg>
                        后端
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 font-medium">Laravel</span>
                </div>
                <p class="text-sm font-mono text-gray-900 dark:text-white truncate" title="{{ $versionInfo['backend_commit'] ?? '未知' }}">{{ $versionInfo['backend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['backend_date']))
                    <p class="text-[11px] text-gray-400 mt-1.5 font-mono">{{ $versionInfo['backend_date'] }}</p>
                @endif
            </div>

            <div class="upgrade-card frontend p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        前端
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 font-medium">Next.js</span>
                </div>
                <p class="text-sm font-mono text-gray-900 dark:text-white truncate" title="{{ $versionInfo['frontend_commit'] ?? '未知' }}">{{ $versionInfo['frontend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['frontend_date']))
                    <p class="text-[11px] text-gray-400 mt-1.5 font-mono">{{ $versionInfo['frontend_date'] }}</p>
                @endif
            </div>

            <div class="upgrade-card env p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        环境
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
                </div>
                <p class="text-sm text-gray-900 dark:text-white">{{ app()->environment() }}</p>
                <p class="text-[11px] text-gray-400 mt-1.5 font-mono">Laravel {{ app()->version() }}</p>
            </div>

            <div class="upgrade-card pm2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        服务
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-pink-100 dark:bg-pink-500/10 text-pink-600 dark:text-pink-400 font-medium">PM2</span>
                </div>
                <p class="text-sm text-gray-900 dark:text-white">{{ $versionInfo['worker_count'] ?? '?' }} Workers</p>
                <p class="text-[11px] text-gray-400 mt-1.5">前端 + 任务队列</p>
            </div>
        </div>

        {{-- 操作按钮 --}}
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button
                wire:click="upgradeAll"
                wire:loading.attr="disabled"
                wire:target="upgradeAll, upgradeBackend, upgradeFrontend"
                class="btn-upgrade inline-flex items-center gap-2.5 px-6 py-2.5 rounded-lg text-sm font-semibold text-white
                       bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700
                       shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="upgradeAll" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                一键全栈升级
            </button>

            <button
                wire:click="upgradeBackend"
                wire:loading.attr="disabled"
                wire:target="upgradeAll, upgradeBackend, upgradeFrontend"
                class="btn-upgrade inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
                       text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-300 dark:border-gray-600
                       hover:bg-gray-50 dark:hover:bg-gray-700 transition-all
                       disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4 text-amber-500" wire:loading.class="animate-spin" wire:target="upgradeBackend" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"></path>
                </svg>
                仅后端
            </button>

            <button
                wire:click="upgradeFrontend"
                wire:loading.attr="disabled"
                wire:target="upgradeAll, upgradeBackend, upgradeFrontend"
                class="btn-upgrade inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
                       text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800
                       border border-gray-300 dark:border-gray-600
                       hover:bg-gray-50 dark:hover:bg-gray-700 transition-all
                       disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <svg class="w-4 h-4 text-blue-500" wire:loading.class="animate-spin" wire:target="upgradeFrontend" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                仅前端
            </button>

            @if($log)
                <button
                    wire:click="$set('log', '')"
                    class="ml-auto inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium
                           text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    清除日志
                </button>
            @endif
        </div>

        {{-- 终端日志 --}}
        @if($log)
            <div class="terminal rounded-xl overflow-hidden shadow-lg" x-data x-init="$nextTick(() => { $el.querySelector('.terminal-body').scrollTop = $el.querySelector('.terminal-body').scrollHeight })">
                <div class="terminal-header">
                    <span class="terminal-dot red"></span>
                    <span class="terminal-dot yellow"></span>
                    <span class="terminal-dot green"></span>
                    <span class="text-[11px] text-gray-500 ml-2 font-mono">upgrade — bash</span>
                    <span class="text-[10px] text-gray-600 ml-auto font-mono">{{ now()->format('H:i:s') }}</span>
                </div>
                <div class="terminal-body" wire:poll.visible="$refresh">
                    @foreach(explode("\n", $log) as $line)
                        <div class="terminal-line
                            @if(str_contains($line, '✓')) success
                            @elseif(str_contains($line, '⚠')) warn
                            @elseif(str_contains($line, '✗') || str_contains($line, 'error') || str_contains($line, 'Error')) error
                            @elseif(str_starts_with($line, '→') || str_starts_with($line, '---') || str_starts_with($line, '===')) info
                            @elseif(str_starts_with($line, '(无输出)')) dim
                            @else text-gray-300
                            @endif
                        ">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
