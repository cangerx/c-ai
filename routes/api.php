<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RedeemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::get('/config', function () {
    $announcements = \App\Models\Announcement::where('enabled', true)
        ->orderBy('sort')->orderByDesc('id')
        ->pluck('content', 'url')
        ->map(fn($content, $url) => $url ? "{$content} · <a href='{$url}' target='_blank'>了解更多 →</a>" : $content)
        ->values()->all();
    return response()->json([
        'prompt_tool_model' => \App\Models\SiteSetting::get('prompt_tool_model', 'gpt-5.4-mini'),
        'announcements' => $announcements,
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/github', [AuthController::class, 'githubRedirect']);
Route::get('/auth/github/callback', [AuthController::class, 'githubCallback']);

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
