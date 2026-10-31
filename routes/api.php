<?php

use App\Http\Controllers\Api\V1\Admin\AdminAuctionController;
use App\Http\Controllers\Api\V1\Admin\AdminDisputeController;
use App\Http\Controllers\Api\V1\Admin\AdminKycController;
use App\Http\Controllers\Api\V1\Admin\AdminLedgerController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuctionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\BidController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TopupController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LiveBids API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------------
    // Public Endpoints
    // ---------------------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('otp/request', [AuthController::class, 'requestOtp']);
        Route::post('otp/verify', [AuthController::class, 'verifyOtp']);
    });

    Route::get('users/{id}/public', [ProfileController::class, 'publicProfile']);

    // Feeds & Discovery
    Route::get('feed/upcoming', [FeedController::class, 'upcoming']);
    Route::get('feed/live', [FeedController::class, 'live']);
    Route::get('categories', [FeedController::class, 'categories']);

    // Public Auction Details & Leaderboard Snapshot
    Route::get('auctions/{id}', [AuctionController::class, 'show']);
    Route::get('auctions/{id}/bids', [BidController::class, 'leaderboard']);

    // Public payment webhook (HMAC signature-verified inside controller)
    Route::post('webhooks/paystack', [PaymentWebhookController::class, 'handlePaystack']);

    // ---------------------------------------------------------------------
    // Authenticated Endpoints
    // ---------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Session & Profile
        Route::prefix('auth')->group(function () {
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        Route::get('me', [ProfileController::class, 'me']);
        Route::patch('me', [ProfileController::class, 'update']);

        // Identity & KYC (NIN + Liveness Verification)
        Route::prefix('kyc')->group(function () {
            Route::post('submit', [KycController::class, 'submit']);
            Route::get('status', [KycController::class, 'status']);
        });

        // Reminders & Live Stream Join
        Route::post('auctions/{id}/remind', [AuctionController::class, 'toggleReminder']);
        Route::post('auctions/{id}/live/join', [AuctionController::class, 'joinLive']);

        // Banks lookup
        Route::get('banks', [BankController::class, 'listBanks']);

        // Orders List & Details
        Route::get('orders', [OrderController::class, 'myOrders']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::get('orders/{id}/messages', [OrderController::class, 'messages']);
        Route::post('orders/{id}/messages', [OrderController::class, 'sendMessage']);
        Route::post('orders/{id}/arrangement', [OrderController::class, 'submitArrangement']);

        // -----------------------------------------------------------------
        // KYC-Gated Endpoints (Mandatory NIN Verification Required)
        // -----------------------------------------------------------------
        Route::middleware('kyc.verified')->group(function () {

            // Auctions Seller Actions
            Route::prefix('auctions')->group(function () {
                Route::post('/', [AuctionController::class, 'create'])->middleware('idempotency');
                Route::post('{id}/live/start', [AuctionController::class, 'startLive']);
                Route::post('{id}/select-winner', [AuctionController::class, 'selectWinner'])->middleware('idempotency');
                Route::post('{id}/end-unsold', [AuctionController::class, 'endUnsold']);
                Route::post('{id}/bids', [BidController::class, 'placeBid']);
            });

            // Orders Handover & Escrow Release
            Route::prefix('orders')->group(function () {
                Route::post('{id}/confirm-handover', [OrderController::class, 'confirmHandover'])->middleware('idempotency');
                Route::post('{id}/mark-received', [OrderController::class, 'markReceived'])->middleware('idempotency');
            });

            // Wallet & Ledger
            Route::prefix('wallet')->group(function () {
                Route::get('/', [WalletController::class, 'balance']);
                Route::get('transactions', [WalletController::class, 'transactions']);

                // Top-ups
                Route::post('topups', [TopupController::class, 'initialize'])->middleware('idempotency');
                Route::get('topups/{reference}/verify', [TopupController::class, 'verify']);

                // Withdrawals
                Route::post('withdrawals', [WithdrawalController::class, 'requestWithdrawal'])->middleware('idempotency');
                Route::get('withdrawals', [WithdrawalController::class, 'history']);
            });

            // Bank Accounts
            Route::prefix('bank-accounts')->group(function () {
                Route::get('/', [BankController::class, 'myAccounts']);
                Route::post('verify', [BankController::class, 'verifyAccount'])->middleware('idempotency');
            });
        });

        // -----------------------------------------------------------------
        // Admin Back-Office Endpoints (Guarded by Role: Admin)
        // -----------------------------------------------------------------
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            // KYC & NIN Photo Review (persisted provider photos)
            Route::get('kyc', [AdminKycController::class, 'index']);
            Route::get('kyc/{id}/nin-photo', [AdminKycController::class, 'viewNinPhoto']);

            // Disputes Queue & Settlement
            Route::get('disputes', [AdminDisputeController::class, 'index']);
            Route::post('disputes/{id}/resolve', [AdminDisputeController::class, 'resolve']);

            // Ledger Maker-Checker Adjustments & Reconciliation
            Route::post('ledger/propose-adjustment', [AdminLedgerController::class, 'proposeAdjustment']);
            Route::post('ledger/adjustments/{id}/approve', [AdminLedgerController::class, 'approveAdjustment']);
            Route::get('ledger/reconcile', [AdminLedgerController::class, 'runReconciliation']);
            Route::get('audit-logs', [AdminLedgerController::class, 'auditLogs']);

            // Live Stream Emergency Kill-Switch
            Route::post('auctions/{id}/kill-stream', [AdminAuctionController::class, 'killLiveStream']);

            // User Governance (Strikes, Suspensions, Bans)
            Route::post('users/{id}/strike', [AdminUserController::class, 'strike']);
            Route::post('users/{id}/suspend', [AdminUserController::class, 'suspend']);
            Route::post('users/{id}/ban', [AdminUserController::class, 'ban']);
        });
    });
});
