<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Render an exception into a normalized JSON API response.
     */
    public function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR',
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Unauthenticated.',
                'errors' => null,
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'errors' => null,
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'Resource not found.',
                'errors' => null,
                'error_code' => 'RESOURCE_NOT_FOUND',
            ], 404);
        }

        if ($e instanceof BusinessRuleException) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'error_code' => $e->getErrorCode(),
            ], $e->getStatusCode());
        }

        if ($e instanceof HttpExceptionInterface) {
            return response()->json([
                'message' => $e->getMessage() ?: 'HTTP error occurred.',
                'errors' => null,
                'error_code' => 'HTTP_ERROR_'.$e->getStatusCode(),
            ], $e->getStatusCode());
        }

        $statusCode = 500;
        $isProduction = app()->isProduction();

        return response()->json([
            'message' => $isProduction ? 'An unexpected internal server error occurred.' : $e->getMessage(),
            'errors' => $isProduction ? null : [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(10)->toArray(),
            ],
            'error_code' => 'INTERNAL_SERVER_ERROR',
        ], $statusCode);
    }
}
