<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Onboarding & Registration Routes (Staging Table)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [OnboardingController::class, 'register']);
    Route::post('register/verify-otp', [OnboardingController::class, 'verifyOtp']);
    Route::post('onboarding/otp/verify', [OnboardingController::class, 'verifyOtp']);
    Route::post('profile/setup', [OnboardingController::class, 'completeProfile']);
});
