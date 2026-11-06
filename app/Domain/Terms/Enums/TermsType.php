<?php

declare(strict_types=1);

namespace App\Domain\Terms\Enums;

enum TermsType: string
{
    case General = 'general';
    case BiddingEscrow = 'bidding_escrow';
    case SellerAgreement = 'seller_agreement';
    case PrivacyPolicy = 'privacy_policy';
    case PickupDelivery = 'pickup_delivery';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'General Terms of Service',
            self::BiddingEscrow => 'Live Bidding & Escrow Terms',
            self::SellerAgreement => 'Seller & Auctioneer Agreement',
            self::PrivacyPolicy => 'Privacy Policy',
            self::PickupDelivery => 'Physical Pickup & Delivery Terms',
        };
    }

    /**
     * All string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
