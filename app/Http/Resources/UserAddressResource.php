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
            'country' => new CountryResource($this->whenLoaded('country')),
            'state' => new StateResource($this->whenLoaded('state')),
            'lga' => new LgaResource($this->whenLoaded('lga')),
            'city' => $this->city,
            'street_address' => $this->street_address,
            'postal_code' => $this->postal_code,
            'is_primary' => $this->is_primary,
            'verification_status' => $this->verification_status,
        ];
    }
}
