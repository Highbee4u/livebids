<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Events\BidPlaced;
use App\Domain\Auction\Models\Auction;
use App\Domain\Ledger\Services\LedgerService;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BiddingTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->ledgerService = app(LedgerService::class);
    }

    public function test_bidder_can_place_valid_bid_and_funds_are_locked_atomically(): void
    {
        Event::fake([BidPlaced::class]);

        $seller = User::factory()->verified()->create();
        $bidder = User::factory()->verified()->create();

        // Credit bidder wallet ₦100,000
        $this->ledgerService->creditTopup($bidder, 10000000, 'PAY-TOP', 'idemp_bid_top_1');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Vintage Rolex Submariner',
            'pickup_area' => 'Victoria Island',
            'starting_bid' => 5000000, // ₦50,000
            'scheduled_start_at' => now(),
            'live_started_at' => now(),
            'status' => AuctionStatus::Live,
        ]);

        $response = $this->actingAs($bidder, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", [
                'amount_kobo' => 5000000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('bid.amount_kobo', 5000000);

        // Check wallet locked funds in ledger
        $wallet = $this->ledgerService->getOrCreateWallet($bidder);
        $this->assertEquals(5000000, $wallet->available_balance);
        $this->assertEquals(5000000, $wallet->locked_balance);

        Event::assertDispatched(BidPlaced::class);
    }

    public function test_seller_cannot_bid_on_their_own_auction(): void
    {
        $seller = User::factory()->verified()->create();
        $this->ledgerService->creditTopup($seller, 10000000, 'PAY-1', 'idemp_seller_top');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Self Bid Prevention Test',
            'pickup_area' => 'Ikeja',
            'starting_bid' => 2000000,
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", [
                'amount_kobo' => 2000000,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SELF_BIDDING_PROHIBITED');
    }

    public function test_bid_below_minimum_increment_is_rejected(): void
    {
        $seller = User::factory()->verified()->create();
        $bidder1 = User::factory()->verified()->create();
        $bidder2 = User::factory()->verified()->create();

        $this->ledgerService->creditTopup($bidder1, 10000000, 'P1', 'idemp_b1');
        $this->ledgerService->creditTopup($bidder2, 10000000, 'P2', 'idemp_b2');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Increment Test Auction',
            'pickup_area' => 'Lekki',
            'starting_bid' => 5000000, // ₦50,000
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        // Bidder 1 bids ₦50,000
        $this->actingAs($bidder1, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount_kobo' => 5000000])
            ->assertStatus(201);

        // Next bid must be at least ₦50,000 + 5% (₦2,500) = ₦52,500 (5,250,000 kobo)
        // Bidder 2 attempts ₦51,000 -> must be rejected
        $response = $this->actingAs($bidder2, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount_kobo' => 5100000]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'BID_BELOW_MINIMUM');
    }

    public function test_raising_own_bid_locks_only_the_delta_difference(): void
    {
        $seller = User::factory()->verified()->create();
        $bidder = User::factory()->verified()->create();

        // Bidder has ₦100,000 in wallet
        $this->ledgerService->creditTopup($bidder, 10000000, 'P-DELTA', 'idemp_delta_1');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Delta Lock Test',
            'pickup_area' => 'Surulere',
            'starting_bid' => 2000000, // ₦20,000
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        // 1. Initial bid: ₦20,000
        $this->actingAs($bidder, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount_kobo' => 2000000])
            ->assertStatus(201);

        $wallet = $this->ledgerService->getOrCreateWallet($bidder);
        $this->assertEquals(8000000, $wallet->available_balance); // ₦80k remaining
        $this->assertEquals(2000000, $wallet->locked_balance);    // ₦20k locked

        // 2. Raise own bid to ₦35,000 (delta is +₦15,000)
        $this->actingAs($bidder, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/bids", ['amount_kobo' => 3500000])
            ->assertStatus(201);

        $wallet->refresh();
        $this->assertEquals(6500000, $wallet->available_balance); // ₦65k remaining (80k - 15k)
        $this->assertEquals(3500000, $wallet->locked_balance);    // ₦35k locked total
    }
}
