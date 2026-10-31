<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auction\Enums\AuctionStatus;
use App\Domain\Auction\Models\Auction;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Identity\Enums\KycStatus;
use App\Domain\Identity\Models\KycRecord;
use App\Domain\Ledger\Invariants\LedgerInvariantChecker;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
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

    public function test_non_admin_is_forbidden_from_admin_console(): void
    {
        $regularUser = User::factory()->verified()->create();
        $regularUser->assignRole('buyer');

        $response = $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_persisted_nin_photo_and_action_is_audit_logged(): void
    {
        Storage::fake('local');

        $admin = User::factory()->verified()->create();
        $admin->assignRole('admin');

        $user = User::factory()->verified()->create();

        // Create a fake persisted image file in storage
        $photoPath = "private/kyc/photos/nin_{$user->id}_test.jpg";
        Storage::disk('local')->put($photoPath, 'fake-jpeg-binary-content');

        $kycRecord = KycRecord::create([
            'user_id' => $user->id,
            'status' => KycStatus::Verified,
            'nin_hash' => hash('sha256', 'test_nin'),
            'nin_photo_path' => $photoPath,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/admin/kyc/{$kycRecord->id}/nin-photo");

        $response->assertStatus(200);

        // Verify audit log entry was created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'viewed_nin_photo',
            'target_type' => 'KycRecord',
            'target_id' => (string) $kycRecord->id,
        ]);
    }

    public function test_dual_approver_maker_checker_prohibits_author_from_approving_own_adjustment(): void
    {
        $admin1 = User::factory()->verified()->create();
        $admin1->assignRole('admin');

        $targetUser = User::factory()->verified()->create();

        // Admin 1 proposes manual ledger credit of ₦20,000 to user
        $proposeRes = $this->actingAs($admin1, 'sanctum')
            ->postJson('/api/v1/admin/ledger/propose-adjustment', [
                'target_user_id' => $targetUser->id,
                'account' => 'available',
                'direction' => 'credit',
                'amount_kobo' => 2000000,
                'reason' => 'Goodwill refund for verified platform downtime incident',
            ]);

        $proposeRes->assertStatus(201);
        $adjustmentId = $proposeRes->json('adjustment.id');

        // Admin 1 attempts to approve their OWN adjustment -> MUST FAIL WITH 403
        $selfApproveRes = $this->actingAs($admin1, 'sanctum')
            ->postJson("/api/v1/admin/ledger/adjustments/{$adjustmentId}/approve");

        $selfApproveRes->assertStatus(403)
            ->assertJsonPath('error_code', 'SELF_APPROVAL_PROHIBITED');

        // Target user wallet balance must still be 0
        $this->assertEquals(0, $this->ledgerService->getOrCreateWallet($targetUser)->available_balance);

        // Admin 2 (second checker) approves the adjustment -> MUST SUCCEED
        $admin2 = User::factory()->verified()->create();
        $admin2->assignRole('admin');

        $checkerRes = $this->actingAs($admin2, 'sanctum')
            ->postJson("/api/v1/admin/ledger/adjustments/{$adjustmentId}/approve");

        $checkerRes->assertStatus(200)
            ->assertJsonPath('adjustment.status', 'approved');

        // Target user wallet balance is now credited ₦20,000!
        $this->assertEquals(2000000, $this->ledgerService->getOrCreateWallet($targetUser)->fresh()->available_balance);

        // Verify invariant parity holds
        $check = $this->invariantChecker->check();
        $this->assertTrue($check['passed']);
    }

    public function test_admin_can_resolve_dispute_with_partial_split_settlement(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->verified()->create();
        $buyer = User::factory()->verified()->create();

        $salePrice = 10000000; // ₦100,000
        $commission = 1000000;  // ₦10,000

        // Buyer tops up ₦100,000
        $this->ledgerService->creditTopup($buyer, $salePrice, 'P-DISP', 'top_disp');

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Disputed Drone Auction',
            'pickup_area' => 'Abuja',
            'starting_bid' => $salePrice,
            'status' => AuctionStatus::Sold,
        ]);

        $order = Order::create([
            'auction_id' => $auction->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'amount' => $salePrice,
            'commission_rate' => 10,
            'commission_amount' => $commission,
            'seller_payout_amount' => $salePrice - $commission,
            'status' => OrderStatus::Disputed,
            'handover_code_hash' => Hash::make('123456'),
            'deadline_at' => now()->addHours(72),
        ]);

        // 1. Lock bidder funds first
        $this->ledgerService->lockFunds($buyer, $salePrice, (string) $auction->id, 'bid-disp-1', 'lock_disp_1');

        // 2. Move locked funds to escrow
        $this->ledgerService->moveLockedToEscrow($buyer, $salePrice, (string) $order->id, 'bid-disp-1', 'escrow_disp_1');

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'opened_by' => $buyer->id,
            'reason' => 'Item minor cosmetic scratch not described in live video',
            'status' => 'open',
        ]);

        // Admin resolves with 50/50 split (₦45,000 to buyer, ₦45,000 to seller, ₦10,000 commission)
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/resolve", [
                'outcome' => 'resolved_split',
                'buyer_split_kobo' => 4500000,
                'seller_split_kobo' => 4500000,
                'notes' => 'Settled mutually via split.',
            ]);

        $response->assertStatus(200);

        // Buyer wallet credited ₦45,000
        $this->assertEquals(4500000, $this->ledgerService->getOrCreateWallet($buyer)->fresh()->available_balance);
        // Seller wallet credited ₦45,000
        $this->assertEquals(4500000, $this->ledgerService->getOrCreateWallet($seller)->fresh()->available_balance);

        // Global ledger parity passes!
        $check = $this->invariantChecker->check();
        $this->assertTrue($check['passed']);
    }

    public function test_admin_can_emergency_kill_live_stream(): void
    {
        $admin = User::factory()->verified()->create();
        $admin->assignRole('admin');

        $seller = User::factory()->verified()->create();

        $auction = Auction::create([
            'seller_id' => $seller->id,
            'title' => 'Rogue Stream Test',
            'pickup_area' => 'Ikeja',
            'starting_bid' => 1000000,
            'status' => AuctionStatus::Live,
            'live_started_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/auctions/{$auction->id}/kill-stream");

        $response->assertStatus(200)
            ->assertJsonPath('killed', true);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'killed_live_stream',
            'target_type' => 'Auction',
            'target_id' => (string) $auction->id,
        ]);
    }
}
