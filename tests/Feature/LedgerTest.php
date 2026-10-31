<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Ledger\Invariants\LedgerInvariantChecker;
use App\Domain\Ledger\Services\LedgerService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected LedgerInvariantChecker $invariantChecker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->ledgerService = app(LedgerService::class);
        $this->invariantChecker = app(LedgerInvariantChecker::class);
    }

    public function test_topup_credit_is_strictly_idempotent(): void
    {
        $user = User::factory()->verified()->create();
        $amount = 5000000; // ₦50,000 in kobo
        $idempotencyKey = 'topup_test_idemp_key_001';

        // Post top-up 10 times with the same idempotency key
        for ($i = 0; $i < 10; $i++) {
            $this->ledgerService->creditTopup($user, $amount, 'REF_001', $idempotencyKey);
        }

        $wallet = $this->ledgerService->getOrCreateWallet($user);

        // Wallet should only be credited ONCE
        $this->assertEquals($amount, $wallet->available_balance);
        $this->assertEquals(0, $wallet->locked_balance);

        // Verify invariant
        $check = $this->invariantChecker->check();
        $this->assertTrue($check['passed']);
    }

    public function test_available_balance_never_goes_negative(): void
    {
        $user = User::factory()->verified()->create();
        $this->ledgerService->creditTopup($user, 1000000, 'REF_002', 'idemp_topup_002'); // ₦10,000

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Insufficient available wallet balance.');

        // Attempt to lock ₦15,000 when available is ₦10,000
        $this->ledgerService->lockFunds($user, 1500000, 'auction-1', 'bid-1', 'idemp_lock_fail');
    }

    public function test_complete_auction_money_cycle_and_reconciliation(): void
    {
        $buyer = User::factory()->verified()->create();
        $seller = User::factory()->verified()->create();

        $salePriceKobo = 5000000; // ₦50,000
        $commissionKobo = 500000;  // 10% = ₦5,000
        $orderId = 'ORD-999';
        $bidId = 'BID-888';

        // 1. Buyer tops up ₦50,000
        $this->ledgerService->creditTopup($buyer, $salePriceKobo, 'PAY-1', 'idemp_1');
        $buyerWallet = $this->ledgerService->getOrCreateWallet($buyer);
        $this->assertEquals(5000000, $buyerWallet->available_balance);

        // 2. Buyer bids ₦50,000 (locks funds)
        $this->ledgerService->lockFunds($buyer, $salePriceKobo, 'AUC-1', $bidId, 'idemp_2');
        $buyerWallet->refresh();
        $this->assertEquals(0, $buyerWallet->available_balance);
        $this->assertEquals(5000000, $buyerWallet->locked_balance);

        // 3. Seller chooses buyer's bid (funds move to escrow)
        $this->ledgerService->moveLockedToEscrow($buyer, $salePriceKobo, $orderId, $bidId, 'idemp_3');
        $buyerWallet->refresh();
        $this->assertEquals(0, $buyerWallet->available_balance);
        $this->assertEquals(0, $buyerWallet->locked_balance);

        // 4. Handover confirmed: Release escrow to seller minus 10% commission
        $this->ledgerService->releaseEscrow($seller, $salePriceKobo, $commissionKobo, $orderId, 'idemp_4');
        $sellerWallet = $this->ledgerService->getOrCreateWallet($seller);
        $this->assertEquals(4500000, $sellerWallet->available_balance);

        // 5. Invariant check: all entries must strictly sum to zero net
        $check = $this->invariantChecker->check();
        $this->assertTrue($check['passed']);
        $this->assertEmpty($check['errors']);
        $this->assertEquals($check['total_debits'], $check['total_credits']);
    }
}
