<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LiveBids API Routes
|--------------------------------------------------------------------------
|
| Versioned API routes aggregated from domain-specific route definitions
| in routes/api/v1/.
|
*/

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/discovery.php';
    require __DIR__.'/api/v1/kyc.php';
    require __DIR__.'/api/v1/auctions.php';
    require __DIR__.'/api/v1/orders.php';
    require __DIR__.'/api/v1/wallet.php';
    require __DIR__.'/api/v1/webhooks.php';
    require __DIR__.'/api/v1/admin.php';
});
