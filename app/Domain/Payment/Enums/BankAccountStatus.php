<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum BankAccountStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case NameMismatch = 'name_mismatch';
}
