<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OnboardingStatus;
use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\Device;
use App\Domain\Identity\Models\OnboardingRequest;
use App\Domain\Identity\Models\OtpCode;
use App\Domain\Identity\Models\UserAddress;
use App\Domain\Ledger\Services\LedgerService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Welfare\Notifications\NotificationDispatcher;

class OnboardingService
{
    public function __construct(
        protected NotificationDispatcher $notifications,
        protected LedgerService $ledgerService,
        protected LocationService $locationService
    ) {}

    /**
     * Step 1: Initiate or resume onboarding registration with phone number.
     *
     * @throws BusinessRuleException
     */
    public function initiateRegistration(
        string $phone,
        ?string $deviceId = null,
        ?string $platform = 'android',
        ?string $fcmToken = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $normalizedPhone = $this->normalizePhone($phone);

        // 1. Check if user already exists and is fully onboarded
        $existingUser = User::where('phone', $normalizedPhone)->first();
        if ($existingUser && $existingUser->isOnboarded()) {
            throw new BusinessRuleException(
                'This phone number is already registered. Please proceed to login.',
                'PHONE_ALREADY_ATTACHED',
                409
            );
        }

        // 2. Check existing onboarding request
        $existingRequest = OnboardingRequest::where('phone', $normalizedPhone)
            ->where('status', '!=', OnboardingStatus::Converted)
            ->latest('id')
            ->first();

        if ($existingRequest && ! $existingRequest->isExpired() && $existingRequest->isPhoneVerified()) {
            return [
                'identifier' => $existingRequest->identifier,
                'status' => 'PENDING_PROFILE',
                'expires_in_seconds' => $existingRequest->expires_at->diffInSeconds(now()),
                'message' => 'Phone already verified. Please complete your profile and pickup address.',
            ];
        }

        // 3. Enforce rate limiting: 5 OTP requests per hour
        $throttleKey = "onboarding_otp:{$normalizedPhone}";
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw new BusinessRuleException(
                "Too many OTP requests. Please wait {$seconds} seconds before trying again.",
                'OTP_RATE_LIMITED',
                429
            );
        }
        RateLimiter::hit($throttleKey, 3600);

        // 4. Create or update OnboardingRequest
        $identifier = (string) Str::uuid();
        $onboardingRequest = OnboardingRequest::create([
            'identifier' => $identifier,
            'phone' => $normalizedPhone,
            'status' => OnboardingStatus::PendingOtp,
            'device_id' => $deviceId,
            'platform' => $platform ?? 'android',
            'fcm_token' => $fcmToken,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'expires_at' => now()->addMinutes(15),
        ]);

        // 5. Generate and dispatch OTP
        $code = (string) random_int(100000, 999999);
        if (! app()->isProduction() && config('app.mock_otp', false)) {
            $code = '123456';
        }

        OtpCode::create([
            'phone' => $normalizedPhone,
            'code_hash' => Hash::make($code),
            'purpose' => OtpPurpose::Login,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            $this->notifications->sendSmsNow(
                $normalizedPhone,
                "Your LiveBids onboarding verification code is {$code}. Valid for 10 minutes. Do not share this code.",
                'onboarding_otp'
            );
        } catch (\Throwable $e) {
            if (app()->isProduction()) {
                report($e);
            }
        }

        return [
            'identifier' => $onboardingRequest->identifier,
            'status' => 'PENDING_OTP',
            'expires_in_seconds' => 600,
            'message' => 'Verification code sent to your phone.',
        ];
    }

    /**
     * Step 2: Verify phone OTP for an active onboarding request.
     *
     * @throws BusinessRuleException
     */
    public function verifyOnboardingOtp(string $identifier, string $code): array
    {
        $onboardingRequest = OnboardingRequest::where('identifier', $identifier)->first();

        if (! $onboardingRequest) {
            throw new BusinessRuleException('Invalid onboarding identifier.', 'INVALID_IDENTIFIER', 404);
        }

        if ($onboardingRequest->isConverted()) {
            throw new BusinessRuleException('This onboarding request has already been completed.', 'ALREADY_CONVERTED', 409);
        }

        if ($onboardingRequest->isExpired()) {
            throw new BusinessRuleException('Onboarding session has expired. Please start registration again.', 'SESSION_EXPIRED', 422);
        }

        // Verify OTP against OtpCode table
        $otp = OtpCode::where('phone', $onboardingRequest->phone)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new BusinessRuleException('No active verification code found for this request.', 'OTP_NOT_FOUND', 404);
        }

        if ($otp->isExpired()) {
            throw new BusinessRuleException('Verification code has expired. Please request a new code.', 'OTP_EXPIRED', 422);
        }

