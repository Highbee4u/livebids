<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Models\Auction;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_seller_can_create_and_schedule_auction(): void
    {
        $seller = User::factory()->verified()->create();
        $seller->assignRole('seller');

        $response = $this->actingAs($seller, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_auc_create_1')
            ->postJson('/api/v1/auctions', [
                'title' => 'iPhone 15 Pro Max 256GB Natural Titanium',
                'description' => 'Mint condition, pristine battery health 99%, with receipt.',
                'condition' => 'like_new',
                'starting_bid' => 50000000, // ₦500,000 in kobo
                'reserve_price' => 60000000, // ₦600,000 hidden reserve
                'pickup_area' => 'Ikeja City Mall, Lagos',
                'scheduled_start_at' => now()->addHours(2)->toIso8601String(),
                'live_duration_seconds' => 180,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('auction.title', 'iPhone 15 Pro Max 256GB Natural Titanium')
            ->assertJsonPath('auction.reserve_price_kobo', 60000000); // visible to creator

        $this->assertDatabaseHas('auctions', [
            'seller_id' => $seller->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_public_viewer_cannot_see_hidden_reserve_price(): void
    {
        $seller = User::factory()->verified()->create();
        $buyer = User::factory()->verified()->create();

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'MacBook Pro M3 Max',
            'pickup_area' => 'Victoria Island, Lagos',
            'starting_bid' => 100000000,
            'reserve_price' => 150000000, // ₦1.5M hidden reserve
            'scheduled_start_at' => now()->addHours(3),
            'status' => AuctionStatus::Scheduled,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/v1/auctions/{$auction->id}");

        $response->assertStatus(200)
            ->assertJsonPath('auction.reserve_price_kobo', null); // hidden!
    }

    public function test_seller_can_start_live_session_and_receive_publisher_token(): void
    {
        $seller = User::factory()->verified()->create();
        $seller->assignRole('seller');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Sony PlayStation 5 Console',
            'pickup_area' => 'Yaba, Lagos',
            'starting_bid' => 40000000,
            'scheduled_start_at' => now()->addMinutes(2),
            'status' => AuctionStatus::Scheduled,
        ]);

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/live/start");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'auction' => ['id', 'status'],
                'video' => ['token', 'ws_url', 'room'],
            ]);

        $this->assertEquals(AuctionStatus::Live, $auction->fresh()->status);
    }

    public function test_buyer_can_toggle_auction_reminder(): void
    {
        $seller = User::factory()->verified()->create();
        $buyer = User::factory()->verified()->create();

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Canon EOS R6 Camera',
            'pickup_area' => 'Lekki Phase 1',
            'starting_bid' => 80000000,
            'scheduled_start_at' => now()->addHours(4),
            'status' => AuctionStatus::Scheduled,
        ]);

        // 1. Subscribe
        $res1 = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/remind");

        $res1->assertStatus(200)->assertJsonPath('has_reminder', true);
        $this->assertDatabaseHas('reminders', ['user_id' => $buyer->id, 'auction_id' => $auction->id]);

        // 2. Unsubscribe
        $res2 = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/v1/auctions/{$auction->id}/remind");

        $res2->assertStatus(200)->assertJsonPath('has_reminder', false);
        $this->assertDatabaseMissing('reminders', ['user_id' => $buyer->id, 'auction_id' => $auction->id]);
    }
}
