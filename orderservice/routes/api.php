<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Semua route di bawah harus LOGIN terlebih dahulu
Route::middleware('verify.login')->group(function () {

    // --- AKSES USER & ADMIN ---
    // User dan Admin bisa melihat daftar order, detail, dan membuat pesanan
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders', [OrderController::class, 'store']);

    // --- KHUSUS AKSES ADMIN ---
    // Hanya Admin yang bisa mengubah status, mengedit data, atau menghapus order
    Route::middleware('check.role')->group(function () {
        Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::put('orders/{id}', [OrderController::class, 'update']);
        Route::delete('orders/{id}', [OrderController::class, 'destroy']);
    });

});
