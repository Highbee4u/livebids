<?php

declare(strict_types=1);

namespace App\Traits\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponder
{
    /**
     * Standardized successful JSON response.
     */
    protected function successResponse(mixed $data = null, string $message = 'success', int $code = 200): JsonResponse
    {
        $payload = [
            'status' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * Standardized error JSON response.
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        ?string $error = null,
        mixed $errors = null,
        array $extra = []
    ): JsonResponse {
        $payload = [
            'status' => false,
            'message' => $message,
            'error' => $error ?? $this->resolveErrorCode($code),
            'error_code' => $error ?? $this->resolveErrorCode($code),
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if (! empty($extra)) {
            $payload = array_merge($payload, $extra);
        }

        return response()->json($payload, $code);
    }

    /**
     * Standardized informational/message response.
     */
    protected function showMessage(string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
        ], $code);
    }

    /**
     * Standardized paginated response.
     */
    protected function paginatedResponse(
        mixed $items,
        LengthAwarePaginator $paginator,
        string $message = 'success',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $code);
    }

    /**
     * Derive machine-readable error code from HTTP status code.
     */
    protected function resolveErrorCode(int $code): string
    {
        return match ($code) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'REQUEST_ERROR',
        };
    }
}
