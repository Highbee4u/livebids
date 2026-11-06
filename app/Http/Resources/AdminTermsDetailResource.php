<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Terms\Enums\TermsType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTermsDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $terms = $this['terms'];
        $typeVal = $terms->type instanceof TermsType ? $terms->type->value : $terms->type;
        $typeLabel = $terms->type instanceof TermsType ? $terms->type->label() : null;

        return [
            'id' => $terms->id,
            'title' => $terms->title,
            'slug' => $terms->slug,
            'type' => $typeVal,
            'type_label' => $typeLabel,
            'version' => $terms->version,
            'content' => $terms->content,
            'summary_of_changes' => $terms->summary_of_changes,
            'is_active' => (bool) $terms->is_active,
            'is_required' => (bool) $terms->is_required,
            'effective_date' => $terms->effective_date?->toIso8601String(),
            'created_by' => $terms->created_by,
            'created_at' => $terms->created_at?->toIso8601String(),
            'updated_at' => $terms->updated_at?->toIso8601String(),
            'metrics' => [
                'total_accepted' => $this['total_accepted'],
                'total_users' => $this['total_users'],
                'acceptance_percentage' => $this['acceptance_percentage'],
            ],
        ];
    }
}
