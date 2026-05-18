<?php

use App\Http\Controllers\Admin\AgentSiteController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingRuleController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginSettingController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RedeemCodeController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StorageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:5,1');

Route::middleware(['auth', 'role:admin,agent'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::get('commissions', [CommissionController::class, 'index'])->name('commissions.index');

    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
});

// 仅 admin 可访问的路由
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('redeem-codes', [RedeemCodeController::class, 'index'])->name('redeem-codes.index');
    Route::get('redeem-codes/generate', [RedeemCodeController::class, 'showGenerate'])->name('redeem-codes.generate');
    Route::post('redeem-codes/generate', [RedeemCodeController::class, 'generate'])->name('redeem-codes.generate.submit');
    Route::post('redeem-codes/{redeemCode}/disable', [RedeemCodeController::class, 'disable'])->name('redeem-codes.disable');
    Route::get('redeem-codes/export', [RedeemCodeController::class, 'export'])->name('redeem-codes.export');

    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');

    Route::get('login-settings', [LoginSettingController::class, 'index'])->name('login-settings.index');
    Route::post('login-settings', [LoginSettingController::class, 'update'])->name('login-settings.update');

    Route::get('storage', [StorageController::class, 'index'])->name('storage.index');
    Route::post('storage', [StorageController::class, 'update'])->name('storage.update');
    Route::post('storage/test', [StorageController::class, 'test'])->name('storage.test');

    Route::get('billing-rules', [BillingRuleController::class, 'index'])->name('billing-rules.index');
    Route::get('billing-rules/create', [BillingRuleController::class, 'create'])->name('billing-rules.create');
    Route::post('billing-rules', [BillingRuleController::class, 'store'])->name('billing-rules.store');
    Route::get('billing-rules/{billingRule}/edit', [BillingRuleController::class, 'edit'])->name('billing-rules.edit');
    Route::put('billing-rules/{billingRule}', [BillingRuleController::class, 'update'])->name('billing-rules.update');
    Route::delete('billing-rules/{billingRule}', [BillingRuleController::class, 'destroy'])->name('billing-rules.destroy');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->name('announcements.toggle');

    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

    Route::post('withdrawals/{withdrawalRequest}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('withdrawals/{withdrawalRequest}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');

    Route::get('agent-sites', [AgentSiteController::class, 'index'])->name('agent-sites.index');
    Route::get('agent-sites/levels', [AgentSiteController::class, 'levels'])->name('agent-sites.levels');
    Route::post('agent-sites/levels', [AgentSiteController::class, 'levelStore'])->name('agent-sites.levels.store');
    Route::put('agent-sites/levels/{agentLevel}', [AgentSiteController::class, 'levelUpdate'])->name('agent-sites.levels.update');
    Route::delete('agent-sites/levels/{agentLevel}', [AgentSiteController::class, 'levelDestroy'])->name('agent-sites.levels.destroy');
    Route::post('agent-sites/batch', [AgentSiteController::class, 'batch'])->name('agent-sites.batch');
    Route::get('agent-sites/{agentSite}', [AgentSiteController::class, 'show'])->name('agent-sites.show');
    Route::get('agent-sites/{agentSite}/edit', [AgentSiteController::class, 'edit'])->name('agent-sites.edit');
    Route::put('agent-sites/{agentSite}', [AgentSiteController::class, 'update'])->name('agent-sites.update');
    Route::post('agent-sites/{agentSite}/toggle', [AgentSiteController::class, 'toggle'])->name('agent-sites.toggle');
    Route::post('agent-sites/{agentSite}/approve', [AgentSiteController::class, 'approve'])->name('agent-sites.approve');
    Route::post('agent-sites/{agentSite}/reject', [AgentSiteController::class, 'reject'])->name('agent-sites.reject');
    Route::delete('agent-sites/{agentSite}', [AgentSiteController::class, 'destroy'])->name('agent-sites.destroy');
});
