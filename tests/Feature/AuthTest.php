<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Models\OtpCode;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_request_phone_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '08012345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'expires_at']);

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '+2348012345678',
            'purpose' => 'login',
        ]);
    }

    public function test_can_verify_otp_and_authenticate(): void
    {
        $phone = '+2348012345678';
        $code = '654321';

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => $code,
            'device_id' => 'device-test-123',
            'platform' => 'android',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'is_new_user',
                'user' => ['id', 'phone', 'kyc_status', 'roles'],
            ]);

        $this->assertDatabaseHas('users', ['phone' => $phone]);
        $this->assertDatabaseHas('devices', ['device_id' => 'device-test-123']);
        $this->assertDatabaseHas('wallets', ['available_balance' => 0]);
    }

    public function test_invalid_otp_is_rejected_and_increments_attempts(): void
    {
        $phone = '+2348012345678';

        $otp = OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make('111111'),
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'OTP_INVALID');

        $this->assertEquals(1, $otp->fresh()->attempts);
    }

    public function test_authenticated_user_can_view_profile_and_logout(): void
    {
        $user = User::factory()->create(['phone' => '+2348099999999']);
        $user->assignRole('buyer');

        $token = $user->createToken('test-token')->plainTextToken;

        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('user.phone', '+2348099999999');

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
