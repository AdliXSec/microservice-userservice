<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

// === Route PUBLIK (tanpa login) ===
Route::apiResource('orders', OrderController::class)->only(['index', 'show']);

// === Route PROTECTED (harus login via User Service) ===
Route::middleware('verify.login')->group(function () {
    Route::apiResource('orders', OrderController::class)->only(['store', 'update', 'destroy']);
});
