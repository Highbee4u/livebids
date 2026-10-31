<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\OtpPurpose;
use App\Domain\Identity\Services\OtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Request an OTP for phone authentication.
     */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $purpose = OtpPurpose::tryFrom($request->input('purpose', 'login')) ?? OtpPurpose::Login;

        $otp = $this->otpService->requestOtp(
            $request->input('phone'),
            $purpose,
            $request->input('device_id')
        );

        return response()->json([
            'message' => 'Verification code sent successfully via SMS.',
            'expires_at' => $otp->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Verify phone OTP and return auth bearer token.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $purpose = OtpPurpose::tryFrom($request->input('purpose', 'login')) ?? OtpPurpose::Login;

        $authData = $this->otpService->verifyOtp(
            phone: $request->input('phone'),
            code: $request->input('code'),
            purpose: $purpose,
            deviceId: $request->input('device_id'),
            platform: $request->input('platform', 'android'),
            fcmToken: $request->input('fcm_token')
        );

        return response()->json([
            'message' => 'Authentication successful.',
            'token' => $authData['token'],
            'token_type' => 'Bearer',
            'is_new_user' => $authData['is_new_user'],
            'user' => new UserResource($authData['user']),
        ]);
    }

    /**
     * Refresh the authenticated user's access token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $newToken = $user->createToken('mobile-app', ['*'])->plainTextToken;

        return response()->json([
            'token' => $newToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Revoke current token and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
