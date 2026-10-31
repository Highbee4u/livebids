<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Identity\Enums\KycStatus;
use App\Domain\Identity\Models\Device;
use App\Domain\Identity\Models\KycRecord;
use App\Domain\Identity\Models\Strike;
use App\Domain\Ledger\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'name',
        'legal_name',
        'email',
        'password',
        'avatar_url',
        'kyc_status',
        'rating',
        'review_count',
        'strikes_count',
        'is_suspended',
        'is_banned',
        'withdrawal_pin_hash',
        'pickup_area',
        'fcm_token',
        'email_notifications_enabled',
        'sms_notifications_enabled',
        'push_notifications_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'withdrawal_pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'kyc_status' => KycStatus::class,
            'rating' => 'decimal:2',
            'review_count' => 'integer',
            'strikes_count' => 'integer',
            'is_suspended' => 'boolean',
            'is_banned' => 'boolean',
            'email_notifications_enabled' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'push_notifications_enabled' => 'boolean',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function kycRecords(): HasMany
    {
        return $this->hasMany(KycRecord::class);
    }

    public function latestKycRecord(): HasOne
    {
        return $this->hasOne(KycRecord::class)->latestOfMany();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function strikes(): HasMany
    {
        return $this->hasMany(Strike::class);
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === KycStatus::Verified;
    }

    public function isRestricted(): bool
    {
        return $this->is_suspended || $this->is_banned;
    }
}
