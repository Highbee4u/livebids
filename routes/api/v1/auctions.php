<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuctionController;
use App\Http\Controllers\Api\V1\BidController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auction & Bidding Routes
|--------------------------------------------------------------------------
*/

// Public Auction Endpoints
Route::prefix('auctions')->group(function () {
    Route::get('{id}', [AuctionController::class, 'show']);
    Route::get('{id}/bids', [BidController::class, 'leaderboard']);
});

// Authenticated Live Stream Participation & Reminders (Must be onboarded)
Route::middleware(['auth:sanctum', 'onboarded'])->prefix('auctions')->group(function () {
    Route::post('{id}/remind', [AuctionController::class, 'toggleReminder']);
    Route::post('{id}/live/join', [AuctionController::class, 'joinLive']);

    // KYC-Gated Seller & Bidding Operations
    Route::middleware('kyc.verified')->group(function () {
        Route::post('/', [AuctionController::class, 'create'])->middleware('idempotency');
        Route::post('{id}/live/start', [AuctionController::class, 'startLive']);
        Route::post('{id}/select-winner', [AuctionController::class, 'selectWinner'])->middleware('idempotency');
        Route::post('{id}/end-unsold', [AuctionController::class, 'endUnsold']);
        Route::post('{id}/bids', [BidController::class, 'placeBid']);
    });
});
