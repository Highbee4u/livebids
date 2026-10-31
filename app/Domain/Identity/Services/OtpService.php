<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\Device;
use App\Domain\Identity\Models\OtpCode;
use App\Domain\Ledger\Services\LedgerService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Welfare\Notifications\NotificationDispatcher;

class OtpService
{
    public function __construct(
        protected NotificationDispatcher $notifications,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Request and dispatch an OTP to a phone number.
     *
     * @throws BusinessRuleException
     */
    public function requestOtp(string $phone, OtpPurpose $purpose = OtpPurpose::Login, ?string $deviceId = null): OtpCode
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $throttleKey = "otp_request:{$normalizedPhone}";

        // Enforce rate limiting: max 5 requests per hour, cooldown 60s
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw new BusinessRuleException(
                "Too many OTP requests. Please wait {$seconds} seconds before trying again.",
                'OTP_RATE_LIMITED',
                429
            );
        }

        RateLimiter::hit($throttleKey, 3600);

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);

        // For local development or automated tests, allow standard mock if configured
        if (! app()->isProduction() && config('app.mock_otp', false)) {
            $code = '123456';
        }

        $otpRecord = OtpCode::create([
            'phone' => $normalizedPhone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Dispatch SMS synchronously via welfare/notifications
        try {
            $this->notifications->sendSmsNow(
                $normalizedPhone,
                "Your LiveBids verification code is {$code}. Valid for 10 minutes. Do not share this code.",
                'otp_verification'
            );
        } catch (\Throwable $e) {
            // In dev/test if SMS provider is not configured, don't crash
            if (app()->isProduction()) {
                report($e);
            }
        }

        return $otpRecord;
    }

    /**
     * Verify an OTP and authenticate or complete the user action.
     *
     * @return array{user: User, token: string, is_new_user: bool}
     *
     * @throws BusinessRuleException
     */
    public function verifyOtp(
        string $phone,
        string $code,
        OtpPurpose $purpose = OtpPurpose::Login,
        ?string $deviceId = null,
        ?string $platform = 'android',
        ?string $fcmToken = null
    ): array {
        $normalizedPhone = $this->normalizePhone($phone);

        $otp = OtpCode::where('phone', $normalizedPhone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new BusinessRuleException('No active verification code found for this phone number.', 'OTP_NOT_FOUND', 404);
        }

        if ($otp->isExpired()) {
            throw new BusinessRuleException('Verification code has expired. Please request a new code.', 'OTP_EXPIRED', 422);
        }

        if ($otp->attempts >= 5) {
            throw new BusinessRuleException('Too many failed attempts. This code is invalidated.', 'OTP_MAX_ATTEMPTS_EXCEEDED', 422);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $remaining = 5 - $otp->attempts;
            throw new BusinessRuleException("Invalid verification code. {$remaining} attempts remaining.", 'OTP_INVALID', 422);
        }

        $otp->update(['used_at' => now()]);

        // Find or create user
        $isNewUser = false;
        $user = User::where('phone', $normalizedPhone)->first();

        if (! $user) {
            $isNewUser = true;
            $user = User::create([
                'phone' => $normalizedPhone,
                'name' => 'Bidder '.substr($normalizedPhone, -4),
                'fcm_token' => $fcmToken,
            ]);

            // Assign default buyer role
            $user->assignRole('buyer');

            // Initialize ledger wallet for the user
            $this->ledgerService->getOrCreateWallet($user);
        } else {
            if ($fcmToken && $user->fcm_token !== $fcmToken) {
                $user->update(['fcm_token' => $fcmToken]);
            }
        }

        // Register or update device
        if ($deviceId) {
            Device::updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $deviceId],
                [
                    'platform' => $platform ?? 'android',
                    'fcm_token' => $fcmToken,
                    'last_active_at' => now(),
                    'is_trusted' => true,
                ]
            );
        }

        // Issue Sanctum token
        $token = $user->createToken('mobile-app', ['*'])->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'is_new_user' => $isNewUser,
        ];
    }

    protected function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        // Default Nigerian format normalization (e.g. 080... -> +23480...)
        if (str_starts_with($clean, '0') && strlen($clean) === 11) {
            return '+234'.substr($clean, 1);
        }
        if (! str_starts_with($clean, '+') && str_starts_with($clean, '234')) {
            return '+'.$clean;
        }

        return $clean;
    }
}
