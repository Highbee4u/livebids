<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auction\Models\Bid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bid
 */
class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bidder = $this->bidder;

        return [
            'id' => $this->id,
            'auction_id' => $this->auction_id,
            'amount_kobo' => $this->amount,
            'amount_formatted' => '₦'.number_format($this->amount / 100, 2),
            'status' => $this->status?->value ?? $this->status,
            'bidder' => [
                'alias' => 'Bidder '.substr((string) ($bidder?->phone ?? '0000'), -4),
                'avatar_url' => $bidder?->avatar_url,
                'rating' => (float) ($bidder?->rating ?? 5.0),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
