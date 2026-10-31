<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum LedgerAccount: string
{
    // User Accounts
    case Available = 'available';
    case Locked = 'locked';

    // System Accounts
    case Escrow = 'escrow';
    case PlatformRevenue = 'platform_revenue';
    case ProviderClearing = 'provider_clearing';
}
