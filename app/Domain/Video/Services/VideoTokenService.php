<?php

declare(strict_types=1);

namespace App\Domain\Video\Services;

use App\Domain\Auction\Models\Auction;
use App\Domain\Video\Contracts\VideoDriverInterface;
use App\Domain\Video\Drivers\LiveKitDriver;
use App\Domain\Video\Drivers\NullDriver;
use App\Domain\Video\Models\LiveSession;
use App\Models\User;

class VideoTokenService
{
    protected VideoDriverInterface $driver;

    public function __construct()
    {
        $default = config('video.default', 'livekit');
        $config = config("video.drivers.{$default}", []);

        $this->driver = match ($default) {
            'livekit' => new LiveKitDriver($config),
            default => new NullDriver($config),
        };
    }

    /**
     * Get or create active live session and generate seller publisher token.
     */
    public function getPublisherToken(Auction $auction, User $seller): array
    {
        $roomName = "auction_{$auction->id}";

        $session = LiveSession::firstOrCreate(
            ['auction_id' => $auction->id, 'room_name' => $roomName],
            ['started_at' => now()]
        );

        return $this->driver->generatePublisherToken(
            roomName: $roomName,
            participantId: (string) $seller->id,
            participantName: $seller->name
        );
    }

    /**
     * Generate subscriber token for a viewer/bidder.
     */
    public function getSubscriberToken(Auction $auction, User $viewer): array
    {
        $roomName = "auction_{$auction->id}";

        return $this->driver->generateSubscriberToken(
            roomName: $roomName,
            participantId: (string) $viewer->id,
            participantName: $viewer->name
        );
    }

    /**
     * Kill room stream (admin kill-switch or emergency moderation).
     */
    public function killStream(Auction $auction, ?User $admin = null): bool
    {
        $roomName = "auction_{$auction->id}";

        $killed = $this->driver->killRoom($roomName);

        LiveSession::where('auction_id', $auction->id)->update([
            'is_killed' => true,
            'killed_by' => $admin?->id,
            'ended_at' => now(),
        ]);

        return $killed;
    }
}
