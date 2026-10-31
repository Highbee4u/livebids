<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isBuyer = $request->user()?->id === $this->buyer_id;
        $isSeller = $request->user()?->id === $this->seller_id;

        return [
            'id' => $this->id,
            'auction_id' => $this->auction_id,
            'auction' => new AuctionResource($this->whenLoaded('auction')),
            'amount_kobo' => $this->amount,
            'amount_formatted' => '₦'.number_format($this->amount / 100, 2),
            'commission_amount_kobo' => $isSeller ? $this->commission_amount : null,
            'seller_payout_amount_kobo' => $isSeller ? $this->seller_payout_amount : null,
            'seller_payout_formatted' => $isSeller ? ('₦'.number_format($this->seller_payout_amount / 100, 2)) : null,
            'status' => $this->status?->value ?? $this->status,
            'handover_type' => $this->handover_type,
            'handover_details' => $this->handover_details,
            'handover_agreed_at' => $this->handover_agreed_at?->toIso8601String(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'handover_code' => ($isBuyer && $this->isAwaitingHandover()) ? $this->handover_code : null,
            'buyer' => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'phone' => $this->buyer->phone,
                'avatar_url' => $this->buyer->avatar_url,
            ],
            'seller' => [
                'id' => $this->seller->id,
                'name' => $this->seller->name,
                'phone' => $this->seller->phone,
                'avatar_url' => $this->seller->avatar_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
