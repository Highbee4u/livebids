<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => $this->whenLoaded('country', fn () => $this->country?->name),
            'state' => $this->whenLoaded('state', fn () => $this->state?->name),
            'lga' => $this->whenLoaded('lga', fn () => $this->lga?->name),
            'city' => $this->city,
            'street_address' => $this->street_address,
            'postal_code' => $this->postal_code,
            'is_primary' => $this->is_primary,
            'verification_status' => $this->verification_status,
        ];
    }
}
