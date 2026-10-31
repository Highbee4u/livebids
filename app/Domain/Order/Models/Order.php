<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use App\Domain\Auction\Models\Auction;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Order\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'buyer_id',
        'seller_id',
        'amount',
        'commission_rate',
        'commission_amount',
        'seller_payout_amount',
        'status',
        'handover_code',
        'handover_code_hash',
        'handover_code_attempts',
        'handover_code_cooldown_until',
        'handover_type',
        'handover_details',
        'handover_agreed_at',
        'deadline_at',
        'completed_at',
    ];

    protected $hidden = [
        'handover_code',
        'handover_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount' => 'integer',
            'commission_rate' => 'integer',
            'commission_amount' => 'integer',
            'seller_payout_amount' => 'integer',
            'handover_code_attempts' => 'integer',
            'handover_details' => 'array',
            'handover_code_cooldown_until' => 'datetime',
            'handover_agreed_at' => 'datetime',
            'deadline_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::Completed;
    }

    public function isAwaitingHandover(): bool
    {
        return $this->status === OrderStatus::AwaitingHandover;
    }

    public function isDisputed(): bool
    {
        return $this->status === OrderStatus::Disputed;
    }
}
