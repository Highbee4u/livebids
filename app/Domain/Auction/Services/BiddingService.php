<?php

declare(strict_types=1);

namespace App\Domain\Auction\Services;

use App\Domain\Auction\Enums\BidStatus;
use App\Domain\Auction\Events\AuctionExtended;
use App\Domain\Auction\Events\BidPlaced;
use App\Domain\Auction\Events\LeaderboardUpdated;
use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Models\Bid;
use App\Domain\Ledger\Services\LedgerService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class BiddingService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Place or raise a bid on a live auction with atomic concurrency lock and ledger lock.
     *
     * @throws BusinessRuleException
     */
    public function placeBid(Auction $auction, User $bidder, int $amountKobo, ?string $clientRequestId = null): Bid
    {
        // 1. Basic Eligibility Checks
        if (! config('auction.features.bidding_enabled', true)) {
            throw new BusinessRuleException('Bidding is temporarily paused for maintenance.', 'BIDDING_DISABLED', 503);
        }

        if (! $auction->isLive()) {
            throw new BusinessRuleException('Bidding is only allowed when an auction is actively live.', 'AUCTION_NOT_LIVE', 422);
        }

        if ($bidder->id === $auction->seller_id) {
            throw new BusinessRuleException('Sellers cannot bid on their own auctions.', 'SELF_BIDDING_PROHIBITED', 403);
        }

        if (! $bidder->isKycVerified()) {
            throw new BusinessRuleException('Identity verification (NIN) is mandatory before bidding.', 'KYC_REQUIRED_FOR_BIDDING', 403);
        }

        if ($bidder->isRestricted()) {
            throw new BusinessRuleException('Account restricted. Bidding is disabled.', 'ACCOUNT_RESTRICTED', 403);
        }

        // 2. Per-user rate limiting (max 2 bids per second to prevent bot flooding)
        $rateKey = "bid_rate:{$bidder->id}:{$auction->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 2)) {
            throw new BusinessRuleException('Bidding too rapidly. Please slow down.', 'BID_RATE_LIMITED', 429);
        }
        RateLimiter::hit($rateKey, 1);

        // 3. New Account Bid Cap Check
        $newAccountCap = config('auction.new_account_max_bid_kobo', 20000000); // ₦200,000
        $newAccountDays = config('auction.new_account_days', 30);
        if ($bidder->created_at->addDays($newAccountDays)->isFuture() && $amountKobo > $newAccountCap) {
            $formattedCap = '₦'.number_format($newAccountCap / 100);
            throw new BusinessRuleException(
                "Accounts under {$newAccountDays} days are capped at {$formattedCap} per bid.",
                'NEW_ACCOUNT_BID_CAP_EXCEEDED',
                422
            );
        }

        // 4. Concurrency Mutex Lock per Auction (prevents simultaneous race condition bids)
        $lockKey = "auction_bid_mutex:{$auction->id}";
        $lock = Cache::lock($lockKey, 5);

        return $lock->block(4, function () use ($auction, $bidder, $amountKobo) {
            // Re-fetch auction state inside the lock
            $auction->refresh();
            if (! $auction->isLive()) {
                throw new BusinessRuleException('Auction has ended or is no longer live.', 'AUCTION_NOT_LIVE', 422);
            }

            // 5. Calculate Minimum Required Bid Amount
            $currentTopBid = Bid::where('auction_id', $auction->id)
                ->where('status', BidStatus::Active)
                ->orderByDesc('amount')
                ->first();

            $minIncrementPercent = config('auction.bid_increment_percentage', 5);
            $minIncrementFloor = config('auction.bid_increment_floor_kobo', 10000); // ₦100

            if (! $currentTopBid) {
                if ($amountKobo < $auction->starting_bid) {
                    $startingFormatted = '₦'.number_format($auction->starting_bid / 100, 2);
                    throw new BusinessRuleException("Bid must be at least the starting bid of {$startingFormatted}.", 'BELOW_STARTING_BID', 422);
                }
            } else {
                $increment = (int) max($currentTopBid->amount * ($minIncrementPercent / 100), $minIncrementFloor);
                $minAllowed = $currentTopBid->amount + $increment;

                if ($amountKobo < $minAllowed) {
                    $minFormatted = '₦'.number_format($minAllowed / 100, 2);
                    throw new BusinessRuleException("Bid amount too low. Next minimum bid is {$minFormatted}.", 'BID_BELOW_MINIMUM', 422, [
                        'current_top_bid' => $currentTopBid->amount,
                        'minimum_required' => $minAllowed,
                    ]);
                }
            }

            // 6. Handle Raising Own Bid or First Bid
            $previousUserBid = Bid::where('auction_id', $auction->id)
                ->where('bidder_id', $bidder->id)
                ->where('status', BidStatus::Active)
                ->first();

            $bidId = 'BID_'.strtoupper(Str::random(12));
            $lockRef = "bid_lock_{$auction->id}_{$bidId}";

            if ($previousUserBid) {
                // Only lock the delta
                $deltaKobo = $amountKobo - $previousUserBid->amount;
                if ($deltaKobo <= 0) {
                    throw new BusinessRuleException('You cannot lower your current active bid.', 'BID_LOWER_THAN_EXISTING', 422);
                }

                $this->ledgerService->lockFunds($bidder, $deltaKobo, (string) $auction->id, $bidId, "raise_{$lockRef}");
                $previousUserBid->update(['status' => BidStatus::Released]);
            } else {
                // Lock entire bid amount
                $this->ledgerService->lockFunds($bidder, $amountKobo, (string) $auction->id, $bidId, $lockRef);
            }

            // 7. Persist Bid
            $bid = Bid::create([
                'auction_id' => $auction->id,
                'bidder_id' => $bidder->id,
                'amount' => $amountKobo,
                'status' => BidStatus::Active,
                'lock_ledger_ref' => $lockRef,
                'created_at' => now(),
            ]);

            // 8. Anti-Sniping Extension Logic
            $this->evaluateAntiSniping($auction);

            // 9. Broadcast real-time events to Reverb
            $totalBids = Bid::where('auction_id', $auction->id)->count();
            event(new BidPlaced($bid, $totalBids));

            $leaderboard = $this->getLeaderboardSnapshot($auction->id);
            event(new LeaderboardUpdated($auction->id, $leaderboard));

            return $bid;
        });
    }

    /**
     * Check if bid is within the final seconds and add anti-snipe extension.
     */
    protected function evaluateAntiSniping(Auction $auction): void
    {
        if (! $auction->live_started_at) {
            return;
        }

        $duration = $auction->live_duration_seconds;
        $endsAt = $auction->live_started_at->copy()->addSeconds($duration);
        $secondsRemaining = now()->diffInSeconds($endsAt, false);

        $triggerSeconds = config('auction.anti_snipe_trigger_seconds', 10);
        $addSeconds = config('auction.anti_snipe_add_seconds', 15);
        $maxExtensions = config('auction.anti_snipe_max_extensions', 3);

        if ($secondsRemaining <= $triggerSeconds && $auction->extensions_count < $maxExtensions) {
            $auction->increment('extensions_count');
            $auction->increment('live_duration_seconds', $addSeconds);

            $newEndsAt = $auction->live_started_at->copy()->addSeconds($auction->live_duration_seconds);

            event(new AuctionExtended(
                auctionId: $auction->id,
                addedSeconds: $addSeconds,
                extensionsCount: $auction->extensions_count,
                newEndAt: $newEndsAt->toIso8601String()
            ));
        }
    }

    /**
     * Get top ranked bids for the live leaderboard.
     */
    public function getLeaderboardSnapshot(int $auctionId, int $limit = 10): array
    {
        $bids = Bid::with('bidder:id,name,phone,avatar_url')
            ->where('auction_id', $auctionId)
            ->where('status', BidStatus::Active)
            ->orderByDesc('amount')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return $bids->map(function (Bid $bid, int $index) {
            $user = $bid->bidder;

            return [
                'rank' => $index + 1,
                'bid_id' => $bid->id,
                'amount_kobo' => $bid->amount,
                'amount_formatted' => '₦'.number_format($bid->amount / 100, 2),
                'bidder_alias' => 'Bidder '.substr((string) ($user?->phone ?? '0000'), -4),
                'bidder_avatar' => $user?->avatar_url,
                'created_at' => $bid->created_at->toIso8601String(),
            ];
        })->toArray();
    }
}