        if ($otp->attempts >= 5) {
            throw new BusinessRuleException('Too many failed attempts. Please restart registration.', 'OTP_MAX_ATTEMPTS_EXCEEDED', 422);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $remaining = 5 - $otp->attempts;
            throw new BusinessRuleException("Invalid verification code. {$remaining} attempts remaining.", 'OTP_INVALID', 422);
        }

        $otp->update(['used_at' => now()]);

        $onboardingRequest->update([
            'phone_verified_at' => now(),
            'status' => OnboardingStatus::PhoneVerified,
            'expires_at' => now()->addHours(2), // 2 hours to finish profile
        ]);

        return [
            'identifier' => $onboardingRequest->identifier,
            'status' => 'PENDING_PROFILE',
            'message' => 'Phone number verified successfully. Please complete your profile and pickup address.',
        ];
    }

    /**
     * Step 3: Complete profile, validate 18+ age, save physical pickup address,
     * and atomically convert to a full User entity.
     *
     * @throws BusinessRuleException
     */
    public function completeProfile(string $identifier, array $data): array
    {
        $onboardingRequest = OnboardingRequest::where('identifier', $identifier)->first();

        if (! $onboardingRequest) {
            throw new BusinessRuleException('Invalid onboarding identifier.', 'INVALID_IDENTIFIER', 404);
        }

        if ($onboardingRequest->isConverted()) {
            throw new BusinessRuleException('This onboarding request has already been completed.', 'ALREADY_CONVERTED', 409);
        }

        if (! $onboardingRequest->isPhoneVerified()) {
            throw new BusinessRuleException('Please verify your phone number before completing your profile.', 'PHONE_NOT_VERIFIED', 422);
        }

        if ($onboardingRequest->isExpired()) {
            throw new BusinessRuleException('Onboarding session has expired. Please restart registration.', 'SESSION_EXPIRED', 422);
        }

        // 1. Validate Age (At least 18 years old)
        $dob = Carbon::parse($data['date_of_birth']);
        if ($dob->diffInYears(now()) < 18) {
            throw new BusinessRuleException(
                'You must be at least 18 years old to register and trade on LiveBids.',
                'UNDERAGE_NOT_PERMITTED',
                422
            );
        }

        // 2. Validate LGA belongs to State
        if (! $this->locationService->lgaBelongsToState((int) $data['lga_id'], (int) $data['state_id'])) {
            throw new BusinessRuleException(
                'The selected LGA does not belong to the selected State.',
                'INVALID_LOCATION',
                422
            );
        }

        // 3. Atomically Convert to Full User Entity
        return DB::transaction(function () use ($onboardingRequest, $data, $dob) {
            // Create user
            $user = User::create([
                'phone' => $onboardingRequest->phone,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'name' => "{$data['first_name']} {$data['last_name']}",
                'date_of_birth' => $dob->toDateString(),
                'email' => $data['email'] ?? null,
                'phone_verified_at' => $onboardingRequest->phone_verified_at,
                'profile_completed_at' => now(),
                'pickup_area' => $data['city'] ?? $data['street_address'],
                'fcm_token' => $data['fcm_token'] ?? $onboardingRequest->fcm_token,
            ]);

            // Assign default buyer role
            $user->assignRole('buyer');

            // Save physical pickup address
            UserAddress::create([
                'user_id' => $user->id,
                'country_id' => (int) $data['country_id'],
                'state_id' => (int) $data['state_id'],
                'lga_id' => (int) $data['lga_id'],
                'city' => $data['city'] ?? null,
                'street_address' => $data['street_address'],
                'postal_code' => $data['postal_code'] ?? null,
                'is_primary' => true,
                'verification_status' => 'unverified',
            ]);

            // Initialize Double-Entry Ledger Wallet
            $this->ledgerService->getOrCreateWallet($user);

            // Bind device if provided
            if ($onboardingRequest->device_id) {
                Device::updateOrCreate(
                    ['user_id' => $user->id, 'device_id' => $onboardingRequest->device_id],
                    [
                        'platform' => $onboardingRequest->platform,
                        'fcm_token' => $user->fcm_token,
                        'last_active_at' => now(),
                        'is_trusted' => true,
                    ]
                );
            }

            // Mark staging request as converted
            $onboardingRequest->update(['status' => OnboardingStatus::Converted]);

            // Issue Sanctum token
            $token = $user->createToken('mobile-app', ['*'])->plainTextToken;

            return [
                'user' => $user->load(['primaryAddress.country', 'primaryAddress.state', 'primaryAddress.lga']),
                'token' => $token,
                'status' => 'ONBOARDING_COMPLETE',
                'message' => 'Onboarding completed successfully! Welcome to LiveBids.',
            ];
        });
    }

    protected function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($clean, '0') && strlen($clean) === 11) {
            return '+234'.substr($clean, 1);
        }
        if (! str_starts_with($clean, '+') && str_starts_with($clean, '234')) {
            return '+'.$clean;
        }

        return $clean;
    }
}
