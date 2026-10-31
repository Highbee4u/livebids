<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Contracts;

use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\Wallet;
use App\Models\User;

interface LedgerServiceInterface
{
    public function getOrCreateWallet(User $user): Wallet;

    public function creditTopup(User $user, int $amountKobo, string $paymentRef, string $idempotencyKey): void;

    public function lockFunds(User $user, int $amountKobo, string $auctionId, string $bidId, string $idempotencyKey): void;

    public function unlockFunds(User $user, int $amountKobo, string $auctionId, string $bidId, string $idempotencyKey): void;

    public function moveLockedToEscrow(User $buyer, int $amountKobo, string $orderId, string $bidId, string $idempotencyKey): void;

    public function releaseEscrow(User $seller, int $grossAmountKobo, int $commissionKobo, string $orderId, string $idempotencyKey): void;

    public function refundEscrow(User $buyer, int $amountKobo, string $orderId, string $idempotencyKey): void;

    public function partialRefundAndSplit(
        User $buyer,
        int $buyerAmountKobo,
        User $seller,
        int $sellerAmountKobo,
        int $commissionKobo,
        string $orderId,
        string $idempotencyKey
    ): void;

    public function holdForWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void;

    public function completeWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void;

    public function failWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void;

    public function manualAdjustment(
        User $targetUser,
        LedgerAccount $account,
        LedgerDirection $direction,
        int $amountKobo,
        string $reason,
        string $idempotencyKey
    ): void;
}
