<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminAuctionController;
use App\Http\Controllers\Api\V1\Admin\AdminDisputeController;
use App\Http\Controllers\Api\V1\Admin\AdminKycController;
use App\Http\Controllers\Api\V1\Admin\AdminLedgerController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Back-Office Operations Routes
|--------------------------------------------------------------------------
|
| Guarded by auth:sanctum and role:admin.
|
*/

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // KYC Review & Persisted Provider NIN Photo Inspection (Audit Logged)
    Route::get('kyc', [AdminKycController::class, 'index']);
    Route::get('kyc/{id}/nin-photo', [AdminKycController::class, 'viewNinPhoto']);

    // Disputes Management & Escrow Split/Release Resolutions
    Route::get('disputes', [AdminDisputeController::class, 'index']);
    Route::post('disputes/{id}/resolve', [AdminDisputeController::class, 'resolve']);

    // Ledger Maker-Checker Dual-Approver Controls & Reconciliation
    Route::post('ledger/propose-adjustment', [AdminLedgerController::class, 'proposeAdjustment']);
    Route::post('ledger/adjustments/{id}/approve', [AdminLedgerController::class, 'approveAdjustment']);
    Route::get('ledger/reconcile', [AdminLedgerController::class, 'runReconciliation']);
    Route::get('audit-logs', [AdminLedgerController::class, 'auditLogs']);

    // Emergency Live Stream Kill-Switch
    Route::post('auctions/{id}/kill-stream', [AdminAuctionController::class, 'killLiveStream']);

    // Trust & Safety Governance (Strikes, Suspensions, Bans)
    Route::post('users/{id}/strike', [AdminUserController::class, 'strike']);
    Route::post('users/{id}/suspend', [AdminUserController::class, 'suspend']);
    Route::post('users/{id}/ban', [AdminUserController::class, 'ban']);
});
