<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\IdentityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitKycRequest;
use App\Http\Resources\KycRecordResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(
        protected IdentityService $identityService
    ) {}

    /**
     * Submit NIN and biometric liveness for identity verification.
     */
    public function submit(SubmitKycRequest $request): JsonResponse
    {
        $user = $request->user();

        $record = $this->identityService->submitNinLiveness(
            user: $user,
            nin: $request->input('nin'),
            selfieBase64: $request->input('selfie_base64'),
            consentData: ['consent' => $request->boolean('consent')],
            dateOfBirth: $request->input('date_of_birth')
        );

        return response()->json([
            'message' => 'Identity verification completed successfully.',
            'kyc_record' => new KycRecordResource($record),
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Check latest KYC verification status.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $latestRecord = $user->latestKycRecord;

        return response()->json([
            'kyc_status' => $user->kyc_status?->value ?? 'pending',
            'is_verified' => $user->isKycVerified(),
            'latest_record' => $latestRecord ? new KycRecordResource($latestRecord) : null,
        ]);
    }
}
