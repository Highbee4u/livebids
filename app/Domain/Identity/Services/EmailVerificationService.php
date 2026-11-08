<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Models\OtpCode;
use App\Domain\Identity\Notifications\VerifyEmailNotification;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationService
{
    /**
     * Send or resend an email verification OTP code to the given user.
     *
     * @throws BusinessRuleException
     */
    public function sendVerificationCode(User $user): OtpCode
    {
        if (empty($user->email)) {
            throw new BusinessRuleException(
                'User does not have an email address associated with their account.',
                'NO_EMAIL_PROVIDED',
                422
            );
        }

        // Rate limiting: 5 requests per hour per user email
        $throttleKey = "email_otp:{$user->email}";
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw new BusinessRuleException(
                "Too many verification requests. Please wait {$seconds} seconds before trying again.",
                'OTP_RATE_LIMITED',
                429
            );
        }
        RateLimiter::hit($throttleKey, 3600);

        // Invalidate any active previous codes for this email and purpose
        OtpCode::where('identifier', $user->email)
            ->where('purpose', OtpPurpose::EmailVerification)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);
        if (! app()->isProduction() && config('app.mock_otp', false)) {
            $code = '123456';
        }

        $otpRecord = OtpCode::create([
            'identifier' => $user->email,
            'code_hash' => Hash::make($code),
            'purpose' => OtpPurpose::EmailVerification,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Dispatch queued email notification
        try {
            Notification::send(
                $user,
                new VerifyEmailNotification(
                    name: $user->first_name ?: $user->name,
                    otp: $code,
                    expiresInMinutes: 15
                )
            );
        } catch (\Throwable $e) {
            if (app()->isProduction()) {
                report($e);
            }
        }

        return $otpRecord;
    }

    /**
     * Verify the 6-digit code and mark the user's email as verified.
     *
     * @throws BusinessRuleException
     */
    public function verifyEmail(User $user, string $code): User
    {
        if ($user->isEmailVerified()) {
            return $user;
        }

        if (empty($user->email)) {
            throw new BusinessRuleException(
                'User does not have an email address associated with their account.',
                'NO_EMAIL_PROVIDED',
                422
            );
        }

        $otp = OtpCode::where('identifier', $user->email)
            ->where('purpose', OtpPurpose::EmailVerification)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $otp) {
            throw new BusinessRuleException(
                'No active verification code found for this email address.',
                'OTP_NOT_FOUND',
                404
            );
        }

        if ($otp->isExpired()) {
            throw new BusinessRuleException(
                'Verification code has expired. Please request a new code.',
                'OTP_EXPIRED',
                422
            );
        }

        if ($otp->attempts >= 5) {
            throw new BusinessRuleException(
                'Too many failed attempts. Please request a new verification code.',
                'OTP_MAX_ATTEMPTS_EXCEEDED',
                422
            );
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $remaining = 5 - $otp->attempts;
            throw new BusinessRuleException(
                "Invalid verification code. {$remaining} attempts remaining.",
                'OTP_INVALID',
                422
            );
        }

        $otp->update(['used_at' => now()]);
        $user->update(['email_verified_at' => now()]);

        return $user->refresh();
    }

    /**
     * Resend verification email to user.
     *
     * @throws BusinessRuleException
     */
    public function resendVerificationCode(User $user): array
    {
        if ($user->isEmailVerified()) {
            throw new BusinessRuleException(
                'Your email address has already been verified.',
                'EMAIL_ALREADY_VERIFIED',
                409
            );
        }

        $this->sendVerificationCode($user);

        return [
            'email' => $user->email,
            'status' => 'PENDING_EMAIL_VERIFICATION',
            'expires_in_seconds' => 900,
            'message' => 'Verification email resent successfully.',
        ];
    }
}
