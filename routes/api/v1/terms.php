<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TermsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Terms & Conditions Routes
|--------------------------------------------------------------------------
*/

// Authenticated User Consent & Compliance (registered first to avoid collision with {type})
Route::middleware('auth:sanctum')->prefix('terms')->group(function (): void {
    Route::get('pending', [TermsController::class, 'pending']);
    Route::get('my-acceptances', [TermsController::class, 'myAcceptances']);
    Route::post('accept', [TermsController::class, 'accept']);
});

// Public Terms Reading
Route::prefix('terms')->group(function (): void {
    Route::get('/', [TermsController::class, 'index']);
    Route::get('{type}', [TermsController::class, 'show'])->where('type', '^[a-z_]+$');
    Route::get('{type}/versions', [TermsController::class, 'versions'])->where('type', '^[a-z_]+$');
});
