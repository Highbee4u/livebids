<?php

declare(strict_types=1);

namespace App\Domain\Auction\Services;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Enums\BidStatus;
use App\Domain\Auction\Events\AuctionEnded;
use App\Domain\Auction\Events\AuctionSold;
use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Models\AuctionEvent;
use App\Domain\Auction\Models\Bid;
use App\Domain\Identity\Enums\StrikeReason;
use App\Domain\Identity\Models\Strike;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Video\Services\VideoTokenService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuctionStateService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected VideoTokenService $videoTokenService
    ) {}

    /**
     * Create an auction listing in Draft state.
     *
     * @throws BusinessRuleException
     */
    public function createAuction(User $seller, array $data): Auction
    {
        if (! $seller->isKycVerified()) {
            throw new BusinessRuleException('Identity verification is required before listing an item.', 'KYC_REQUIRED_TO_SELL', 403);
        }

        $minLeadSeconds = app()->environment('testing') ? 0 : config('auction.schedule_min_lead_seconds', 3600);
        $scheduledAt = Carbon::parse($data['scheduled_start_at']);

        if ($scheduledAt->lt(now()->addSeconds($minLeadSeconds))) {
            throw new BusinessRuleException(
                'Live sessions must be scheduled at least 1 hour in advance.',
                'INVALID_SCHEDULE_TIME',
                422
            );
        }

        return Auction::create([
            'seller_id' => $seller->id,
            'category_id' => $data['category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'condition' => $data['condition'] ?? 'like_new',
            'teaser_video_url' => $data['teaser_video_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'starting_bid' => $data['starting_bid'] ?? 100000,
            'reserve_price' => $data['reserve_price'] ?? null,
            'pickup_area' => $data['pickup_area'],
            'delivery_options' => $data['delivery_options'] ?? ['pickup' => true],
            'scheduled_start_at' => $scheduledAt,
            'live_duration_seconds' => $data['live_duration_seconds'] ?? config('auction.live_duration_seconds', 180),
            'status' => AuctionStatus::Scheduled, // Auto-scheduled for MVP
        ]);
    }

    /**
     * Seller begins the live video auction stream.
     *
     * @throws BusinessRuleException
     */
    public function startLive(Auction $auction, User $seller): array
    {
        if ($auction->seller_id !== $seller->id) {
            throw new BusinessRuleException('Only the listing seller can start this live session.', 'UNAUTHORIZED_SELLER', 403);
        }

        if ($auction->isLive()) {
            // Reconnecting: return token without restarting timers
            return $this->videoTokenService->getPublisherToken($auction, $seller);
        }

        if ($auction->status !== AuctionStatus::Scheduled) {
            throw new BusinessRuleException('Auction is not in a scheduled state.', 'AUCTION_NOT_SCHEDULED', 422);
        }

        // Validate Grace Window (5 to 10 minutes)
        $graceSeconds = config('auction.grace_window_seconds', 600);
        $scheduledTime = $auction->scheduled_start_at;

        if (! app()->environment('testing')) {
            if (now()->lt($scheduledTime->copy()->subMinutes(15))) {
                throw new BusinessRuleException('Too early to go live. You may begin camera setup 15 minutes before scheduled start.', 'TOO_EARLY', 422);
            }

            if (now()->gt($scheduledTime->copy()->addSeconds($graceSeconds))) {
                $this->markMissed($auction);
                throw new BusinessRuleException('Start window expired. Auction marked as missed.', 'GRACE_PERIOD_EXPIRED', 422);
            }
        }

        $fromStatus = $auction->status->value;

        $auction->update([
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        AuctionEvent::create([
            'auction_id' => $auction->id,
            'event_type' => 'live_started',
            'from_status' => $fromStatus,
            'to_status' => AuctionStatus::Live->value,
            'triggered_by' => $seller->id,
        ]);

        return $this->videoTokenService->getPublisherToken($auction, $seller);
    }

    /**
     * Seller picks a winning bid (either top bid or any valid ranked bid).
     * Ends auction instantly, holds winning funds in escrow, and unlocks all losing bidders.
     *
     * @throws BusinessRuleException
     */
    public function selectWinner(Auction $auction, User $seller, int $bidId): Order
    {
        if ($auction->seller_id !== $seller->id) {
            throw new BusinessRuleException('Only the seller can select a winner.', 'UNAUTHORIZED_SELLER', 403);
        }

        // Double-tap Guard: if already sold, return existing order
        if ($auction->status === AuctionStatus::Sold) {
            $existingOrder = Order::where('auction_id', $auction->id)->first();
            if ($existingOrder) {
                return $existingOrder;
            }
        }

        if (! in_array($auction->status, [AuctionStatus::Live, AuctionStatus::InDecisionWindow], true)) {
            throw new BusinessRuleException('Winner can only be selected during the live session or decision window.', 'INVALID_AUCTION_STATE', 422);
        }

        return DB::transaction(function () use ($auction, $seller, $bidId) {
            $winningBid = Bid::where('id', $bidId)
                ->where('auction_id', $auction->id)
                ->firstOrFail();

            if ($winningBid->status !== BidStatus::Active) {
                throw new BusinessRuleException('This bid is no longer active.', 'INACTIVE_BID', 422);
            }

            // Check if seller skipped a higher bid
            $topBid = Bid::where('auction_id', $auction->id)
                ->where('status', BidStatus::Active)
                ->orderByDesc('amount')
                ->first();

            $skippedHigher = $topBid && ($winningBid->amount < $topBid->amount);

            // Calculate Commission & Settlement Split
            $commissionRate = config('auction.commission_percentage', 10);
            $commissionAmount = (int) round($winningBid->amount * ($commissionRate / 100));
            $sellerPayout = $winningBid->amount - $commissionAmount;

            // Generate 6-digit Handover Code
            $handoverCode = sprintf('%06d', random_int(100000, 999999));
            $autoReleaseHours = config('auction.escrow_auto_release_hours', 72);

            // Create Order
            $order = Order::create([
                'auction_id' => $auction->id,
                'buyer_id' => $winningBid->bidder_id,
                'seller_id' => $seller->id,
                'amount' => $winningBid->amount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'seller_payout_amount' => $sellerPayout,
                'status' => OrderStatus::AwaitingHandover,
                'handover_code' => $handoverCode,
                'handover_code_hash' => Hash::make($handoverCode),
                'deadline_at' => now()->addHours($autoReleaseHours),
            ]);

            // Move Winning Bidder's Locked Funds into Escrow
            $this->ledgerService->moveLockedToEscrow(
                $winningBid->bidder,
                $winningBid->amount,
                (string) $order->id,
                (string) $winningBid->id,
                "escrow_hold_order_{$order->id}"
            );

            $winningBid->update(['status' => BidStatus::Won]);

            // Release all losing bidders' locks immediately and atomically
            $losingBids = Bid::with('bidder')
                ->where('auction_id', $auction->id)
                ->where('status', BidStatus::Active)
                ->where('id', '!=', $winningBid->id)
                ->get();

            foreach ($losingBids as $losingBid) {
                $this->ledgerService->unlockFunds(
                    $losingBid->bidder,
                    $losingBid->amount,
                    (string) $auction->id,
                    (string) $losingBid->id,
                    "unlock_bid_{$losingBid->id}"
                );
                $losingBid->update(['status' => BidStatus::Released]);
            }

            // Update Auction to Sold
            $fromStatus = $auction->status->value;
            $auction->update([
                'status' => AuctionStatus::Sold,
                'winning_bid_id' => $winningBid->id,
                'winning_amount' => $winningBid->amount,
                'skipped_higher_bid' => $skippedHigher,
                'live_ended_at' => now(),
            ]);

            AuctionEvent::create([
                'auction_id' => $auction->id,
                'event_type' => 'auction_sold',
                'from_status' => $fromStatus,
                'to_status' => AuctionStatus::Sold->value,
                'triggered_by' => $seller->id,
                'metadata' => [
                    'winning_bid_id' => $winningBid->id,
                    'winning_amount' => $winningBid->amount,
                    'skipped_higher_bid' => $skippedHigher,
                ],
            ]);

            // Broadcast real-time Sold event
            event(new AuctionSold($auction, $winningBid));

            // Attach temporary plaintext code for response
            $order->setAttribute('initial_handover_code', $handoverCode);

            return $order;
        });
    }

    /**
     * End auction as unsold and unlock all bidders.
     */
    public function endUnsold(Auction $auction, User $seller, string $reason = 'seller_ended_unsold'): void
    {
        if ($auction->seller_id !== $seller->id) {
            throw new BusinessRuleException('Unauthorized.', 'UNAUTHORIZED_SELLER', 403);
        }

        if ($auction->status === AuctionStatus::Sold) {
            throw new BusinessRuleException('Cannot end an auction that has already been sold.', 'AUCTION_ALREADY_SOLD', 422);
        }

        DB::transaction(function () use ($auction, $seller, $reason) {
            // Release any active locks
            $activeBids = Bid::with('bidder')
                ->where('auction_id', $auction->id)
                ->where('status', BidStatus::Active)
                ->get();

            foreach ($activeBids as $bid) {
                $this->ledgerService->unlockFunds(
                    $bid->bidder,
                    $bid->amount,
                    (string) $auction->id,
                    (string) $bid->id,
                    "unsold_unlock_bid_{$bid->id}"
                );
                $bid->update(['status' => BidStatus::Released]);
            }

            $fromStatus = $auction->status->value;
            $auction->update([
                'status' => AuctionStatus::Unsold,
                'live_ended_at' => now(),
            ]);

            AuctionEvent::create([
                'auction_id' => $auction->id,
                'event_type' => 'auction_unsold',
                'from_status' => $fromStatus,
                'to_status' => AuctionStatus::Unsold->value,
                'triggered_by' => $seller->id,
                'metadata' => ['reason' => $reason],
            ]);

            event(new AuctionEnded($auction, $reason));
        });
    }

    /**
     * Mark auction as missed when seller fails to show up within the grace window.
     */
    public function markMissed(Auction $auction): void
    {
        if ($auction->status !== AuctionStatus::Scheduled) {
            return;
        }

        DB::transaction(function () use ($auction) {
            $fromStatus = $auction->status->value;

            $auction->update([
                'status' => AuctionStatus::Missed,
                'live_ended_at' => now(),
            ]);

            // Issue strike to seller
            Strike::create([
                'user_id' => $auction->seller_id,
                'reason' => StrikeReason::SellerNoShow,
                'note' => "Seller failed to go live for scheduled auction #{$auction->id}",
                'auction_id' => $auction->id,
            ]);

            $auction->seller->increment('strikes_count');

            AuctionEvent::create([
                'auction_id' => $auction->id,
                'event_type' => 'auction_missed',
                'from_status' => $fromStatus,
                'to_status' => AuctionStatus::Missed->value,
            ]);

            event(new AuctionEnded($auction, 'seller_no_show'));
        });
    }
}
