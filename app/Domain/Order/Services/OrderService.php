<?php

declare(strict_types=1);

namespace App\Domain\Order\Services;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderMessage;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Seller enters the 6-digit handover code provided by the buyer upon item inspection.
     * Triggers escrow payout release to seller minus platform commission.
     *
     * @throws BusinessRuleException
     */
    public function confirmHandoverWithCode(Order $order, User $seller, string $code): Order
    {
        if ($order->seller_id !== $seller->id) {
            throw new BusinessRuleException('Only the seller can confirm handover with the code.', 'UNAUTHORIZED_SELLER', 403);
        }

        if ($order->status !== OrderStatus::AwaitingHandover) {
            throw new BusinessRuleException("Order is not awaiting handover. Current status: {$order->status->value}", 'ORDER_NOT_AWAITING_HANDOVER', 422);
        }

        // 1. Cool-down Check
        if ($order->handover_code_cooldown_until && $order->handover_code_cooldown_until->isFuture()) {
            $mins = $order->handover_code_cooldown_until->diffInMinutes();
            throw new BusinessRuleException(
                "Too many incorrect attempts. Handover code entry locked for {$mins} more minutes.",
                'HANDOVER_CODE_LOCKED',
                429
            );
        }

        // 2. Verify 6-digit Code
        if (! Hash::check($code, $order->handover_code_hash)) {
            $order->increment('handover_code_attempts');

            $maxAttempts = config('auction.handover_code_max_attempts', 5);
            $cooldownMins = config('auction.handover_code_cooldown_minutes', 30);

            if ($order->handover_code_attempts >= $maxAttempts) {
                $order->update(['handover_code_cooldown_until' => now()->addMinutes($cooldownMins)]);
                throw new BusinessRuleException(
                    "Maximum attempts exceeded. Code verification locked for {$cooldownMins} minutes.",
                    'HANDOVER_CODE_EXHAUSTED',
                    429
                );
            }

            $remaining = $maxAttempts - $order->handover_code_attempts;
            throw new BusinessRuleException("Invalid handover code. {$remaining} attempts remaining.", 'INVALID_HANDOVER_CODE', 422);
        }

        // 3. Release Escrow & Settle Order Atomically
        return DB::transaction(function () use ($order, $seller) {
            $this->ledgerService->releaseEscrow(
                seller: $seller,
                grossAmountKobo: $order->amount,
                commissionKobo: $order->commission_amount,
                orderId: (string) $order->id,
                idempotencyKey: "payout_order_{$order->id}"
            );

            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Buyer explicitly taps "Mark as received" from their order screen.
     *
     * @throws BusinessRuleException
     */
    public function buyerMarkReceived(Order $order, User $buyer): Order
    {
        if ($order->buyer_id !== $buyer->id) {
            throw new BusinessRuleException('Only the buyer can mark the item as received.', 'UNAUTHORIZED_BUYER', 403);
        }

        if ($order->status !== OrderStatus::AwaitingHandover) {
            throw new BusinessRuleException('Order is not awaiting handover.', 'ORDER_NOT_AWAITING_HANDOVER', 422);
        }

        return DB::transaction(function () use ($order) {
            $this->ledgerService->releaseEscrow(
                seller: $order->seller,
                grossAmountKobo: $order->amount,
                commissionKobo: $order->commission_amount,
                orderId: (string) $order->id,
                idempotencyKey: "payout_buyer_received_{$order->id}"
            );

            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Structured arrangement details (pickup time, location, delivery options).
     *
     * @throws BusinessRuleException
     */
    public function submitArrangement(Order $order, User $user, string $type, array $details): Order
    {
        if ($user->id !== $order->buyer_id && $user->id !== $order->seller_id) {
            throw new BusinessRuleException('Unauthorized.', 'FORBIDDEN', 403);
        }

        $order->update([
            'handover_type' => $type,
            'handover_details' => $details,
            'handover_agreed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Send in-app chat message between buyer and seller.
     *
     * @throws BusinessRuleException
     */
    public function sendMessage(Order $order, User $sender, string $body, ?array $attachments = null): OrderMessage
    {
        if ($sender->id !== $order->buyer_id && $sender->id !== $order->seller_id) {
            throw new BusinessRuleException('Unauthorized to participate in this order chat.', 'FORBIDDEN', 403);
        }

        return OrderMessage::create([
            'order_id' => $order->id,
            'sender_id' => $sender->id,
            'body' => $body,
            'attachments' => $attachments,
            'created_at' => now(),
        ]);
    }

    /**
     * Automatic escrow release if buyer never confirms and no dispute is active.
     */
    public function autoReleaseEscrow(Order $order): bool
    {
        if ($order->status !== OrderStatus::AwaitingHandover) {
            return false;
        }

        if (now()->lt($order->deadline_at)) {
            return false;
        }

        DB::transaction(function () use ($order) {
            $this->ledgerService->releaseEscrow(
                seller: $order->seller,
                grossAmountKobo: $order->amount,
                commissionKobo: $order->commission_amount,
                orderId: (string) $order->id,
                idempotencyKey: "auto_release_order_{$order->id}"
            );

            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);
        });

        return true;
    }
}
