<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;

// === Route PUBLIK (tanpa login) ===
Route::get('/orders', [OrderController::class, 'index']);        // GET semua order
Route::get('/orders/{id}', [OrderController::class, 'show']);     // GET detail order

// === Route PROTECTED (harus login via User Service) ===
Route::middleware('verify.login')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);        // POST buat order baru
    Route::put('/orders/{id}', [OrderController::class, 'update']);   // PUT update order
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']); // DELETE hapus order
});
