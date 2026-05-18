<x-filament-panels::page>
    <style>
        /* 驱动 Radio 卡片化（Filament v5 fi-fo-radio） */
        .storage-driver-radio {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
            gap: .75rem !important;
        }
        .storage-driver-radio .fi-fo-radio-label {
            position: relative;
            border: 1px solid rgb(229 231 235);
            background: #fff;
            border-radius: .75rem;
            padding: .9rem 1rem !important;
            transition: border-color .15s, box-shadow .15s, background-color .15s;
            cursor: pointer;
            margin: 0 !important;
            display: flex;
            align-items: flex-start;
            gap: .625rem;
        }
        .dark .storage-driver-radio .fi-fo-radio-label {
            border-color: rgb(255 255 255 / .1);
            background: rgb(17 24 39);
        }
        .storage-driver-radio .fi-fo-radio-label:hover {
            border-color: rgb(96 165 250);
            box-shadow: 0 1px 3px rgb(0 0 0 / .05);
        }
        .storage-driver-radio .fi-fo-radio-label:has(input:checked) {
            border-color: rgb(59 130 246);
            background: rgb(239 246 255);
            box-shadow: 0 0 0 3px rgb(59 130 246 / .12);
        }
        .dark .storage-driver-radio .fi-fo-radio-label:has(input:checked) {
            background: rgb(30 58 138 / .25);
        }
        .storage-driver-radio .fi-fo-radio-label-text > p:first-child {
            font-weight: 600;
            color: rgb(17 24 39);
            font-size: .95rem;
        }
        .dark .storage-driver-radio .fi-fo-radio-label-text > p:first-child {
            color: rgb(243 244 246);
        }
        .storage-driver-radio .fi-fo-radio-label-description {
            margin-top: .25rem !important;
            font-size: .8rem !important;
            color: rgb(107 114 128) !important;
            line-height: 1.4 !important;
        }

        /* Hero & Summary */
        .ss-hero {
            margin-bottom: 1.5rem;
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid rgb(229 231 235);
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 60%, #ffffff 100%);
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .ss-hero-top {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1.25rem;
            align-items: center;
            justify-content: space-between;
        }
        .ss-hero-left { display: flex; align-items: center; gap: 1rem; min-width: 0; }
        .ss-hero-icon {
            display: flex; align-items: center; justify-content: center;
            width: 3.5rem; height: 3.5rem;
            border-radius: 12px;
            background: #fff;
            color: #2563eb;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
            border: 1px solid rgb(229 231 235);
            flex-shrink: 0;
        }
        .ss-hero-icon svg { width: 1.75rem; height: 1.75rem; }
        .ss-hero-title {
            display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
            font-size: 1.25rem; font-weight: 600; color: rgb(17 24 39);
            margin: 0;
        }
        .ss-hero-sub {
            margin-top: .25rem;
            font-size: .875rem;
            color: rgb(107 114 128);
            word-break: break-all;
        }
        .ss-tag {
            display: inline-block;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 500;
            line-height: 1.4;
        }
        .ss-tag-gray   { background: #f3f4f6; color: #4b5563; }
        .ss-tag-orange { background: #ffedd5; color: #c2410c; }
        .ss-tag-amber  { background: #fef3c7; color: #b45309; }

        .ss-status {
            display: inline-flex; align-items: center; gap: 6px;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .ss-status svg { width: 14px; height: 14px; flex-shrink: 0; }
        .ss-status-gray    { background: #f3f4f6; color: #4b5563; }
        .ss-status-emerald { background: #d1fae5; color: #047857; }
        .ss-status-rose    { background: #ffe4e6; color: #be123c; }
        .ss-status-amber   { background: #fef3c7; color: #b45309; }

        .ss-summary {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1px;
            background: rgb(229 231 235);
            border-top: 1px solid rgb(229 231 235);
        }
        @media (min-width: 640px) {
            .ss-summary { grid-template-columns: repeat(3, 1fr); }
        }
        .ss-cell {
            background: #fff;
            padding: 1rem;
            border-left: 3px solid transparent;
            min-width: 0;
        }
        .ss-cell-label {
            font-size: 11px;
            font-weight: 500;
            color: rgb(107 114 128);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .ss-cell-value {
            margin-top: 6px;
            font-size: 14px;
            font-weight: 600;
            color: rgb(55 65 81);
            word-break: break-all;
        }
        .ss-cell-value.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .ss-cell-value.ok   { color: rgb(5 150 105); }
        .ss-cell-value.warn { color: rgb(217 119 6); }
        .ss-cell-value.err  { color: rgb(225 29 72); }
        .ss-cell-hint {
            margin-top: 2px;
            font-size: 12px;
            color: rgb(107 114 128);
            word-break: break-all;
        }
        .ss-cell-hint.err { color: rgb(225 29 72); }
        .ss-bd-gray    { border-left-color: #d1d5db; }
        .ss-bd-emerald { border-left-color: #10b981; }
        .ss-bd-amber   { border-left-color: #f59e0b; }
        .ss-bd-rose    { border-left-color: #f43f5e; }

        /* Sticky footer */
        .ss-sticky {
            position: sticky;
            bottom: 0;
            margin-top: 1.5rem;
            padding: .75rem 1rem;
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(6px);
            border-top: 1px solid rgb(229 231 235);
        }
        .ss-sticky-row {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;
        }
        .ss-sticky-tip { font-size: 12px; color: rgb(107 114 128); }
        .ss-sticky-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

        /* Dark mode */
        .dark .ss-hero {
            border-color: rgba(255,255,255,.1);
            background: linear-gradient(135deg, rgba(30,58,138,.35) 0%, rgb(17 24 39) 60%, rgb(17 24 39) 100%);
        }
        .dark .ss-hero-icon {
            background: rgb(31 41 55);
            color: #60a5fa;
            border-color: rgba(255,255,255,.1);
        }
        .dark .ss-hero-title { color: #fff; }
        .dark .ss-hero-sub { color: rgb(156 163 175); }
        .dark .ss-summary { background: rgba(255,255,255,.1); border-top-color: rgba(255,255,255,.1); }
        .dark .ss-cell { background: rgb(17 24 39); }
        .dark .ss-cell-value { color: rgb(229 231 235); }
        .dark .ss-cell-hint { color: rgb(156 163 175); }
        .dark .ss-tag-gray { background: rgba(255,255,255,.1); color: rgb(209 213 219); }
        .dark .ss-tag-orange { background: rgba(249,115,22,.15); color: rgb(253 186 116); }
        .dark .ss-tag-amber  { background: rgba(245,158,11,.15); color: rgb(252 211 77); }
        .dark .ss-status-gray    { background: rgba(255,255,255,.1); color: rgb(209 213 219); }
        .dark .ss-status-emerald { background: rgba(16,185,129,.15); color: rgb(110 231 183); }
        .dark .ss-status-rose    { background: rgba(244,63,94,.15); color: rgb(253 164 175); }
        .dark .ss-status-amber   { background: rgba(245,158,11,.15); color: rgb(252 211 77); }
        .dark .ss-sticky {
            background: rgba(17,24,39,.92);
            border-top-color: rgba(255,255,255,.1);
        }
        .dark .ss-sticky-tip { color: rgb(156 163 175); }
    </style>

    @php($s = $summary)
    @php($d = $s['driver'] ?? 'local')

    {{-- HERO --}}
    <div class="ss-hero">
        <div class="ss-hero-top">
            <div class="ss-hero-left">
                <div class="ss-hero-icon">
                    <x-dynamic-component :component="$s['driver_icon'] ?? 'heroicon-o-server-stack'" />
                </div>
                <div style="min-width:0;">
                    <div class="ss-hero-title">
                        <span>{{ $s['driver_label'] }}</span>
                        @if ($d === 'local')
                            <span class="ss-tag ss-tag-gray">本地</span>
                        @elseif ($d === 'oss')
                            <span class="ss-tag ss-tag-orange">阿里云</span>
                        @elseif ($d === 'r2')
                            <span class="ss-tag ss-tag-amber">Cloudflare</span>
                        @endif
                    </div>
                    <div class="ss-hero-sub">
                        @if ($d === 'local')
                            存到 storage/app/public，无需任何外部凭证
                        @else
                            Bucket <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#374151;">{{ $s['bucket'] }}</span>
                            <span style="margin:0 6px;color:#d1d5db;">·</span>
                            <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#374151;">{{ \Illuminate\Support\Str::limit($s['endpoint'], 56) }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div style="flex-shrink:0;">
                @if ($d === 'local')
                    <span class="ss-status ss-status-gray">
                        <x-heroicon-m-minus-circle />
                        无需测试
                    </span>
                @elseif ($s['last_test_ok'] && !empty($s['last_test_at']))
                    <span class="ss-status ss-status-emerald">
                        <x-heroicon-m-check-circle />
                        连接正常
                    </span>
                @elseif (!empty($s['last_test_at']))
                    <span class="ss-status ss-status-rose">
                        <x-heroicon-m-x-circle />
                        连接异常
                    </span>
                @else
                    <span class="ss-status ss-status-amber">
                        <x-heroicon-m-clock />
                        待测试
                    </span>
                @endif
            </div>
        </div>

        {{-- 三栏摘要 --}}
        <div class="ss-summary">
            {{-- 凭证 --}}
            <div class="ss-cell {{ $d === 'local' ? 'ss-bd-gray' : ($s['has_secret'] ? 'ss-bd-emerald' : 'ss-bd-amber') }}">
                <div class="ss-cell-label">凭证</div>
                @if ($d === 'local')
                    <div class="ss-cell-value">无需凭证</div>
                    <div class="ss-cell-hint">本地存储不需要 Access Key</div>
                @elseif ($s['has_secret'])
                    <div class="ss-cell-value ok">Secret 已配置</div>
                    <div class="ss-cell-hint">出于安全密钥不回显，留空即保留原值</div>
                @else
                    <div class="ss-cell-value warn">Secret 未配置</div>
                    <div class="ss-cell-hint">请在向导第 2 步填入 Secret</div>
                @endif
            </div>

            {{-- 端点 --}}
            <div class="ss-cell">
                <div class="ss-cell-label">服务端点</div>
                @if ($d === 'local')
                    <div class="ss-cell-value">storage/app/public</div>
                    <div class="ss-cell-hint">通过 /storage/* 访问</div>
                @else
                    <div class="ss-cell-value mono" title="{{ $s['endpoint'] }}">{{ $s['endpoint'] }}</div>
                    <div class="ss-cell-hint">Bucket <span style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">{{ $s['bucket'] }}</span></div>
                @endif
            </div>

            {{-- 最近测试 --}}
            <div class="ss-cell {{ empty($s['last_test_at']) ? 'ss-bd-amber' : ($s['last_test_ok'] ? 'ss-bd-emerald' : 'ss-bd-rose') }}">
                <div class="ss-cell-label">最近连通性</div>
                @if (empty($s['last_test_at']))
                    <div class="ss-cell-value">尚未测试</div>
                    <div class="ss-cell-hint">点击下方"连接测试"立即检查</div>
                @elseif ($s['last_test_ok'])
                    <div class="ss-cell-value ok">可用</div>
                    <div class="ss-cell-hint">{{ $s['last_test_at'] }}</div>
                @else
                    <div class="ss-cell-value err">失败</div>
                    <div class="ss-cell-hint err" title="{{ $s['last_test_msg'] }}">{{ $s['last_test_msg'] }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- 表单（Wizard） --}}
    <form wire:submit="save" style="padding-bottom:6rem;">
        {{ $this->form }}
    </form>

    {{-- Sticky footer --}}
    <div class="ss-sticky">
        <div class="ss-sticky-row">
            <div class="ss-sticky-tip">
                @if ($d === 'local')
                    本地驱动无需测试，直接保存即可
                @else
                    保存只写配置，请单独点击"连接测试"验证
                @endif
            </div>
            <div class="ss-sticky-actions">
                {{ $this->testConnectionAction }}
                {{ $this->uploadProbeAction }}
                <x-filament::button wire:click="save" icon="heroicon-o-bookmark-square" wire:loading.attr="disabled">
                    保存配置
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
