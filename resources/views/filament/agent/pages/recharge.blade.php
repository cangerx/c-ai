<x-filament-panels::page>
    <form wire:submit="redeem">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                立即兑换
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
