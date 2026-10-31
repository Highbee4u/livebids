<?php

declare(strict_types=1);

namespace App\Domain\Auction\Models;

use App\Domain\Auction\Enums\BidStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'auction_id',
        'bidder_id',
        'amount',
        'status',
        'lock_ledger_ref',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => BidStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }
}
