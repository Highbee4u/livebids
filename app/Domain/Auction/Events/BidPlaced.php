<?php

declare(strict_types=1);

namespace App\Domain\Auction\Events;

use App\Domain\Auction\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Bid $bid,
        public readonly int $totalBidsCount
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("auction.{$this->bid->auction_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'bid.placed';
    }

    public function broadcastWith(): array
    {
        $bidder = $this->bid->bidder;

        return [
            'bid_id' => $this->bid->id,
            'auction_id' => $this->bid->auction_id,
            'amount_kobo' => $this->bid->amount,
            'amount_formatted' => '₦'.number_format($this->bid->amount / 100, 2),
            'bidder_alias' => 'Bidder '.substr((string) ($bidder?->phone ?? '0000'), -4),
            'bidder_avatar' => $bidder?->avatar_url,
            'total_bids' => $this->totalBidsCount,
            'timestamp' => $this->bid->created_at->toIso8601String(),
        ];
    }
}
