<?php

declare(strict_types=1);

namespace App\Domain\Auction\Events;

use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionSold implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly Bid $winningBid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("auction.{$this->auction->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'auction.sold';
    }

    public function broadcastWith(): array
    {
        $winner = $this->winningBid->bidder;

        return [
            'auction_id' => $this->auction->id,
            'status' => 'sold',
            'winning_amount_kobo' => $this->winningBid->amount,
            'winning_amount_formatted' => '₦'.number_format($this->winningBid->amount / 100, 2),
            'winner_alias' => 'Bidder '.substr((string) ($winner?->phone ?? '0000'), -4),
            'skipped_higher_bid' => $this->auction->skipped_higher_bid,
            'ended_at' => now()->toIso8601String(),
        ];
    }
}
