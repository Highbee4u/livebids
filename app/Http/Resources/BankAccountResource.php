<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Payment\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'status' => $this->status?->value ?? $this->status,
            'is_default' => $this->is_default,
        ];
    }
}
