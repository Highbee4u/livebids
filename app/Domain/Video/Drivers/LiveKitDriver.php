<?php

declare(strict_types=1);

namespace App\Domain\Video\Drivers;

use App\Domain\Video\Contracts\VideoDriverInterface;
use Illuminate\Support\Facades\Http;

class LiveKitDriver implements VideoDriverInterface
{
    protected string $apiUrl;

    protected string $apiKey;

    protected string $apiSecret;

    public function __construct(array $config)
    {
        $this->apiUrl = rtrim((string) ($config['api_url'] ?? 'http://localhost:7880'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->apiSecret = (string) ($config['api_secret'] ?? '');
    }

    public function generatePublisherToken(string $roomName, string $participantId, string $participantName): array
    {
        $grants = [
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
            ],
        ];

        $token = $this->createJwt($participantId, $participantName, $grants);

        return [
            'token' => $token,
            'ws_url' => str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->apiUrl),
            'room' => $roomName,
        ];
    }

    public function generateSubscriberToken(string $roomName, string $participantId, string $participantName): array
    {
        $grants = [
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => false,
                'canSubscribe' => true,
            ],
        ];

        $token = $this->createJwt($participantId, $participantName, $grants);

        return [
            'token' => $token,
            'ws_url' => str_replace(['http://', 'https://'], ['ws://', 'wss://'], $this->apiUrl),
            'room' => $roomName,
        ];
    }

    public function killRoom(string $roomName): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return true;
        }

        try {
            $adminToken = $this->createJwt('admin', 'Admin', ['video' => ['roomAdmin' => true, 'room' => $roomName]]);
            $response = Http::withToken($adminToken)
                ->timeout(5)
                ->post("{$this->apiUrl}/twirp/livekit.RoomService/DeleteRoom", [
                    'room' => $roomName,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function createJwt(string $identity, string $name, array $grants): string
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return 'dev_token_'.base64_encode("{$identity}:{$name}");
        }

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $this->apiKey,
            'sub' => $identity,
            'name' => $name,
            'nbf' => time() - 5,
            'exp' => time() + 14400, // 4 hours
            ...$grants,
        ];

        $base64Header = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $base64Payload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->apiSecret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
