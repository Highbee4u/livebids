<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auction\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Auction
 */
class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSeller = $request->user()?->id === $this->seller_id;
        $highestBid = $this->getHighestBid();

        $secondsRemaining = null;
        if ($this->isLive() && $this->live_started_at) {
            $endsAt = $this->live_started_at->copy()->addSeconds($this->live_duration_seconds);
            $secondsRemaining = max(0, (int) now()->diffInSeconds($endsAt, false));
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'condition' => $this->condition?->value ?? $this->condition,
            'teaser_video_url' => $this->teaser_video_url,
            'thumbnail_url' => $this->thumbnail_url,
            'starting_bid_kobo' => $this->starting_bid,
            'starting_bid_formatted' => '₦'.number_format($this->starting_bid / 100, 2),
            // Hidden reserve price is only visible to the seller
            'reserve_price_kobo' => $isSeller ? $this->reserve_price : null,
            'pickup_area' => $this->pickup_area,
            'delivery_options' => $this->delivery_options,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'live_duration_seconds' => $this->live_duration_seconds,
            'seconds_remaining' => $secondsRemaining,
            'status' => $this->status?->value ?? $this->status,
            'live_started_at' => $this->live_started_at?->toIso8601String(),
            'live_ended_at' => $this->live_ended_at?->toIso8601String(),
            'highest_bid' => $highestBid ? [
                'amount_kobo' => $highestBid->amount,
                'amount_formatted' => '₦'.number_format($highestBid->amount / 100, 2),
            ] : null,
            'winning_amount_kobo' => $this->winning_amount,
            'winning_amount_formatted' => $this->winning_amount ? ('₦'.number_format($this->winning_amount / 100, 2)) : null,
            'skipped_higher_bid' => $this->skipped_higher_bid,
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'avatar_url' => $this->seller->avatar_url,
                'rating' => (float) $this->seller->rating,
                'is_verified' => $this->seller->isKycVerified(),
            ],
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
