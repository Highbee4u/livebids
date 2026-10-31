<?php

declare(strict_types=1);

namespace App\Domain\Payment\Enums;

enum TopupStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Abandoned = 'abandoned';
}
