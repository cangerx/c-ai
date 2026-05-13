<?php

namespace App\Providers;

use App\Services\AppLoader;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppLoader::class);

        $loader = $this->app->make(AppLoader::class);
        foreach ($loader->getProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $loader = $this->app->make(AppLoader::class);
        View::share('appMenuGroups', $loader->getMenuItems());
    }
}
