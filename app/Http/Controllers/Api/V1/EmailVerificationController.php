<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\EmailVerificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResendEmailVerificationRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $emailVerificationService
    ) {}

    /**
     * Verify email address using the 6-digit OTP code.
     */
    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $verifiedUser = $this->emailVerificationService->verifyEmail(
            user: $user,
            code: $request->input('code')
        );

        return $this->successResponse(
            [
                'user' => new UserResource($verifiedUser),
                'status' => 'EMAIL_VERIFIED',
            ],
            'Email address verified successfully.'
        );
    }

    /**
     * Resend verification email with a new 6-digit OTP code.
     */
    public function resend(ResendEmailVerificationRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $result = $this->emailVerificationService->resendVerificationCode($user);

        return $this->successResponse(
            $result,
            $result['message']
        );
    }

    /**
     * Resolve the target user from authenticated session, or identifier/email parameters.
     */
    protected function resolveUser(VerifyEmailRequest|ResendEmailVerificationRequest $request): User
    {
        if ($request->user()) {
            return $request->user();
        }

        if ($request->filled('identifier')) {
            return User::where('identifier', $request->input('identifier'))->firstOrFail();
        }

        return User::where('email', $request->input('email'))->firstOrFail();
    }
}
