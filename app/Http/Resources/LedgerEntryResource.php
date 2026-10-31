<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => $this->account?->value ?? $this->account,
            'direction' => $this->direction?->value ?? $this->direction,
            'amount_kobo' => $this->amount,
            'amount_formatted' => '₦'.number_format($this->amount / 100, 2),
            'ref_type' => $this->ref_type,
            'ref_id' => $this->ref_id,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
