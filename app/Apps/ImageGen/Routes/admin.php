<?php

use App\Apps\ImageGen\Controllers\Admin\ChannelController;
use App\Apps\ImageGen\Controllers\Admin\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('tasks',                        [TaskController::class, 'index'])->name('tasks');
Route::get('tasks/{task_id}',              [TaskController::class, 'show'])->name('tasks.show')->where('task_id', '[a-f0-9]{32}');
Route::post('tasks/{task_id}/retry',       [TaskController::class, 'retry'])->name('tasks.retry')->where('task_id', '[a-f0-9]{32}');
Route::post('tasks/{task_id}/refund',      [TaskController::class, 'refund'])->name('tasks.refund')->where('task_id', '[a-f0-9]{32}');
Route::post('tasks/{task_id}/force-fail',  [TaskController::class, 'forceFail'])->name('tasks.force-fail')->where('task_id', '[a-f0-9]{32}');

Route::get('channels', [ChannelController::class, 'index'])->name('channels');
Route::get('channels/create', [ChannelController::class, 'create'])->name('channels.create');
Route::post('channels', [ChannelController::class, 'store'])->name('channels.store');
Route::get('channels/{channel}/edit', [ChannelController::class, 'edit'])->name('channels.edit');
Route::put('channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
Route::post('channels/{channel}/toggle', [ChannelController::class, 'toggleStatus'])->name('channels.toggle');

