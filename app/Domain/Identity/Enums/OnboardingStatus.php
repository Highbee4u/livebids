<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum OnboardingStatus: string
{
    case PendingOtp = 'pending_otp';
    case PhoneVerified = 'phone_verified';
    case Converted = 'converted';
    case Expired = 'expired';
}
