<?php

use App\Http\Controllers\Agent\AuthController;
use App\Http\Controllers\Agent\DashboardController;
use App\Http\Controllers\Agent\SubUserController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth', 'role:agent,admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('sub-users', [SubUserController::class, 'index'])->name('sub-users');
    Route::post('sub-users/{user}/recharge', [SubUserController::class, 'recharge'])->name('sub-users.recharge');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
