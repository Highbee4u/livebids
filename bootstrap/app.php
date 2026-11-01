<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\FeatureFlagMiddleware;
use App\Http\Middleware\IdempotencyKeyMiddleware;
use App\Http\Middleware\KycGateMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_perm' => RoleOrPermissionMiddleware::class,
            'idempotency' => IdempotencyKeyMiddleware::class,
            'kyc.verified' => KycGateMiddleware::class,
            'feature' => FeatureFlagMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->expectsJson() || $request->is('api/*')) {
                return app(ApiExceptionHandler::class)->render($e);
            }

            return null;
        });
    })
    ->create();
