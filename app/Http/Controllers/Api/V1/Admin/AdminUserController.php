<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Identity\Enums\StrikeReason;
use App\Domain\Identity\Models\Strike;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function strike(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($id);
        $reason = StrikeReason::tryFrom($request->input('reason')) ?? StrikeReason::Other;

        Strike::create([
            'user_id' => $user->id,
            'issued_by' => $request->user()->id,
            'reason' => $reason,
            'note' => $request->input('note'),
        ]);

        $user->increment('strikes_count');

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'issued_strike',
            targetType: 'User',
            targetId: (string) $user->id,
            after: ['strikes_count' => $user->fresh()->strikes_count, 'reason' => $reason->value]
        );

        return response()->json([
            'message' => 'Strike recorded against user account.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function suspend(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_suspended' => true]);

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'suspended_user',
            targetType: 'User',
            targetId: (string) $user->id,
            after: ['is_suspended' => true]
        );

        return response()->json([
            'message' => 'User account suspended.',
            'user' => new UserResource($user),
        ]);
    }

    public function ban(int $id, Request $request): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_banned' => true]);

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'banned_user',
            targetType: 'User',
            targetId: (string) $user->id,
            after: ['is_banned' => true]
        );

        return response()->json([
            'message' => 'User permanently banned.',
            'user' => new UserResource($user),
        ]);
    }
}
