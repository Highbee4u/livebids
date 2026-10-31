<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auction\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon_url' => $this->icon_url,
            'description' => $this->description,
        ];
    }
}
