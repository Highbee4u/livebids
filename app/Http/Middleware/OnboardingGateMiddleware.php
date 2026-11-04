<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingGateMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isOnboarded()) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'ONBOARDING_INCOMPLETE',
                'code' => 'ONBOARDING_INCOMPLETE',
                'message' => 'Please complete your profile and pickup address before proceeding.',
                'data' => [
                    'is_phone_verified' => $user->isPhoneVerified(),
                    'is_profile_completed' => $user->isProfileCompleted(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
