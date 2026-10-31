<?php

declare(strict_types=1);

namespace App\Domain\Dispute\Services;

use App\Domain\Dispute\Models\Dispute;
use App\Domain\Dispute\Models\DisputeEvidence;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisputeService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Open a dispute on an order, freezing escrow and halting auto-release.
     *
     * @throws BusinessRuleException
     */
    public function openDispute(Order $order, User $user, string $reason, ?string $evidencePath = null, ?string $description = null): Dispute
    {
        if ($user->id !== $order->buyer_id && $user->id !== $order->seller_id) {
            throw new BusinessRuleException('Only the buyer or seller can open a dispute on this order.', 'FORBIDDEN', 403);
        }

        if ($order->status !== OrderStatus::AwaitingHandover) {
            throw new BusinessRuleException('Disputes can only be opened while an order is awaiting handover.', 'INVALID_ORDER_STATE', 422);
        }

        return DB::transaction(function () use ($order, $user, $reason, $evidencePath, $description) {
            // Freeze escrow
            $order->update(['status' => OrderStatus::Disputed]);

            $dispute = Dispute::create([
                'order_id' => $order->id,
                'opened_by' => $user->id,
                'reason' => $reason,
                'status' => 'open',
            ]);

            if ($evidencePath) {
                DisputeEvidence::create([
                    'dispute_id' => $dispute->id,
                    'uploaded_by' => $user->id,
                    'file_path' => $evidencePath,
                    'media_type' => 'photo',
                    'description' => $description,
                    'created_at' => now(),
                ]);
            }

            return $dispute;
        });
    }

    /**
     * Admin resolves a dispute with an outcome (refund buyer, release seller, or split).
     *
     * @throws BusinessRuleException
     */
    public function resolveDispute(
        Dispute $dispute,
        User $admin,
        string $outcome, // 'resolved_buyer', 'resolved_seller', 'resolved_split'
        ?int $buyerSplitKobo = null,
        ?int $sellerSplitKobo = null,
        ?string $notes = null
    ): Dispute {
        if ($dispute->status !== 'open' && $dispute->status !== 'under_review') {
            throw new BusinessRuleException('Dispute has already been resolved.', 'DISPUTE_ALREADY_RESOLVED', 422);
        }

        $order = $dispute->order;

        return DB::transaction(function () use ($dispute, $order, $admin, $outcome, $buyerSplitKobo, $sellerSplitKobo, $notes) {
            $idempotencyKey = "resolve_dispute_{$dispute->id}";

            if ($outcome === 'resolved_buyer') {
                // 100% refund to buyer
                $this->ledgerService->refundEscrow(
                    buyer: $order->buyer,
                    amountKobo: $order->amount,
                    orderId: (string) $order->id,
                    idempotencyKey: $idempotencyKey
                );

                $order->update([
                    'status' => OrderStatus::CancelledRefunded,
                    'completed_at' => now(),
                ]);
            } elseif ($outcome === 'resolved_seller') {
                // 100% release to seller minus platform commission
                $this->ledgerService->releaseEscrow(
                    seller: $order->seller,
                    grossAmountKobo: $order->amount,
                    commissionKobo: $order->commission_amount,
                    orderId: (string) $order->id,
                    idempotencyKey: $idempotencyKey
                );

                $order->update([
                    'status' => OrderStatus::Completed,
                    'completed_at' => now(),
                ]);
            } elseif ($outcome === 'resolved_split') {
                $buyerAmt = (int) $buyerSplitKobo;
                $sellerAmt = (int) $sellerSplitKobo;
                $commission = $order->amount - ($buyerAmt + $sellerAmt);

                $this->ledgerService->partialRefundAndSplit(
                    buyer: $order->buyer,
                    buyerAmountKobo: $buyerAmt,
                    seller: $order->seller,
                    sellerAmountKobo: $sellerAmt,
                    commissionKobo: max(0, $commission),
                    orderId: (string) $order->id,
                    idempotencyKey: $idempotencyKey
                );

                $order->update([
                    'status' => OrderStatus::Completed,
                    'completed_at' => now(),
                ]);
            } else {
                throw new BusinessRuleException("Unsupported dispute outcome: {$outcome}", 'INVALID_OUTCOME', 422);
            }

            $dispute->update([
                'status' => $outcome,
                'resolution_notes' => $notes,
                'buyer_split_amount' => $buyerSplitKobo,
                'seller_split_amount' => $sellerSplitKobo,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ]);

            return $dispute;
        });
    }
}
