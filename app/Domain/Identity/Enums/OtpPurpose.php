<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Withdrawal = 'withdrawal';
    case PinReset = 'pin_reset';
}
