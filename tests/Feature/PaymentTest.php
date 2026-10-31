<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Enums\KycStatus;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payment\Models\Topup;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_initialize_topup_with_idempotency_header(): void
    {
        $user = User::factory()->verified()->create();
        $user->assignRole('buyer');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_topup_init_1')
            ->postJson('/api/v1/wallet/topups', [
                'amount_kobo' => 500000, // ₦5,000
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'topup' => ['id', 'amount_kobo', 'reference', 'status', 'checkout_url'],
            ]);

        $this->assertDatabaseHas('topups', [
            'user_id' => $user->id,
            'amount' => 500000,
            'status' => 'pending',
        ]);
    }

    public function test_paystack_charge_success_webhook_credits_wallet_and_is_replay_safe(): void
    {
        $user = User::factory()->verified()->create();
        $reference = 'TOP_TEST_REF_777';

        $topup = Topup::create([
            'user_id' => $user->id,
            'amount' => 1000000, // ₦10,000
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $webhookPayload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 999991,
                'reference' => $reference,
                'amount' => 1000000,
                'status' => 'success',
            ],
        ];

        // 1. First webhook delivery
        $response = $this->postJson('/api/v1/webhooks/paystack', $webhookPayload);
        $response->assertStatus(200);

        $wallet = app(LedgerService::class)->getOrCreateWallet($user);
        $this->assertEquals(1000000, $wallet->available_balance);
        $this->assertEquals('success', $topup->fresh()->status->value);

        // 2. Replay of the exact same webhook
        $replayResponse = $this->postJson('/api/v1/webhooks/paystack', $webhookPayload);
        $replayResponse->assertStatus(200)
            ->assertJsonPath('message', 'Event already processed.');

        // Wallet balance must NOT be double-credited
        $this->assertEquals(1000000, $wallet->fresh()->available_balance);
    }

    public function test_bank_account_registration_rejects_name_mismatch(): void
    {
        $user = User::factory()->create([
            'kyc_status' => KycStatus::Verified,
            'legal_name' => 'CHUKWUMA OBI',
        ]);
        $user->assignRole('seller');

        // Paystack mock returns 'TEST VERIFIED USER', which does not match 'CHUKWUMA OBI'
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'idemp_bank_test_1')
            ->postJson('/api/v1/bank-accounts/verify', [
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'BANK_NAME_MISMATCH');
    }
}
