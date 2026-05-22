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
        .su-term-body { max-height: 420px; scrollbar-width: thin; scrollbar-color: #4b5563 #1f2937; }
        .su-term-body::-webkit-scrollbar { width: 6px; }
        .su-term-body::-webkit-scrollbar-track { background: #1f2937; }
        .su-term-body::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 3px; }
    </style>

    <div class="space-y-5">
        {{-- 运行状态横幅 --}}
        <div wire:loading.remove wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                <span class="su-pulse inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-sm font-medium text-emerald-700 dark:text-emerald-400">系统运行正常</span>
                <span class="text-xs text-emerald-500/70 ml-auto">就绪</span>
            </div>
        </div>
        <div wire:loading wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                <span class="su-pulse-warn inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-sm font-medium text-amber-700 dark:text-amber-400">正在升级，请勿关闭页面...</span>
                <svg class="animate-spin h-4 w-4 text-amber-500 ml-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
        </div>

        {{-- 版本信息卡片 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- 后端 --}}
            <div class="relative overflow-hidden p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-amber-500 to-red-500 rounded-t-xl"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-server-stack class="w-3.5 h-3.5" />
                        后端
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 font-medium">Laravel</span>
                </div>
                <p class="text-sm font-mono text-gray-900 dark:text-white truncate" title="{{ $versionInfo['backend_commit'] ?? '未知' }}">{{ $versionInfo['backend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['backend_date']))
                    <p class="text-[11px] text-gray-400 mt-1.5 font-mono">{{ $versionInfo['backend_date'] }}</p>
                @endif
            </div>

            {{-- 前端 --}}
            <div class="relative overflow-hidden p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-blue-500 to-violet-500 rounded-t-xl"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-computer-desktop class="w-3.5 h-3.5" />
                        前端
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 font-medium">Next.js</span>
                </div>
                <p class="text-sm font-mono text-gray-900 dark:text-white truncate" title="{{ $versionInfo['frontend_commit'] ?? '未知' }}">{{ $versionInfo['frontend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['frontend_date']))
                    <p class="text-[11px] text-gray-400 mt-1.5 font-mono">{{ $versionInfo['frontend_date'] }}</p>
                @endif
            </div>

            {{-- 环境 --}}
            <div class="relative overflow-hidden p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-t-xl"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-cog-6-tooth class="w-3.5 h-3.5" />
                        环境
                    </span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-medium">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}</span>
                </div>
                <p class="text-sm text-gray-900 dark:text-white">{{ app()->environment() }}</p>
                <p class="text-[11px] text-gray-400 mt-1.5 font-mono">Laravel {{ app()->version() }}</p>
            </div>

            {{-- 服务 --}}
            <div class="relative overflow-hidden p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-pink-500 to-rose-500 rounded-t-xl"></div>
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-bolt class="w-3.5 h-3.5" />
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
                class="inline-flex items-center gap-2.5 px-6 py-2.5 rounded-lg text-sm font-semibold text-white
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
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
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
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
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
                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                    清除日志
                </button>
            @endif
        </div>

        {{-- 终端日志 --}}
        @if($log)
            <div class="rounded-xl overflow-hidden shadow-lg border border-gray-200 dark:border-gray-700"
                 x-data x-init="$nextTick(() => { const b = $el.querySelector('[data-term-body]'); if(b) b.scrollTop = b.scrollHeight })">
                <div class="flex items-center gap-1.5 px-3 py-2.5 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400 ml-2 font-mono">upgrade — bash</span>
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 ml-auto font-mono">{{ now()->format('H:i:s') }}</span>
                </div>
                <div data-term-body class="su-term-body p-4 bg-gray-900 overflow-y-auto font-mono text-xs leading-relaxed" style="max-height:420px">
                    @foreach(explode("\n", $log) as $line)
                        <div class="whitespace-pre-wrap break-all
                            @if(str_contains($line, '✓')) text-emerald-400
                            @elseif(str_contains($line, '⚠')) text-amber-400
                            @elseif(str_contains($line, '✗') || str_contains($line, 'error') || str_contains($line, 'Error')) text-red-400
                            @elseif(str_starts_with($line, '→') || str_starts_with($line, '---') || str_starts_with($line, '===')) text-blue-400
                            @elseif(str_starts_with($line, '(无输出)')) text-gray-600
                            @else text-gray-300
                            @endif
                        ">{{ $line }}</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
