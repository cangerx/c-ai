<?php

namespace App\Apps\ImageGen;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ImageGenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
    }

    protected function loadRoutes(): void
    {
        $routesDir = __DIR__ . '/Routes';

        if (file_exists($routesDir . '/admin.php')) {
            Route::middleware(['web', 'auth', 'role:admin,agent'])
                ->prefix('admin/apps/image-gen')
                ->name('admin.image-gen.')
                ->group($routesDir . '/admin.php');
        }

        if (file_exists($routesDir . '/api.php')) {
            Route::middleware(['api', \App\Http\Middleware\CorsMiddleware::class])
                ->prefix('api/apps/image-gen')
                ->name('api.image-gen.')
                ->group($routesDir . '/api.php');
        }
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Views', 'image-gen');
    }
}
