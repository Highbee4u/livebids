<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\KycController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity & KYC Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('kyc')->group(function () {
    Route::post('submit', [KycController::class, 'submit']);
    Route::get('status', [KycController::class, 'status']);
});
