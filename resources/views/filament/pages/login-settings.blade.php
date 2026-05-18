<x-filament-panels::page>
    <style>
        .ls-wrap { max-width: 780px; }
        .ls-wrap .fi-section {
            border-radius: 12px !important;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            transition: box-shadow .2s;
            margin-bottom: .75rem;
        }
        .dark .ls-wrap .fi-section { border-color: rgba(255,255,255,.08); }
        .ls-wrap .fi-section:hover { box-shadow: 0 4px 12px rgba(0,0,0,.06); }
        .dark .ls-wrap .fi-section:hover { box-shadow: 0 4px 12px rgba(0,0,0,.3); }
        .ls-btn-row { margin-top: 1.25rem; }
    </style>

    <div class="ls-wrap">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="ls-btn-row">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    保存设置
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
