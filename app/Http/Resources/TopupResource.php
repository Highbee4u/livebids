<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Payment\Models\Topup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Topup
 */
class TopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_kobo' => $this->amount,
            'amount_formatted' => '₦'.number_format($this->amount / 100, 2),
            'reference' => $this->reference,
            'status' => $this->status?->value ?? $this->status,
            'checkout_url' => $this->checkout_url,
            'access_code' => $this->access_code,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
