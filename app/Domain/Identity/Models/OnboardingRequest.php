<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\OnboardingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'phone',
        'status',
        'phone_verified_at',
        'attempts',
        'device_id',
        'platform',
        'fcm_token',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OnboardingStatus::class,
            'phone_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isPhoneVerified(): bool
    {
        return ! is_null($this->phone_verified_at) && $this->status === OnboardingStatus::PhoneVerified;
    }

    public function isConverted(): bool
    {
        return $this->status === OnboardingStatus::Converted;
    }

    public function isExpired(): bool
    {
        return now()->gt($this->expires_at);
    }
}
