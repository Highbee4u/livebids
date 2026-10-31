<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Support = 'support';
}
