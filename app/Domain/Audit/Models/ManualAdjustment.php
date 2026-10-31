<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAdjustment extends Model
{
    use HasFactory;

    protected $table = 'manual_adjustments_queue';

    protected $fillable = [
        'requested_by',
        'approved_by',
        'target_user_id',
        'account',
        'direction',
        'amount',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'direction' => LedgerDirection::class,
            'amount' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
