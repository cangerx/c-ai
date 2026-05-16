<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\SubSiteController;
use Illuminate\Support\Facades\Route;

// 首页：直接返回 public/index.html，不做 Laravel 重构
Route::get('/', function () {
    if (!file_exists(storage_path('installed'))) {
        return redirect('/install');
    }
    // 分站域名访问
    if (app()->bound('agent_site')) {
        return app(SubSiteController::class)->index();
    }
    $path = public_path('index.html');
    if (!is_file($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/terms', fn () => response()->file(public_path('terms.html')));
Route::get('/privacy', fn () => response()->file(public_path('privacy.html')));
Route::get('/reset-password', fn () => view('auth.reset-password'));

Route::get('/explore', [\App\Apps\ImageGen\Controllers\GalleryController::class, 'index'])->name('explore');
Route::get('/pricing', function () {
    if (app()->bound('agent_site')) {
        return app(SubSiteController::class)->pricing();
    }
    return app(\App\Http\Controllers\PricingController::class)->index();
})->name('pricing');

// 认证路由
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 分站路径访问
Route::get('/s/{slug}', [SubSiteController::class, 'index']);
Route::get('/s/{slug}/pricing', [SubSiteController::class, 'pricing']);
