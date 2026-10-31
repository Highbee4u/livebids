<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available_balance_kobo' => $this->available_balance,
            'available_balance_formatted' => '₦'.number_format($this->available_balance / 100, 2),
            'locked_balance_kobo' => $this->locked_balance,
            'locked_balance_formatted' => '₦'.number_format($this->locked_balance / 100, 2),
            'total_balance_kobo' => $this->total_balance,
            'total_balance_formatted' => '₦'.number_format($this->total_balance / 100, 2),
            'currency' => $this->currency,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
