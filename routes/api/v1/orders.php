<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Orders, Escrow & Handover Settlement Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'myOrders']);
    Route::get('{id}', [OrderController::class, 'show']);
    Route::get('{id}/messages', [OrderController::class, 'messages']);
    Route::post('{id}/messages', [OrderController::class, 'sendMessage']);
    Route::post('{id}/arrangement', [OrderController::class, 'submitArrangement']);

    // Handover Code Release & Buyer Receipt Confirmation (KYC Required)
    Route::middleware('kyc.verified')->group(function () {
        Route::post('{id}/confirm-handover', [OrderController::class, 'confirmHandover'])->middleware('idempotency');
        Route::post('{id}/mark-received', [OrderController::class, 'markReceived'])->middleware('idempotency');
    });
});
