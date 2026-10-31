<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum KycStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case FailedRetryable = 'failed_retryable';
    case FailedBlocked = 'failed_blocked';
    case ManualReview = 'manual_review';
}
