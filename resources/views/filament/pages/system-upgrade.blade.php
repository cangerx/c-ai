<x-filament-panels::page>
    <div class="space-y-6">
        {{-- 版本信息 --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">后端版本</h3>
                <p class="text-sm font-mono text-gray-900 dark:text-white">{{ $versionInfo['backend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['backend_date']))
                    <p class="text-xs text-gray-400 mt-1">{{ $versionInfo['backend_date'] }}</p>
                @endif
            </div>
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">前端版本</h3>
                <p class="text-sm font-mono text-gray-900 dark:text-white">{{ $versionInfo['frontend_commit'] ?? '未知' }}</p>
                @if(!empty($versionInfo['frontend_date']))
                    <p class="text-xs text-gray-400 mt-1">{{ $versionInfo['frontend_date'] }}</p>
                @endif
            </div>
        </div>

        {{-- 操作按钮 --}}
        <div class="flex flex-wrap gap-3">
            <x-filament::button
                wire:click="upgradeAll"
                wire:loading.attr="disabled"
                color="primary"
                icon="heroicon-o-arrow-path"
                size="lg"
            >
                一键全栈升级
            </x-filament::button>

            <x-filament::button
                wire:click="upgradeBackend"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-o-server-stack"
            >
                仅升级后端
            </x-filament::button>

            <x-filament::button
                wire:click="upgradeFrontend"
                wire:loading.attr="disabled"
                color="gray"
                icon="heroicon-o-computer-desktop"
            >
                仅升级前端
            </x-filament::button>
        </div>

        {{-- 加载提示 --}}
        <div wire:loading wire:target="upgradeAll, upgradeBackend, upgradeFrontend">
            <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span class="text-sm font-medium">升级中，请勿关闭页面...</span>
            </div>
        </div>

        {{-- 日志输出 --}}
        @if($log)
            <div class="p-4 bg-gray-900 rounded-xl border border-gray-700">
                <h3 class="text-sm font-medium text-gray-400 mb-2">执行日志</h3>
                <pre class="text-xs text-green-400 font-mono whitespace-pre-wrap max-h-[500px] overflow-y-auto leading-relaxed">{{ $log }}</pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>
