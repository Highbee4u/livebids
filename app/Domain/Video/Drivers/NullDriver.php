<?php

declare(strict_types=1);

namespace App\Domain\Video\Drivers;

use App\Domain\Video\Contracts\VideoDriverInterface;

class NullDriver implements VideoDriverInterface
{
    public function __construct(protected array $config = []) {}

    public function generatePublisherToken(string $roomName, string $participantId, string $participantName): array
    {
        return [
            'token' => 'mock_publisher_token_'.base64_encode("{$roomName}:{$participantId}"),
            'ws_url' => 'ws://localhost:7880',
            'room' => $roomName,
        ];
    }

    public function generateSubscriberToken(string $roomName, string $participantId, string $participantName): array
    {
        return [
            'token' => 'mock_subscriber_token_'.base64_encode("{$roomName}:{$participantId}"),
            'ws_url' => 'ws://localhost:7880',
            'room' => $roomName,
        ];
    }

    public function killRoom(string $roomName): bool
    {
        return true;
    }
}
