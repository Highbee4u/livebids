<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\StrikeReason;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Strike extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'issued_by',
        'reason',
        'note',
        'order_id',
        'auction_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => StrikeReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
