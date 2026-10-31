<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Identity\Models\KycRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KycRecord
 */
class KycRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value ?? $this->status,
            'verified_name' => $this->verified_name,
            'match_score' => (float) $this->match_score,
            'attempt_count' => $this->attempt_count,
            'cooldown_until' => $this->cooldown_until?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
