<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingRuleController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RedeemCodeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StorageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.submit');

Route::middleware(['auth', 'role:admin,agent'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::get('redeem-codes', [RedeemCodeController::class, 'index'])->name('redeem-codes.index');
    Route::get('redeem-codes/generate', [RedeemCodeController::class, 'showGenerate'])->name('redeem-codes.generate');
    Route::post('redeem-codes/generate', [RedeemCodeController::class, 'generate'])->name('redeem-codes.generate.submit');
    Route::post('redeem-codes/{redeemCode}/disable', [RedeemCodeController::class, 'disable'])->name('redeem-codes.disable');
    Route::get('redeem-codes/export', [RedeemCodeController::class, 'export'])->name('redeem-codes.export');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('storage', [StorageController::class, 'index'])->name('storage.index');
    Route::post('storage', [StorageController::class, 'update'])->name('storage.update');
    Route::post('storage/test', [StorageController::class, 'test'])->name('storage.test');

    Route::get('billing-rules', [BillingRuleController::class, 'index'])->name('billing-rules.index');
    Route::get('billing-rules/create', [BillingRuleController::class, 'create'])->name('billing-rules.create');
    Route::post('billing-rules', [BillingRuleController::class, 'store'])->name('billing-rules.store');
    Route::get('billing-rules/{billingRule}/edit', [BillingRuleController::class, 'edit'])->name('billing-rules.edit');
    Route::put('billing-rules/{billingRule}', [BillingRuleController::class, 'update'])->name('billing-rules.update');
    Route::delete('billing-rules/{billingRule}', [BillingRuleController::class, 'destroy'])->name('billing-rules.destroy');

    Route::get('commissions', [CommissionController::class, 'index'])->name('commissions.index');

    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::post('withdrawals/{withdrawalRequest}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve')->middleware('role:admin');
    Route::post('withdrawals/{withdrawalRequest}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject')->middleware('role:admin');
});
