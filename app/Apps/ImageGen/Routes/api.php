<?php

use App\Apps\ImageGen\Controllers\Api\GenerateController;
use App\Apps\ImageGen\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('generate', [GenerateController::class, 'submit'])->name('generate');
    Route::get('status', [GenerateController::class, 'status'])->name('status');
    Route::post('proxy', [ProxyController::class, 'handle'])->name('proxy');
});
