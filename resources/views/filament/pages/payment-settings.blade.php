<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3 pt-2">
            <x-filament::button type="submit" icon="heroicon-m-check">保存设置</x-filament::button>
            {{ $this->testConnectionAction }}
        </div>
    </form>
</x-filament-panels::page>
