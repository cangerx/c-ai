<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RedeemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::get('/config', function () {
    return response()->json([
        'prompt_tool_model' => \App\Models\SiteSetting::get('prompt_tool_model', 'gpt-5.4-mini'),
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/redeem', [RedeemController::class, 'redeem']);

    Route::put('/me', [UserController::class, 'updateMe']);
    Route::get('/usage', [UserController::class, 'usage']);
    Route::get('/tasks', [UserController::class, 'tasks']);
    Route::delete('/tasks/{taskId}', [UserController::class, 'deleteTask']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
