<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum StrikeReason: string
{
    case SellerNoShow = 'seller_no_show';
    case BuyerNoShow = 'buyer_no_show';
    case ProhibitedItem = 'prohibited_item';
    case Harassment = 'harassment';
    case ShillBidding = 'shill_bidding';
    case OffPlatformTrading = 'off_platform_trading';
    case Other = 'other';
}
