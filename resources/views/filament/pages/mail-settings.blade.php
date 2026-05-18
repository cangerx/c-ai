<x-filament-panels::page>
    {{-- 快捷预设 --}}
    <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-4 mb-6">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">快捷预设 — 选择服务商自动填充服务器配置</p>
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button size="sm" color="gray" wire:click="applyPreset('qq')">QQ 邮箱</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="applyPreset('163')">网易 163</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="applyPreset('aliyun')">阿里企业邮</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="applyPreset('outlook')">Outlook</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="applyPreset('gmail')">Gmail</x-filament::button>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6" style="display:flex; align-items:center; gap:12px;">
            <x-filament::button type="submit">保存设置</x-filament::button>
            <div style="display:inline-flex; align-items:center;">
                {{ $this->testMailAction }}
            </div>
        </div>
    </form>
</x-filament-panels::page>
