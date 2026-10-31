<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'available_balance',
        'locked_balance',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'integer',
            'locked_balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function getTotalBalanceAttribute(): int
    {
        return $this->available_balance + $this->locked_balance;
    }
}
