<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Order\Models\OrderMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderMessage
 */
class OrderMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender->name,
            'body' => $this->body,
            'attachments' => $this->attachments,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
