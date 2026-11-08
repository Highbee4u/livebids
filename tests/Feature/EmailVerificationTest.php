<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\OtpCode;
use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_verify_email_with_valid_otp_as_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'email_verified_at' => null,
        ]);

        $code = '456789';
        OtpCode::create([
            'identifier' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => OtpPurpose::EmailVerification,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verify', [
                'code' => $code,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Email address verified successfully.')
            ->assertJsonPath('data.status', 'EMAIL_VERIFIED')
            ->assertJsonPath('data.user.is_email_verified', true);

        $user->refresh();
        $this->assertTrue($user->isEmailVerified());
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => $user->email,
            'purpose' => OtpPurpose::EmailVerification->value,
        ]);
        $this->assertNotNull(OtpCode::where('identifier', $user->email)->first()->used_at);
    }

    public function test_can_verify_email_unauthenticated_using_user_identifier(): void
    {
        $user = User::factory()->create([
            'email' => 'guest@example.com',
            'email_verified_at' => null,
        ]);

        $code = '654321';
        OtpCode::create([
            'identifier' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => OtpPurpose::EmailVerification,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'identifier' => $user->identifier,
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'EMAIL_VERIFIED');

        $user->refresh();
        $this->assertTrue($user->isEmailVerified());
    }

    public function test_verify_email_rejects_invalid_code_and_increments_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'email_verified_at' => null,
        ]);

        $otp = OtpCode::create([
            'identifier' => $user->email,
            'code_hash' => Hash::make('123456'),
            'purpose' => OtpPurpose::EmailVerification,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verify', [
                'code' => '999999',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error_code', 'OTP_INVALID');

        $otp->refresh();
        $this->assertEquals(1, $otp->attempts);
        $this->assertNull($otp->used_at);
    }

    public function test_verify_email_rejects_expired_code(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'email_verified_at' => null,
        ]);

        OtpCode::create([
            'identifier' => $user->email,
            'code_hash' => Hash::make('123456'),
            'purpose' => OtpPurpose::EmailVerification,
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/verify', [
                'code' => '123456',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'OTP_EXPIRED');
    }

    public function test_resend_verification_email_queues_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'PENDING_EMAIL_VERIFICATION')
            ->assertJsonPath('data.email', $user->email);

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => $user->email,
            'purpose' => OtpPurpose::EmailVerification->value,
        ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_email_fails_if_already_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend');

        $response->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('error_code', 'EMAIL_ALREADY_VERIFIED');
    }
}
