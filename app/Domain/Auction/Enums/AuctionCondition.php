<?php

declare(strict_types=1);

namespace App\Domain\Auction\Enums;

enum AuctionCondition: string
{
    case BrandNew = 'new';
    case LikeNew = 'like_new';
    case UsedFair = 'used_fair';
    case Refurbished = 'refurbished';
}
