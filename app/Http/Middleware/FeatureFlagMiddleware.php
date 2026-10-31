<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagMiddleware
{
    /**
     * Check if a system feature kill-switch is active.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $enabled = config("auction.features.{$feature}", true);

        if (! $enabled) {
            return response()->json([
                'message' => "The {$feature} service is temporarily disabled for scheduled maintenance.",
                'errors' => null,
                'error_code' => 'FEATURE_DISABLED',
            ], 503);
        }

        return $next($request);
    }
}
