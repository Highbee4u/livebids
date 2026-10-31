<?php

declare(strict_types=1);

namespace App\Domain\Auction\Enums;

enum BidStatus: string
{
    case Active = 'active';
    case Won = 'won';
    case Released = 'released';
}
