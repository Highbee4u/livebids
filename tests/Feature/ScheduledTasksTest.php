<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Models\Auction;
use App\Domain\Identity\Enums\KycStatus;
use App\Domain\Identity\Models\KycRecord;
use App\Domain\Identity\Models\Strike;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notification\Models\Reminder;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected User $buyer;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);

        $this->seller = User::factory()->create([
            'phone' => '+2348011112222',
            'name' => 'Seller Test',
        ]);
        $this->seller->assignRole('seller');

        KycRecord::create([
            'user_id' => $this->seller->id,
            'nin_hash' => hash('sha256', '12345678901'),
            'status' => KycStatus::Verified,
            'verified_at' => now(),
        ]);

        $this->buyer = User::factory()->create([
            'phone' => '+2348033334444',
            'name' => 'Buyer Test',
            'fcm_token' => 'fcm_token_test_123',
        ]);
        $this->buyer->assignRole('buyer');

        $this->ledgerService = app(LedgerService::class);
        $this->ledgerService->getOrCreateWallet($this->seller);
        $this->ledgerService->getOrCreateWallet($this->buyer);
    }

    public function test_auctions_mark_missed_command_transitions_expired_auctions_and_strikes_seller(): void
    {
        // 1. Create auction scheduled 15 minutes ago
        $expiredAuction = Auction::create([
            'seller_id' => $this->seller->id,
            'title' => 'Expired Vintage Camera',
            'pickup_area' => 'Ikeja',
            'starting_bid' => 100000,
            'scheduled_start_at' => now()->subMinutes(15),
            'status' => AuctionStatus::Scheduled,
        ]);

        // 2. Create another auction scheduled in the future
        $futureAuction = Auction::create([
            'seller_id' => $this->seller->id,
            'title' => 'Future iPhone',
            'pickup_area' => 'Lekki',
            'starting_bid' => 500000,
            'scheduled_start_at' => now()->addMinutes(30),
            'status' => AuctionStatus::Scheduled,
        ]);

        $this->artisan('auctions:mark-missed')
            ->expectsOutputToContain('Marked 1 auction(s) as missed and issued seller strike(s).')
            ->assertSuccessful();

        $expiredAuction->refresh();
        $futureAuction->refresh();

        $this->assertEquals(AuctionStatus::Missed, $expiredAuction->status);
        $this->assertEquals(AuctionStatus::Scheduled, $futureAuction->status);

        // Verify strike was issued
        $this->assertDatabaseHas('strikes', [
            'user_id' => $this->seller->id,
            'auction_id' => $expiredAuction->id,
        ]);
        $this->assertEquals(1, $this->seller->fresh()->strikes_count);
    }

    public function test_orders_auto_release_escrow_command_settles_orders_past_deadline(): void
    {
        // 1. Setup seller and buyer balances
        $this->ledgerService->creditTopup($this->buyer, 1000000, 'test_topup_1', 'idemp_topup_1');
        $this->ledgerService->lockFunds($this->buyer, 1000000, '1', '1', 'lock_1');
        $this->ledgerService->moveLockedToEscrow($this->buyer, 1000000, '1', '1', 'order_escrow_1');

        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'title' => 'Settled Auction Item',
            'pickup_area' => 'Yaba',
            'starting_bid' => 500000,
            'scheduled_start_at' => now()->subDays(2),
            'status' => AuctionStatus::Sold,
        ]);

        // Create expired order
        $order = Order::create([
            'auction_id' => $auction->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'amount' => 1000000,
            'commission_amount' => 100000, // 10%
            'seller_payout_amount' => 900000,
            'handover_code_hash' => Hash::make('123456'),
            'handover_code' => '123456',
            'status' => OrderStatus::AwaitingHandover,
            'deadline_at' => now()->subHours(2), // expired 2 hours ago
        ]);

        $this->artisan('orders:auto-release-escrow')
            ->expectsOutputToContain('Auto-released escrow for 1 order(s).')
            ->assertSuccessful();

        $order->refresh();
        $this->assertEquals(OrderStatus::Completed, $order->status);

        // Verify seller received net payout (₦10,000 - ₦1,000 commission = ₦9,000 = 900,000 kobo)
        $sellerWallet = $this->ledgerService->getOrCreateWallet($this->seller);
        $this->assertEquals(900000, $sellerWallet->available_balance);
    }

    public function test_auctions_send_reminders_command_dispatches_notifications_and_sets_notified_at(): void
    {
        $auction = Auction::create([
            'seller_id' => $this->seller->id,
            'title' => 'Reminder Test Watch',
            'pickup_area' => 'Victoria Island',
            'starting_bid' => 200000,
            'scheduled_start_at' => now()->addMinutes(10), // Starts in 10 mins
            'status' => AuctionStatus::Scheduled,
        ]);

        $reminder = Reminder::create([
            'user_id' => $this->buyer->id,
            'auction_id' => $auction->id,
            'notified_at' => null,
        ]);

        $this->artisan('auctions:send-reminders')
            ->expectsOutputToContain('Dispatched 1 auction reminder(s).')
            ->assertSuccessful();

        $reminder->refresh();
        $this->assertNotNull($reminder->notified_at);
    }
}
