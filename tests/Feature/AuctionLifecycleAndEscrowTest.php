<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Models\Auction;
use App\Domain\Auction\Services\BiddingService;
use App\Domain\Ledger\Invariants\LedgerInvariantChecker;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionLifecycleAndEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected BiddingService $biddingService;

    protected LedgerInvariantChecker $invariantChecker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->ledgerService = app(LedgerService::class);
        $this->biddingService = app(BiddingService::class);
        $this->invariantChecker = app(LedgerInvariantChecker::class);
    }

    public function test_complete_auction_close_escrow_hold_and_handover_code_release(): void
    {
        $seller = User::factory()->verified()->create();
        $winner = User::factory()->verified()->create();
        $loser = User::factory()->verified()->create();

        // 1. Fund both bidders
        $this->ledgerService->creditTopup($winner, 10000000, 'P-WIN', 'top_win'); // ₦100,000
        $this->ledgerService->creditTopup($loser, 10000000, 'P-LOSE', 'top_lose'); // ₦100,000

        // 2. Start Live Auction
        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Apple iPad Pro 12.9 M2',
            'pickup_area' => 'Maryland Mall, Lagos',
            'starting_bid' => 4000000, // ₦40,000
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        // 3. Loser bids ₦40,000, Winner bids ₦50,000
        $bidLoser = $this->biddingService->placeBid($auction, $loser, 4000000);
        $bidWinner = $this->biddingService->placeBid($auction, $winner, 5000000);

        // Loser and Winner funds are currently locked
        $this->assertEquals(4000000, $this->ledgerService->getOrCreateWallet($loser)->locked_balance);
        $this->assertEquals(5000000, $this->ledgerService->getOrCreateWallet($winner)->locked_balance);

        // 4. Seller selects Winner's bid
        $response = $this->actingAs($seller, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_sel_win_1')
            ->postJson("/api/v1/auctions/{$auction->id}/select-winner", [
                'bid_id' => $bidWinner->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('order.status', 'awaiting_handover')
            ->assertJsonPath('order.amount_kobo', 5000000);

        $orderId = $response->json('order.id');
        $order = Order::findOrFail($orderId);

        // 5. Verify Invariants immediately after winner selection:
        // - Auction is Sold
        $this->assertEquals(AuctionStatus::Sold, $auction->fresh()->status);
        // - Loser funds were unlocked automatically back to available balance!
        $loserWallet = $this->ledgerService->getOrCreateWallet($loser)->fresh();
        $this->assertEquals(10000000, $loserWallet->available_balance);
        $this->assertEquals(0, $loserWallet->locked_balance);
        // - Winner locked funds moved to escrow
        $winnerWallet = $this->ledgerService->getOrCreateWallet($winner)->fresh();
        $this->assertEquals(5000000, $winnerWallet->available_balance);
        $this->assertEquals(0, $winnerWallet->locked_balance);

        // 6. Test Handover Code settlement:
        // Attempt wrong code -> rejected
        $wrongCodeRes = $this->actingAs($seller, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_code_wrong_1')
            ->postJson("/api/v1/orders/{$order->id}/confirm-handover", [
                'code' => '000000',
            ]);

        $wrongCodeRes->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_HANDOVER_CODE');

        // Buyer checks their order screen and views their 6-digit handover code
        $buyerOrderRes = $this->actingAs($winner, 'sanctum')->getJson("/api/v1/orders/{$order->id}");
        $buyerOrderRes->assertStatus(200);
        $validCode = $buyerOrderRes->json('order.handover_code');
        $this->assertNotNull($validCode);

        // Submit correct handover code
        $correctCodeRes = $this->actingAs($seller, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_code_correct_1')
            ->postJson("/api/v1/orders/{$order->id}/confirm-handover", [
                'code' => $validCode,
            ]);

        $correctCodeRes->assertStatus(200)
            ->assertJsonPath('order.status', 'completed');

        // 7. Verify Seller was paid ₦45,000 (₦50,000 - 10% commission ₦5,000)
        $sellerWallet = $this->ledgerService->getOrCreateWallet($seller)->fresh();
        $this->assertEquals(4500000, $sellerWallet->available_balance);
        $this->assertEquals(OrderStatus::Completed, $order->fresh()->status);

        // 8. Verify global double-entry ledger parity invariant
        $check = $this->invariantChecker->check();
        $this->assertTrue($check['passed']);
        $this->assertEmpty($check['errors']);
    }
}
