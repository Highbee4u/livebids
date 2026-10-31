<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Contracts\LedgerServiceInterface;
use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\Wallet;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LedgerService implements LedgerServiceInterface
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'locked_balance' => 0,
                'currency' => 'NGN',
            ]
        );
    }

    /**
     * Credit wallet from a successful top-up.
     * Double-entry:
     *   DEBIT  provider_clearing (system)
     *   CREDIT available         (user)
     */
    public function creditTopup(User $user, int $amountKobo, string $paymentRef, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($user);

        $entries = [
            [
                'wallet_id' => null,
                'account' => LedgerAccount::ProviderClearing,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'topup',
                'ref_id' => $paymentRef,
                'description' => "Clearing funds for topup {$paymentRef}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'topup',
                'ref_id' => $paymentRef,
                'description' => "Wallet topup credited via Paystack ref {$paymentRef}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Lock funds when placing or raising a bid.
     * Double-entry:
     *   DEBIT  available (user)
     *   CREDIT locked    (user)
     */
    public function lockFunds(User $user, int $amountKobo, string $auctionId, string $bidId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($user);

        $entries = [
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'bid_lock',
                'ref_id' => $bidId,
                'description' => 'Locked ₦'.number_format($amountKobo / 100, 2)." for bid on auction #{$auctionId}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Locked,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'bid_lock',
                'ref_id' => $bidId,
                'description' => "Bid hold reserved for auction #{$auctionId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Unlock funds when an auction ends without this user winning.
     * Double-entry:
     *   DEBIT  locked    (user)
     *   CREDIT available (user)
     */
    public function unlockFunds(User $user, int $amountKobo, string $auctionId, string $bidId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($user);

        $entries = [
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Locked,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'bid_unlock',
                'ref_id' => $bidId,
                'description' => "Released bid hold for auction #{$auctionId}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'bid_unlock',
                'ref_id' => $bidId,
                'description' => "Restored available balance from auction #{$auctionId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Move winning bidder's locked funds into system escrow upon auction close.
     * Double-entry:
     *   DEBIT  locked (buyer)
     *   CREDIT escrow (system)
     */
    public function moveLockedToEscrow(User $buyer, int $amountKobo, string $orderId, string $bidId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($buyer);

        $entries = [
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Locked,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'escrow_hold',
                'ref_id' => $orderId,
                'description' => "Moved winning bid from locked to escrow for Order #{$orderId}",
            ],
            [
                'wallet_id' => null,
                'account' => LedgerAccount::Escrow,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'escrow_hold',
                'ref_id' => $orderId,
                'description' => "Escrow secured for Order #{$orderId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Release escrow to seller upon successful handover confirmation.
     * Double-entry:
     *   DEBIT  escrow           (system) : gross amount
     *   CREDIT available        (seller) : gross - commission
     *   CREDIT platform_revenue (system) : commission
     */
    public function releaseEscrow(User $seller, int $grossAmountKobo, int $commissionKobo, string $orderId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($seller);
        $netPayoutKobo = $grossAmountKobo - $commissionKobo;

        $entries = [
            [
                'wallet_id' => null,
                'account' => LedgerAccount::Escrow,
                'direction' => LedgerDirection::Debit,
                'amount' => $grossAmountKobo,
                'ref_type' => 'escrow_release',
                'ref_id' => $orderId,
                'description' => "Escrow released for completed Order #{$orderId}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $netPayoutKobo,
                'ref_type' => 'seller_payout',
                'ref_id' => $orderId,
                'description' => "Net proceeds for Order #{$orderId} after commission",
            ],
            [
                'wallet_id' => null,
                'account' => LedgerAccount::PlatformRevenue,
                'direction' => LedgerDirection::Credit,
                'amount' => $commissionKobo,
                'ref_type' => 'platform_commission',
                'ref_id' => $orderId,
                'description' => "Platform commission booked for Order #{$orderId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Full refund from escrow back to buyer (e.g. cancellation, seller ghosting, dispute won by buyer).
     * Double-entry:
     *   DEBIT  escrow    (system)
     *   CREDIT available (buyer)
     */
    public function refundEscrow(User $buyer, int $amountKobo, string $orderId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($buyer);

        $entries = [
            [
                'wallet_id' => null,
                'account' => LedgerAccount::Escrow,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'escrow_refund',
                'ref_id' => $orderId,
                'description' => "Escrow refunded for cancelled Order #{$orderId}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'escrow_refund',
                'ref_id' => $orderId,
                'description' => "Refund credited to wallet for Order #{$orderId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Partial split resolution of a dispute.
     */
    public function partialRefundAndSplit(
        User $buyer,
        int $buyerAmountKobo,
        User $seller,
        int $sellerAmountKobo,
        int $commissionKobo,
        string $orderId,
        string $idempotencyKey
    ): void {
        $buyerWallet = $this->getOrCreateWallet($buyer);
        $sellerWallet = $this->getOrCreateWallet($seller);
        $totalEscrow = $buyerAmountKobo + $sellerAmountKobo + $commissionKobo;

        $entries = [
            [
                'wallet_id' => null,
                'account' => LedgerAccount::Escrow,
                'direction' => LedgerDirection::Debit,
                'amount' => $totalEscrow,
                'ref_type' => 'dispute_split',
                'ref_id' => $orderId,
                'description' => "Escrow debited for dispute split resolution on Order #{$orderId}",
            ],
            [
                'wallet_id' => $buyerWallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $buyerAmountKobo,
                'ref_type' => 'dispute_buyer_refund',
                'ref_id' => $orderId,
                'description' => "Dispute settlement partial refund for Order #{$orderId}",
            ],
            [
                'wallet_id' => $sellerWallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $sellerAmountKobo,
                'ref_type' => 'dispute_seller_payout',
                'ref_id' => $orderId,
                'description' => "Dispute settlement partial payout for Order #{$orderId}",
            ],
        ];

        if ($commissionKobo > 0) {
            $entries[] = [
                'wallet_id' => null,
                'account' => LedgerAccount::PlatformRevenue,
                'direction' => LedgerDirection::Credit,
                'amount' => $commissionKobo,
                'ref_type' => 'dispute_commission',
                'ref_id' => $orderId,
                'description' => "Commission retained on dispute settlement for Order #{$orderId}",
            ];
        }

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Hold funds when user requests a bank withdrawal.
     * Double-entry:
     *   DEBIT  available         (user)
     *   CREDIT provider_clearing (system)
     */
    public function holdForWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($user);

        $entries = [
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'withdrawal_hold',
                'ref_id' => $withdrawalId,
                'description' => "Pending withdrawal request #{$withdrawalId}",
            ],
            [
                'wallet_id' => null,
                'account' => LedgerAccount::ProviderClearing,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'withdrawal_hold',
                'ref_id' => $withdrawalId,
                'description' => "Withdrawal funds in clearing for request #{$withdrawalId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Completed withdrawal: provider has confirmed transfer.
     */
    public function completeWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void
    {
        // Funds were already debited from available to clearing. Nothing further to debit/credit,
        // or record clearing settlement.
    }

    /**
     * Failed withdrawal: return funds back to user's available balance.
     */
    public function failWithdrawal(User $user, int $amountKobo, string $withdrawalId, string $idempotencyKey): void
    {
        $wallet = $this->getOrCreateWallet($user);

        $entries = [
            [
                'wallet_id' => null,
                'account' => LedgerAccount::ProviderClearing,
                'direction' => LedgerDirection::Debit,
                'amount' => $amountKobo,
                'ref_type' => 'withdrawal_refund',
                'ref_id' => $withdrawalId,
                'description' => "Clearing returned for failed withdrawal #{$withdrawalId}",
            ],
            [
                'wallet_id' => $wallet->id,
                'account' => LedgerAccount::Available,
                'direction' => LedgerDirection::Credit,
                'amount' => $amountKobo,
                'ref_type' => 'withdrawal_refund',
                'ref_id' => $withdrawalId,
                'description' => "Refund credited for failed withdrawal #{$withdrawalId}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Manual adjustment by admin console (requires two approvers at HTTP/Policy layer).
     */
    public function manualAdjustment(
        User $targetUser,
        LedgerAccount $account,
        LedgerDirection $direction,
        int $amountKobo,
        string $reason,
        string $idempotencyKey
    ): void {
        $wallet = $this->getOrCreateWallet($targetUser);

        // Counterpart account
        $counterAccount = LedgerAccount::PlatformRevenue;
        $counterDirection = $direction === LedgerDirection::Debit ? LedgerDirection::Credit : LedgerDirection::Debit;

        $entries = [
            [
                'wallet_id' => $wallet->id,
                'account' => $account,
                'direction' => $direction,
                'amount' => $amountKobo,
                'ref_type' => 'manual_adjustment',
                'ref_id' => (string) $targetUser->id,
                'description' => "Manual adjustment: {$reason}",
            ],
            [
                'wallet_id' => null,
                'account' => $counterAccount,
                'direction' => $counterDirection,
                'amount' => $amountKobo,
                'ref_type' => 'manual_adjustment',
                'ref_id' => (string) $targetUser->id,
                'description' => "Offset for manual adjustment: {$reason}",
            ],
        ];

        $this->post($entries, $idempotencyKey);
    }

    /**
     * Core atomic double-entry posting engine.
     *
     * @param array<int, array{
     *   wallet_id: ?int,
     *   account: LedgerAccount,
     *   direction: LedgerDirection,
     *   amount: int,
     *   ref_type: string,
     *   ref_id: string,
     *   description: string
     * }> $entries
     *
     * @throws BusinessRuleException
     */
    public function post(array $entries, string $idempotencyKey): void
    {
        // 1. Idempotency Check: if this key was already posted, return safely
        $existing = LedgerEntry::where('idempotency_key', $idempotencyKey)->exists();
        if ($existing) {
            return;
        }

        // 2. Strict Double-Entry Balance Invariant Check
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            if ($entry['amount'] <= 0) {
                throw new BusinessRuleException('Ledger entry amount must be strictly greater than zero.', 'INVALID_LEDGER_AMOUNT', 422);
            }

            if ($entry['direction'] === LedgerDirection::Debit) {
                $totalDebit += $entry['amount'];
            } else {
                $totalCredit += $entry['amount'];
            }
        }

        if ($totalDebit !== $totalCredit) {
            throw new BusinessRuleException(
                "Ledger imbalance violation: Debits ({$totalDebit}) != Credits ({$totalCredit}).",
                'LEDGER_IMBALANCE',
                500
            );
        }

        // 3. Atomic Database Execution & Balance Updates
        DB::transaction(function () use ($entries, $idempotencyKey) {
            $walletsToUpdate = [];

            foreach ($entries as $data) {
                // Insert immutable ledger entry
                LedgerEntry::create([
                    'wallet_id' => $data['wallet_id'],
                    'account' => $data['account'],
                    'direction' => $data['direction'],
                    'amount' => $data['amount'],
                    'idempotency_key' => $idempotencyKey,
                    'ref_type' => $data['ref_type'],
                    'ref_id' => $data['ref_id'],
                    'description' => $data['description'],
                    'created_at' => now(),
                ]);

                if ($data['wallet_id']) {
                    $walletId = $data['wallet_id'];
                    if (! isset($walletsToUpdate[$walletId])) {
                        // Acquire row lock to prevent race conditions on balance updates
                        $walletsToUpdate[$walletId] = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
                    }

                    $wallet = $walletsToUpdate[$walletId];

                    if ($data['account'] === LedgerAccount::Available) {
                        if ($data['direction'] === LedgerDirection::Credit) {
                            $wallet->available_balance += $data['amount'];
                        } else {
                            if ($wallet->available_balance < $data['amount']) {
                                throw new BusinessRuleException('Insufficient available wallet balance.', 'INSUFFICIENT_FUNDS', 422);
                            }
                            $wallet->available_balance -= $data['amount'];
                        }
                    } elseif ($data['account'] === LedgerAccount::Locked) {
                        if ($data['direction'] === LedgerDirection::Credit) {
                            $wallet->locked_balance += $data['amount'];
                        } else {
                            if ($wallet->locked_balance < $data['amount']) {
                                throw new BusinessRuleException('Insufficient locked balance to release or transfer.', 'INSUFFICIENT_LOCKED_FUNDS', 422);
                            }
                            $wallet->locked_balance -= $data['amount'];
                        }
                    }
                }
            }

            // Save updated balances
            foreach ($walletsToUpdate as $wallet) {
                $wallet->save();
            }
        });
    }
}
