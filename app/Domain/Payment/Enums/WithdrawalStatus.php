<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Held = 'held'; // Manual review / fraud hold
    case Cancelled = 'cancelled';
}
