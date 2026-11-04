<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\TopupController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet, Ledger, Topups, Withdrawals & Banks Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'onboarded'])->group(function () {
    // Bank Institutions Lookup
    Route::get('banks', [BankController::class, 'listBanks']);

    // KYC-Gated Financial Operations
    Route::middleware('kyc.verified')->group(function () {
        // Wallet Balances & Ledger Statement
        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'balance']);
            Route::get('transactions', [WalletController::class, 'transactions']);

            // Top-ups (Paystack)
            Route::post('topups', [TopupController::class, 'initialize'])->middleware('idempotency');
            Route::get('topups/{reference}/verify', [TopupController::class, 'verify']);

            // Withdrawals
            Route::post('withdrawals', [WithdrawalController::class, 'requestWithdrawal'])->middleware('idempotency');
            Route::get('withdrawals', [WithdrawalController::class, 'history']);
        });

        // Bank Accounts (NUBAN Verification with KYC Name Matching)
        Route::prefix('bank-accounts')->group(function () {
            Route::get('/', [BankController::class, 'myAccounts']);
            Route::post('verify', [BankController::class, 'verifyAccount'])->middleware('idempotency');
        });
    });
});
