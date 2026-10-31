<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKeyMiddleware
{
    /**
     * Handle an incoming request and ensure strict idempotency on money/state operations.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce on mutating requests
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required for this operation.',
                'errors' => null,
                'error_code' => 'IDEMPOTENCY_KEY_MISSING',
            ], 400);
        }

        $userId = $request->user()?->id ?? 'guest';
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";
        $lockKey = "idempotency_lock:{$userId}:{$idempotencyKey}";

        // Check if previously completed
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse !== null) {
            return response()->json(
                $cachedResponse['data'],
                $cachedResponse['status'],
                array_merge($cachedResponse['headers'], ['X-Idempotent-Replay' => 'true'])
            );
        }

        // Acquire lock to prevent duplicate in-flight requests
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A request with this Idempotency-Key is currently being processed.',
                'errors' => null,
                'error_code' => 'CONCURRENT_REQUEST',
            ], 409);
        }

        try {
            $response = $next($request);

            // Only cache successful JSON responses (200, 201)
            if ($response instanceof JsonResponse && in_array($response->getStatusCode(), [200, 201], true)) {
                Cache::put($cacheKey, [
                    'data' => $response->getData(true),
                    'status' => $response->getStatusCode(),
                    'headers' => ['Content-Type' => 'application/json'],
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
