<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Render an exception into a normalized JSON API response.
     */
    public function render(Throwable $e): JsonResponse
    {
        $incidentId = 'ERR-LIVE-'.strtoupper(Str::random(8));

        // Validation Exception (422)
        if ($e instanceof ValidationException) {
            $firstError = collect($e->errors())->flatten()->first() ?: 'The given data was invalid.';

            return response()->json([
                'status' => false,
                'message' => $firstError,
                'error' => 'VALIDATION_ERROR',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }

        // Authentication Exception (401)
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Unauthenticated.',
                'error' => 'UNAUTHENTICATED',
                'error_code' => 'UNAUTHENTICATED',
                'errors' => null,
            ], 401);
        }

        // Authorization Exception (403)
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'error' => 'FORBIDDEN',
                'error_code' => 'FORBIDDEN',
                'errors' => null,
            ], 403);
        }

        // Route / Resource Not Found (404)
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'status' => false,
                'message' => 'The requested resource or route could not be found.',
                'error' => 'NOT_FOUND',
                'error_code' => 'RESOURCE_NOT_FOUND',
                'errors' => null,
            ], 404);
        }

        // Method Not Allowed (405)
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'status' => false,
                'message' => 'The requested HTTP method is not supported for this endpoint.',
                'error' => 'METHOD_NOT_ALLOWED',
                'error_code' => 'METHOD_NOT_ALLOWED',
                'errors' => null,
            ], 405);
        }

        // Domain Business Rule Exception
        if ($e instanceof BusinessRuleException) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => $e->getErrorCode(),
                'error_code' => $e->getErrorCode(),
                'errors' => $e->getErrors(),
            ], $e->getStatusCode());
        }

        // HTTP Exception
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'HTTP error occurred.',
                'error' => 'HTTP_ERROR_'.$statusCode,
                'error_code' => 'HTTP_ERROR_'.$statusCode,
                'errors' => null,
            ], $statusCode);
        }

        // Database Query Exception (500)
        if ($e instanceof QueryException) {
            Log::error("Database Query Error [{$incidentId}]", [
                'incident_id' => $incidentId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => "A database processing error occurred. Reference: {$incidentId}",
                'error' => 'DATABASE_ERROR',
                'error_code' => 'DATABASE_ERROR',
                'incident_id' => $incidentId,
            ], 500);
        }

        // Generic Server Error (500)
        $isProduction = app()->isProduction();

        Log::error("Unhandled Server Exception [{$incidentId}]", [
            'incident_id' => $incidentId,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'status' => false,
            'message' => $isProduction
                ? "An unexpected internal server error occurred. Reference: {$incidentId}"
                : $e->getMessage(),
            'error' => 'INTERNAL_SERVER_ERROR',
            'error_code' => 'INTERNAL_SERVER_ERROR',
            'incident_id' => $incidentId,
            'errors' => $isProduction ? null : [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(10)->toArray(),
            ],
        ], 500);
    }
}
