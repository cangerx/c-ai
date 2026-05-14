<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// 首页：直接返回 public/index.html，不做 Laravel 重构
Route::get('/', function () {
    $path = public_path('index.html');
    if (!is_file($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::get('/explore', [\App\Apps\ImageGen\Controllers\GalleryController::class, 'index'])->name('explore');

// 认证路由
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 用户中心
Route::middleware('auth')->prefix('user')->name('user.')->group(function () {
    Route::get('/', [UserController::class, 'dashboard'])->name('dashboard');
    Route::get('/wallet', [UserController::class, 'wallet'])->name('wallet');
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::get('/usage', [UserController::class, 'usageHistory'])->name('usage');
    Route::get('/redeem', [UserController::class, 'redeemPage'])->name('redeem');
    Route::post('/redeem', [UserController::class, 'redeem'])->name('redeem.submit');
});
