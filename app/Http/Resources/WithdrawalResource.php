<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Payment\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_kobo' => $this->amount,
            'amount_formatted' => '₦'.number_format($this->amount / 100, 2),
            'reference' => $this->reference,
            'status' => $this->status?->value ?? $this->status,
            'bank_account' => new BankAccountResource($this->whenLoaded('bankAccount')),
            'failure_reason' => $this->failure_reason,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
