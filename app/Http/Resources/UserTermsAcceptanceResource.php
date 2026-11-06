<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTermsAcceptanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'terms_id' => $this->terms_id,
            'terms' => new TermsResource($this->whenLoaded('terms')),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
