<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // 安装向导 — 无中间件，不依赖数据库
            Route::get('/install', [\App\Http\Controllers\InstallController::class, 'index']);
            Route::get('/install/step2', [\App\Http\Controllers\InstallController::class, 'step2']);
            Route::post('/install/test-db', [\App\Http\Controllers\InstallController::class, 'testDb']);
            Route::post('/install', [\App\Http\Controllers\InstallController::class, 'run']);

            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('web')
                ->prefix('agent')
                ->name('agent.')
                ->group(base_path('routes/agent.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\ResolveAgentSite::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson()) {
                return null;
            }
            if (str_starts_with($request->path(), 'admin')) {
                return '/admin/login';
            }
            if (str_starts_with($request->path(), 'agent')) {
                return '/agent/login';
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
