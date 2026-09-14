<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MerchantDashboardController;
use App\Http\Controllers\Api\UsageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/usage', [UsageController::class, 'store'])->middleware('throttle:usage');
Route::get('/merchants/{id}/dashboard', [MerchantDashboardController::class, 'show']);
