<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Terms\Enums\TermsType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $typeVal = $this->type instanceof TermsType ? $this->type->value : $this->type;
        $typeLabel = $this->type instanceof TermsType ? $this->type->label() : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $typeVal,
            'type_label' => $typeLabel,
            'version' => $this->version,
            'content' => $this->content,
            'summary_of_changes' => $this->summary_of_changes,
            'is_active' => (bool) $this->is_active,
            'is_required' => (bool) $this->is_required,
            'effective_date' => $this->effective_date?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
