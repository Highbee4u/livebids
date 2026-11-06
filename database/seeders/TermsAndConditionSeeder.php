<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Terms\Enums\TermsType;
use App\Domain\Terms\Models\TermsAndCondition;
use Illuminate\Database\Seeder;

class TermsAndConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultTerms = [
            [
                'title' => 'LiveBids General Terms of Service',
                'slug' => 'general-v1-0',
                'type' => TermsType::General->value,
                'version' => '1.0',
                'content' => "# LiveBids General Terms of Service\n\nWelcome to LiveBids. By registering an account and accessing our platform, you agree to be bound by these General Terms of Service. You must be at least 18 years old to trade on LiveBids. All transactions are subject to identity verification and platform moderation.",
                'summary_of_changes' => 'Initial platform terms of service release.',
                'is_active' => true,
                'is_required' => true,
                'effective_date' => now()->subDay(),
            ],
            [
                'title' => 'Live Auction & Escrow Settlement Policy',
                'slug' => 'bidding-escrow-v1-0',
                'type' => TermsType::BiddingEscrow->value,
                'version' => '1.0',
                'content' => "# Live Auction Bidding & Escrow Policy\n\nAll bids placed during live video auction sessions are legally binding contracts. Winning bidders must fund escrow or settle within the declared checkout window. Funds remain locked in platform double-entry escrow until physical item handover is confirmed via OTP code exchange.",
                'summary_of_changes' => 'Initial real-time bidding and escrow settlement rules.',
                'is_active' => true,
                'is_required' => true,
                'effective_date' => now()->subDay(),
            ],
            [
                'title' => 'LiveBids Privacy & Data Protection Policy',
                'slug' => 'privacy-policy-v1-0',
                'type' => TermsType::PrivacyPolicy->value,
                'version' => '1.0',
                'content' => "# LiveBids Privacy Policy\n\nWe process personal data, including telephone numbers, physical pickup addresses, and National Identity Number (NIN) verification records, exclusively for account security, KYC compliance, and transaction settlement. Data is encrypted in transit and at rest.",
                'summary_of_changes' => 'Initial privacy and data handling guidelines.',
                'is_active' => true,
                'is_required' => true,
                'effective_date' => now()->subDay(),
            ],
            [
                'title' => 'Seller & Auctioneer Operating Agreement',
                'slug' => 'seller-agreement-v1-0',
                'type' => TermsType::SellerAgreement->value,
                'version' => '1.0',
                'content' => "# Seller & Auctioneer Operating Agreement\n\nSellers on LiveBids agree to accurately represent catalog items during live video broadcasts. Reserve prices are confidential and binding. Sellers commit to fulfilling winning orders within the designated dispatch window and are subject to seller penalty strikes for abandoned live streams or misrepresented goods.",
                'summary_of_changes' => 'Initial seller obligations and auction broadcast rules.',
                'is_active' => true,
                'is_required' => true,
                'effective_date' => now()->subDay(),
            ],
            [
                'title' => 'Physical Item Inspection & Handover Protocol',
                'slug' => 'pickup-delivery-v1-0',
                'type' => TermsType::PickupDelivery->value,
                'version' => '1.0',
                'content' => "# Physical Item Inspection & Handover Protocol\n\nBuyers must inspect physical items at designated pickup locations before sharing the 6-digit handover code. Providing the handover code signals irreversible acceptance and authorizes immediate release of escrow funds to the seller. In cases of damaged or misrepresented items, buyers must open a dispute prior to sharing the code.",
                'summary_of_changes' => 'Initial physical pickup verification and OTP settlement guidelines.',
                'is_active' => true,
                'is_required' => true,
                'effective_date' => now()->subDay(),
            ],
        ];

        foreach ($defaultTerms as $terms) {
            TermsAndCondition::firstOrCreate(
                ['type' => $terms['type'], 'version' => $terms['version']],
                $terms
            );
        }
    }
}
