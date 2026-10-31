<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'condition' => ['nullable', 'string', 'in:new,like_new,used_fair,refurbished'],
            'starting_bid' => ['required', 'integer', 'min:10000'], // ₦100 floor
            'reserve_price' => ['nullable', 'integer', 'gt:starting_bid'],
            'pickup_area' => ['required', 'string', 'max:150'],
            'delivery_options' => ['nullable', 'array'],
            'teaser_video_url' => ['nullable', 'url', 'max:500'],
            'thumbnail_url' => ['nullable', 'url', 'max:500'],
            'scheduled_start_at' => ['required', 'date'],
            'live_duration_seconds' => ['nullable', 'integer', 'min:60', 'max:600'],
        ];
    }
}
