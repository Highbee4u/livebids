<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment Webhooks Routes
|--------------------------------------------------------------------------
|
| HMAC signatures verified inside PaymentWebhookController.
|
*/

Route::prefix('webhooks')->group(function () {
    Route::post('paystack', [PaymentWebhookController::class, 'handlePaystack']);
});
