<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\KycStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KycGateMiddleware
{
    /**
     * Ensure the authenticated user has verified their identity before accessing money/bidding/selling features.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Bypass if feature flag is turned off (e.g. in local/staging sandboxes)
        if (! config('auction.features.kyc_required', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'errors' => null,
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if ($user->kyc_status !== KycStatus::Verified) {
            return response()->json([
                'message' => 'NIN and liveness identity verification is mandatory before accessing this feature.',
                'errors' => [
                    'kyc_status' => $user->kyc_status?->value ?? KycStatus::Pending->value,
                ],
                'error_code' => 'KYC_VERIFICATION_REQUIRED',
            ], 403);
        }

        if ($user->is_suspended || $user->is_banned) {
            return response()->json([
                'message' => 'Account is suspended or restricted. Please contact support.',
                'errors' => null,
                'error_code' => 'ACCOUNT_RESTRICTED',
            ], 403);
        }

        return $next($request);
    }
}
