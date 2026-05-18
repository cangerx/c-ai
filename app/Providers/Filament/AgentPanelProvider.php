<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AgentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agent')
            ->path('agent')
            ->login()
            ->brandName('代理中心')
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Agent/Resources'), for: 'App\Filament\Agent\Resources')
            ->discoverPages(in: app_path('Filament/Agent/Pages'), for: 'App\Filament\Agent\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agent/Widgets'), for: 'App\Filament\Agent\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook('panels::head.end', fn () => new HtmlString('
                <style>
                    .fi-ta-row { transition: background 0.2s, transform 0.15s; }
                    .fi-ta-row:hover { background: rgba(99,102,241,0.04) !important; transform: translateX(2px); }

                    .fi-wi-stats-overview-stat {
                        border-radius: 12px !important;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
                        transition: transform 0.2s, box-shadow 0.2s;
                        animation: fadeSlideUp 0.4s ease both;
                    }
                    .fi-wi-stats-overview-stat:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
                    }

                    .fi-section { border-radius: 12px !important; transition: box-shadow 0.2s; }
                    .fi-section:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.04); }

                    .fi-sidebar-nav { font-size: 14px; }
                    .fi-sidebar-item-button { transition: background 0.15s, padding-left 0.15s !important; }
                    .fi-sidebar-item-button:hover { padding-left: 4px !important; }

                    .fi-badge { font-weight: 500 !important; animation: popIn 0.25s ease both; }
                    .fi-btn { transition: transform 0.1s, box-shadow 0.15s !important; }
                    .fi-btn:active { transform: scale(0.96) !important; }

                    .fi-header-heading { font-weight: 700 !important; letter-spacing: -0.02em; }
                    .fi-ta-header-cell { font-weight: 600 !important; color: rgba(0,0,0,0.6) !important; font-size: 12px !important; text-transform: uppercase; letter-spacing: 0.05em; }

                    .fi-modal-window { animation: modalIn 0.25s cubic-bezier(0.16,1,0.3,1) both !important; }
                    .fi-notification { animation: slideInRight 0.3s cubic-bezier(0.16,1,0.3,1) both !important; }

                    @keyframes fadeSlideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
                    @keyframes popIn { from { opacity:0; transform:scale(0.8); } to { opacity:1; transform:scale(1); } }
                    @keyframes modalIn { from { opacity:0; transform:scale(0.95) translateY(8px); } to { opacity:1; transform:scale(1) translateY(0); } }
                    @keyframes slideInRight { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
                </style>
            '));
    }
}
