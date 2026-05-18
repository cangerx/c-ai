<?php

// Legacy agent routes — replaced by Filament Agent panel at /agent
// Kept commented for reference during migration.

/*
use App\Http\Controllers\Agent\AuthController;
use App\Http\Controllers\Agent\DashboardController;
use App\Http\Controllers\Agent\PlanController;
use App\Http\Controllers\Agent\RechargeController;
use App\Http\Controllers\Agent\RedeemCodeController;
use App\Http\Controllers\Agent\SiteSettingController;
use App\Http\Controllers\Agent\StatisticsController;
use App\Http\Controllers\Agent\SubUserController;
use App\Http\Controllers\Agent\TransactionController;
use App\Http\Controllers\Agent\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login']);

Route::middleware(['auth', 'role:agent,admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics');

    Route::get('sub-users', [SubUserController::class, 'index'])->name('sub-users');
    Route::post('sub-users/{user}/recharge', [SubUserController::class, 'recharge'])->name('sub-users.recharge');

    Route::post('recharge', [RechargeController::class, 'redeem'])->name('recharge');

    Route::get('redeem-codes', [RedeemCodeController::class, 'index'])->name('redeem-codes');
    Route::get('redeem-codes/generate', [RedeemCodeController::class, 'showGenerate'])->name('redeem-codes.generate');
    Route::post('redeem-codes/generate', [RedeemCodeController::class, 'generate'])->name('redeem-codes.generate.submit');
    Route::post('redeem-codes/{redeemCode}/disable', [RedeemCodeController::class, 'disable'])->name('redeem-codes.disable');
    Route::get('redeem-codes/export', [RedeemCodeController::class, 'export'])->name('redeem-codes.export');

    Route::get('plans', [PlanController::class, 'index'])->name('plans');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{agentPlan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{agentPlan}', [PlanController::class, 'destroy'])->name('plans.destroy');

    Route::get('site-settings', [SiteSettingController::class, 'edit'])->name('site-settings');
    Route::put('site-settings', [SiteSettingController::class, 'update'])->name('site-settings.update');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions');

    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals');
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
*/
