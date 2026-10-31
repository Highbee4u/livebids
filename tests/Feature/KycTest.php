<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Enums\KycStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Config::set('verification.default', 'null');
        Config::set('verification.drivers.null.simulate_success', true);
    }

    public function test_unverified_user_is_gated_from_wallet_endpoints(): void
    {
        $user = User::factory()->create([
            'kyc_status' => KycStatus::Pending,
        ]);
        $user->assignRole('buyer');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/wallet');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'KYC_VERIFICATION_REQUIRED');
    }

    public function test_user_can_submit_nin_and_become_verified(): void
    {
        $user = User::factory()->create([
            'kyc_status' => KycStatus::Pending,
        ]);
        $user->assignRole('buyer');

        // Test base64 1x1 png image
        $fakeSelfie = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/kyc/submit', [
                'nin' => '12345678901',
                'selfie_base64' => $fakeSelfie,
                'consent' => true,
                'date_of_birth' => '1995-05-15',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('kyc_record.status', 'verified')
            ->assertJsonPath('user.is_kyc_verified', true);

        $this->assertEquals(KycStatus::Verified, $user->fresh()->kyc_status);
        $this->assertTrue($user->fresh()->hasRole('seller'));

        // Verify photo was persisted securely to storage
        $record = $user->fresh()->latestKycRecord;
        $this->assertNotNull($record);
    }

    public function test_duplicate_nin_is_rejected_with_conflict(): void
    {
        $user1 = User::factory()->create(['kyc_status' => KycStatus::Pending]);
        $user1->assignRole('buyer');

        $user2 = User::factory()->create(['kyc_status' => KycStatus::Pending]);
        $user2->assignRole('buyer');

        // User 1 verifies NIN
        $this->actingAs($user1, 'sanctum')
            ->postJson('/api/v1/kyc/submit', [
                'nin' => '98765432101',
                'consent' => true,
            ])->assertStatus(200);

        // User 2 attempts to verify the SAME NIN
        $response = $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/kyc/submit', [
                'nin' => '98765432101',
                'consent' => true,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'DUPLICATE_IDENTITY_DETECTED');
    }

    public function test_missing_consent_is_rejected(): void
    {
        $user = User::factory()->create(['kyc_status' => KycStatus::Pending]);
        $user->assignRole('buyer');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/kyc/submit', [
                'nin' => '12345678901',
                'consent' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }
}
