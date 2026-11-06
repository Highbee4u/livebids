<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Identity\Enums\OnboardingStatus;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\Country;
use App\Domain\Identity\Models\Lga;
use App\Domain\Identity\Models\OnboardingRequest;
use App\Domain\Identity\Models\OtpCode;
use App\Domain\Identity\Models\State;
use App\Domain\Terms\Models\TermsAndCondition;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TermsAndConditionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(TermsAndConditionSeeder::class);
    }

    public function test_step_1_register_initiates_staging_request_without_creating_user(): void
    {
        $phone = '+2348011223344';

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => $phone,
            'device_id' => 'device_xyz_1',
            'platform' => 'android',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'PENDING_OTP');

        $identifier = $response->json('data.identifier');
        $this->assertNotEmpty($identifier);

        // Assert row created in staging table
        $this->assertDatabaseHas('onboarding_requests', [
            'identifier' => $identifier,
            'phone' => $phone,
            'status' => OnboardingStatus::PendingOtp->value,
        ]);

        // Core users table must be empty!
        $this->assertDatabaseMissing('users', [
            'phone' => $phone,
        ]);
    }

    public function test_step_1_register_rejects_already_onboarded_phone(): void
    {
        $existing = User::factory()->create([
            'phone' => '+2348099887766',
            'profile_completed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+2348099887766',
            'device_id' => 'device_xyz_1',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('error_code', 'PHONE_ALREADY_ATTACHED');
    }

    public function test_step_2_verify_otp_validates_code_and_marks_phone_verified(): void
    {
        $phone = '+2348055667788';

        // 1. Create staging request
        $staging = OnboardingRequest::create([
            'identifier' => '550e8400-e29b-41d4-a716-446655440000',
            'phone' => $phone,
            'status' => OnboardingStatus::PendingOtp,
            'expires_at' => now()->addMinutes(10),
        ]);

        // 2. Create OTP code
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make('654321'),
            'purpose' => OtpPurpose::Login,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        // 3. Test wrong code fails
        $wrongResponse = $this->postJson('/api/v1/auth/register/verify-otp', [
            'identifier' => $staging->identifier,
            'code' => '000000',
        ]);
        $wrongResponse->assertStatus(422)
            ->assertJsonPath('error_code', 'OTP_INVALID');

        // 4. Test correct code succeeds
        $correctResponse = $this->postJson('/api/v1/auth/register/verify-otp', [
            'identifier' => $staging->identifier,
            'code' => '654321',
        ]);

        $correctResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'PENDING_PROFILE');

        // Verify status in staging table
        $staging->refresh();
        $this->assertEquals(OnboardingStatus::PhoneVerified, $staging->status);
        $this->assertNotNull($staging->phone_verified_at);

        // Core users table must STILL be empty!
        $this->assertDatabaseMissing('users', ['phone' => $phone]);
    }

    public function test_step_3_profile_setup_rejects_underage_users_under_18(): void
    {
        $staging = OnboardingRequest::create([
            'identifier' => '550e8400-e29b-41d4-a716-446655440001',
            'phone' => '+2348077889900',
            'status' => OnboardingStatus::PhoneVerified,
            'phone_verified_at' => now(),
            'expires_at' => now()->addHours(1),
        ]);

        $nigeria = Country::where('iso2', 'NG')->first();
        $lagos = State::where('name', 'Lagos')->first();
        $ikeja = Lga::where('name', 'Ikeja')->where('state_id', $lagos->id)->first();

        // 17 years old
        $underageDob = now()->subYears(17)->toDateString();

        $response = $this->postJson('/api/v1/auth/profile/setup', [
            'identifier' => $staging->identifier,
            'first_name' => 'Young',
            'last_name' => 'Bidder',
            'date_of_birth' => $underageDob,
            'country_id' => $nigeria->id,
            'state_id' => $lagos->id,
            'lga_id' => $ikeja->id,
            'street_address' => '12 Allen Avenue',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_step_3_profile_setup_rejects_lga_not_belonging_to_state(): void
    {
        $staging = OnboardingRequest::create([
            'identifier' => '550e8400-e29b-41d4-a716-446655440002',
            'phone' => '+2348077889911',
            'status' => OnboardingStatus::PhoneVerified,
            'phone_verified_at' => now(),
            'expires_at' => now()->addHours(1),
        ]);

        $nigeria = Country::where('iso2', 'NG')->first();
        $lagos = State::where('name', 'Lagos')->first();
        $kano = State::where('name', 'Kano')->first();
        $kanoLga = Lga::where('state_id', $kano->id)->first();

        $adultDob = now()->subYears(22)->toDateString();

        $response = $this->postJson('/api/v1/auth/profile/setup', [
            'identifier' => $staging->identifier,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => $adultDob,
            'country_id' => $nigeria->id,
            'state_id' => $lagos->id, // State is Lagos
            'lga_id' => $kanoLga->id, // LGA belongs to Kano!
            'street_address' => '44 Broad Street',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lga_id']);
    }

    public function test_step_3_profile_setup_successfully_converts_to_user_and_provisions_wallet(): void
    {
        $phone = '+2348088990011';
        $staging = OnboardingRequest::create([
            'identifier' => '550e8400-e29b-41d4-a716-446655440003',
            'phone' => $phone,
            'status' => OnboardingStatus::PhoneVerified,
            'phone_verified_at' => now(),
            'device_id' => 'device_abc_123',
            'platform' => 'ios',
            'expires_at' => now()->addHours(1),
        ]);

        $nigeria = Country::where('iso2', 'NG')->first();
        $lagos = State::where('name', 'Lagos')->first();
        $ikeja = Lga::where('name', 'Ikeja')->where('state_id', $lagos->id)->first();

        $adultDob = '1998-05-15'; // 28 years old

        $response = $this->postJson('/api/v1/auth/profile/setup', [
            'identifier' => $staging->identifier,
            'first_name' => 'Chidi',
            'last_name' => 'Okonkwo',
            'date_of_birth' => $adultDob,
            'email' => 'chidi@example.com',
            'country_id' => $nigeria->id,
            'state_id' => $lagos->id,
            'lga_id' => $ikeja->id,
            'city' => 'Ikeja',
            'street_address' => '10 Oba Akran Avenue',
            'postal_code' => '100001',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'ONBOARDING_COMPLETE')
            ->assertJsonPath('data.user.first_name', 'Chidi')
            ->assertJsonPath('data.user.last_name', 'Okonkwo')
            ->assertJsonPath('data.user.is_onboarded', true);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // 1. Verify User created in database
        $user = User::where('phone', $phone)->first();
        $this->assertNotNull($user);
        $this->assertEquals('Chidi Okonkwo', $user->name);
        $this->assertEquals('1998-05-15', $user->date_of_birth->toDateString());
        $this->assertTrue($user->isOnboarded());
        $this->assertTrue($user->hasRole('buyer'));

        // 2. Verify UserAddress created in database
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'country_id' => $nigeria->id,
            'state_id' => $lagos->id,
            'lga_id' => $ikeja->id,
            'street_address' => '10 Oba Akran Avenue',
            'is_primary' => 1,
        ]);

        // 3. Verify Ledger Wallet provisioned
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'available_balance' => 0,
            'locked_balance' => 0,
        ]);

        // 4. Verify Staging Request marked as converted
        $staging->refresh();
        $this->assertEquals(OnboardingStatus::Converted, $staging->status);

        // 5. Verify Automatic Initial Consent (General Terms & Privacy Policy)
        $this->assertDatabaseHas('user_terms_acceptances', [
            'user_id' => $user->id,
            'terms_id' => TermsAndCondition::where('type', 'general')->first()->id,
        ]);
        $this->assertDatabaseHas('user_terms_acceptances', [
            'user_id' => $user->id,
            'terms_id' => TermsAndCondition::where('type', 'privacy_policy')->first()->id,
        ]);
    }

    public function test_onboarding_gate_blocks_incomplete_users_from_protected_endpoints(): void
    {
        // User with verified phone but incomplete profile
        $incompleteUser = User::factory()->phoneVerifiedOnly()->create();

        // Attempting to access orders endpoint
        $response = $this->actingAs($incompleteUser, 'sanctum')
            ->getJson('/api/v1/orders');

        $response->assertStatus(403)
            ->assertJsonPath('code', 'ONBOARDING_INCOMPLETE');

        // Fully onboarded user succeeds
        $completeUser = User::factory()->create();

        $successResponse = $this->actingAs($completeUser, 'sanctum')
            ->getJson('/api/v1/orders');

        $successResponse->assertOk();
    }
}
