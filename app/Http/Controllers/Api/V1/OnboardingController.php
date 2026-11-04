<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\OnboardingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteProfileRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyOnboardingOtpRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Step 1: Register phone number, check availability, and dispatch verification OTP.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->onboardingService->initiateRegistration(
            phone: $request->input('phone'),
            deviceId: $request->input('device_id'),
            platform: $request->input('platform', 'android'),
            fcmToken: $request->input('fcm_token'),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ], 200);
    }

    /**
     * Step 2: Verify phone OTP code for active onboarding request.
     */
    public function verifyOtp(VerifyOnboardingOtpRequest $request): JsonResponse
    {
        $result = $this->onboardingService->verifyOnboardingOtp(
            identifier: $request->input('identifier'),
            code: $request->input('code')
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ], 200);
    }

    /**
     * Step 3: Complete profile (18+ DOB), physical pickup address, and convert to User.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $result = $this->onboardingService->completeProfile(
            identifier: $request->input('identifier'),
            data: $request->validated()
        );

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'status' => $result['status'],
            ],
        ], 201);
    }
}
