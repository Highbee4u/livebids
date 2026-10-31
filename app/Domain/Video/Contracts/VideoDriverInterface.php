<?php

declare(strict_types=1);

namespace App\Domain\Video\Contracts;

interface VideoDriverInterface
{
    /**
     * Generate an RTC publisher token for the live seller.
     *
     * @return array{token: string, ws_url: string, room: string}
     */
    public function generatePublisherToken(string $roomName, string $participantId, string $participantName): array;

    /**
     * Generate an RTC subscriber token for a live bidder/viewer.
     *
     * @return array{token: string, ws_url: string, room: string}
     */
    public function generateSubscriberToken(string $roomName, string $participantId, string $participantName): array;

    /**
     * End room and stop all active streams (kill-switch).
     */
    public function killRoom(string $roomName): bool;
}
