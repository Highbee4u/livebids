<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\IdentityServiceInterface;
use App\Domain\Identity\Enums\KycStatus;
use App\Domain\Identity\Models\KycRecord;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Welfare\Verification\Facades\Verification;

class IdentityService implements IdentityServiceInterface
{
    /**
     * Submit NIN and liveness photo for identity verification.
     *
     * @param  array{consent: bool, policy_version?: string}  $consentData
     *
     * @throws BusinessRuleException
     */
    public function submitNinLiveness(
        User $user,
        string $nin,
        ?string $selfieBase64 = null,
        array $consentData = [],
        ?string $dateOfBirth = null
    ): KycRecord {
        // 1. Consent Validation
        if (empty($consentData['consent'])) {
            throw new BusinessRuleException(
                'Explicit consent for identity lookup and biometric processing is required.',
                'CONSENT_REQUIRED',
                422
            );
        }

        // 2. Validate NIN format (11 digits)
        $cleanNin = preg_replace('/[^\d]/', '', $nin);
        if (strlen($cleanNin) !== 11) {
            throw new BusinessRuleException('NIN must be exactly 11 numeric digits.', 'INVALID_NIN_FORMAT', 422);
        }

        // 3. Compute deterministic cryptographic hash of NIN for duplicate checking
        $ninHash = hash_hmac('sha256', $cleanNin, config('app.key'));

        // 4. Enforce One-NIN-One-Account Invariant
        $existingVerified = KycRecord::where('nin_hash', $ninHash)
            ->where('status', KycStatus::Verified)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($existingVerified) {
            throw new BusinessRuleException(
                'This National Identity Number is already verified on another account. Duplicate accounts are prohibited.',
                'DUPLICATE_IDENTITY_DETECTED',
                409
            );
        }

        // 5. Cooldown & Attempt Limit Checks
        $latestRecord = KycRecord::where('user_id', $user->id)->latest('id')->first();
        if ($latestRecord && $latestRecord->cooldown_until && $latestRecord->cooldown_until->isFuture()) {
            $diff = $latestRecord->cooldown_until->diffForHumans();
            throw new BusinessRuleException(
                "Verification attempts temporarily locked due to repeated failures. Try again {$diff}.",
                'KYC_COOLDOWN_ACTIVE',
                429
            );
        }

        $attemptCount = $latestRecord ? ($latestRecord->attempt_count + 1) : 1;

        // 6. Save or process selfie if provided
        $selfiePath = null;
        if ($selfieBase64) {
            $selfiePath = $this->storeBase64Image(
                $selfieBase64,
                "kyc/selfies/selfie_{$user->id}_".time().'.jpg'
            );
        }

        // 7. Invoke welfare/verification engine
        $provider = config('verification.default', 'identitypass');
        $extraParams = array_filter([
            'date_of_birth' => $dateOfBirth,
            'selfie_url' => $selfiePath ? Storage::disk('local')->path($selfiePath) : null,
        ]);

        $result = Verification::verifyNin($cleanNin, $extraParams);

        if ($result->isSuccessful()) {
            $data = $result->data ?? [];
            $verifiedName = trim(($data['firstname'] ?? $data['first_name'] ?? '').' '.($data['lastname'] ?? $data['last_name'] ?? ''));

            if (empty($verifiedName) && ! empty($data['fullname'])) {
                $verifiedName = (string) $data['fullname'];
            }

            // Persist the provider NIN photo if present as base64
            $ninPhotoPath = null;
            $rawPhotoBase64 = $data['photo'] ?? $data['nin_data']['photo'] ?? null;
            if (! empty($rawPhotoBase64) && is_string($rawPhotoBase64)) {
                $ninPhotoPath = $this->storeBase64Image(
                    $rawPhotoBase64,
                    "kyc/photos/nin_{$user->id}_".time().'.jpg'
                );
            }

            $kycRecord = KycRecord::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_ref' => $result->reference,
                'status' => KycStatus::Verified,
                'match_score' => 95.00,
                'verified_name' => $verifiedName ?: $user->name,
                'nin_hash' => $ninHash,
                'nin_photo_path' => $ninPhotoPath,
                'selfie_photo_path' => $selfiePath,
                'consent_logged_at' => now(),
                'attempt_count' => $attemptCount,
                'raw_response' => $result->raw,
            ]);

            // Update user to Verified state
            $user->update([
                'kyc_status' => KycStatus::Verified,
                'legal_name' => $verifiedName ?: $user->name,
            ]);

            // Grant seller privileges automatically
            if (! $user->hasRole('seller')) {
                $user->assignRole('seller');
            }

            return $kycRecord;
        }

        // Verification failed
        $isExhausted = $attemptCount >= 3;
        $status = $isExhausted ? KycStatus::ManualReview : KycStatus::FailedRetryable;
        $cooldown = $isExhausted ? now()->addHours(24) : null;

        $kycRecord = KycRecord::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_ref' => $result->reference,
            'status' => $status,
            'match_score' => 0.00,
            'nin_hash' => $ninHash,
            'selfie_photo_path' => $selfiePath,
            'consent_logged_at' => now(),
            'attempt_count' => $attemptCount,
            'cooldown_until' => $cooldown,
            'rejection_reason' => $result->message,
            'raw_response' => $result->raw,
        ]);

        $user->update(['kyc_status' => $status]);

        throw new BusinessRuleException(
            "Identity verification failed: {$result->message}",
            'KYC_VERIFICATION_FAILED',
            422,
            ['status' => $status->value, 'attempts_remaining' => max(0, 3 - $attemptCount)]
        );
    }

    /**
     * Decode base64 image and persist into private secure storage.
     */
    protected function storeBase64Image(string $base64Data, string $targetPath): ?string
    {
        try {
            // Strip data:image/...;base64, header if present
            if (str_contains($base64Data, ',')) {
                $base64Data = explode(',', $base64Data)[1];
            }

            $binary = base64_decode($base64Data, true);
            if ($binary === false) {
                return null;
            }

            Storage::disk('local')->put("private/{$targetPath}", $binary);

            return "private/{$targetPath}";
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
