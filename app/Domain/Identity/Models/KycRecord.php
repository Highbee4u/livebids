<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\KycStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_ref',
        'status',
        'match_score',
        'verified_name',
        'nin_hash',
        'nin_photo_path',
        'selfie_photo_path',
        'consent_logged_at',
        'attempt_count',
        'cooldown_until',
        'rejection_reason',
        'raw_response',
    ];

    protected $hidden = [
        'nin_hash',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'status' => KycStatus::class,
            'match_score' => 'decimal:2',
            'consent_logged_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'attempt_count' => 'integer',
            'raw_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->status === KycStatus::Verified;
    }
}
