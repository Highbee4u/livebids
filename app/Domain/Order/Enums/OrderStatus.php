<?php

declare(strict_types=1);

namespace App\Domain\Order\Enums;

enum OrderStatus: string
{
    case AwaitingHandover = 'awaiting_handover';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case CancelledRefunded = 'cancelled_refunded';
}
