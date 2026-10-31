<?php

declare(strict_types=1);

namespace App\Domain\Auction\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuctionExtended implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $auctionId,
        public readonly int $addedSeconds,
        public readonly int $extensionsCount,
        public readonly string $newEndAt
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("auction.{$this->auctionId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'auction.extended';
    }

    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auctionId,
            'added_seconds' => $this->addedSeconds,
            'extensions_count' => $this->extensionsCount,
            'new_ends_at' => $this->newEndAt,
        ];
    }
}
