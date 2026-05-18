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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Cang AI 管理后台')
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->navigationItems([])
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
                    /* 表格行 hover */
                    .fi-ta-row { transition: background 0.2s; }
                    .fi-ta-row:hover { background: rgba(59,130,246,0.04) !important; }

                    /* 统计卡片 */
                    .fi-wi-stats-overview-stat {
                        border-radius: 12px !important;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
                        transition: box-shadow 0.2s;
                    }
                    .fi-wi-stats-overview-stat:hover {
                        box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
                    }

                    /* Section 卡片 */
                    .fi-section {
                        border-radius: 12px !important;
                        transition: box-shadow 0.2s;
                    }
                    .fi-section:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.04); }

                    /* 侧边栏导航项 */
                    .fi-sidebar-nav { font-size: 14px; }

                    /* Badge */
                    .fi-badge {
                        font-weight: 500 !important;
                    }

                    /* 按钮交互 */
                    .fi-btn { transition: box-shadow 0.15s !important; }

                    /* 页面标题 */
                    .fi-header-heading { font-weight: 700 !important; letter-spacing: -0.02em; }
                    .fi-ta-header-cell { font-weight: 600 !important; color: rgba(0,0,0,0.6) !important; font-size: 12px !important; text-transform: uppercase; letter-spacing: 0.05em; }
                    .fi-pagination { display: flex !important; justify-content: flex-end !important; align-items: center; gap: 12px; }

                    /* Modal 弹窗动画 */
                    .fi-modal-window {
                        animation: modalIn 0.25s cubic-bezier(0.16,1,0.3,1) both !important;
                    }

                    /* 通知 toast 滑入 */
                    .fi-notification {
                        animation: slideInRight 0.3s cubic-bezier(0.16,1,0.3,1) both !important;
                    }

                    @keyframes modalIn {
                        from { opacity: 0; transform: scale(0.95) translateY(8px); }
                        to { opacity: 1; transform: scale(1) translateY(0); }
                    }
                    @keyframes slideInRight {
                        from { opacity: 0; transform: translateX(20px); }
                        to { opacity: 1; transform: translateX(0); }
                    }
                </style>
            '))
            ->renderHook('panels::body.end', fn () => new HtmlString('
                <div id="gt-lightbox" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;cursor:zoom-out;">
                    <img id="gt-lightbox-img" style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:8px;" />
                </div>
                <script>
                    document.addEventListener("click", function(e) {
                        if (e.target.classList.contains("lightbox-img")) {
                            var lb = document.getElementById("gt-lightbox");
                            document.getElementById("gt-lightbox-img").src = e.target.src;
                            lb.style.display = "flex";
                        }
                    });
                    document.getElementById("gt-lightbox").addEventListener("click", function() {
                        this.style.display = "none";
                    });
                    document.addEventListener("keydown", function(e) {
                        if (e.key === "Escape") document.getElementById("gt-lightbox").style.display = "none";
                    });
                </script>
            '));
    }
}
