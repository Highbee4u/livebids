<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Models\AdminAuditLog;
use App\Domain\Audit\Models\ManualAdjustment;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Invariants\LedgerInvariantChecker;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLedgerController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LedgerInvariantChecker $invariantChecker
    ) {}

    /**
     * Propose a manual ledger adjustment (Maker).
     */
    public function proposeAdjustment(Request $request): JsonResponse
    {
        $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'account' => ['required', 'string', 'in:available,locked'],
            'direction' => ['required', 'string', 'in:debit,credit'],
            'amount_kobo' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $targetUser = User::findOrFail($request->integer('target_user_id'));

        $adjustment = $this->auditLogService->proposeAdjustment(
            requester: $request->user(),
            targetUser: $targetUser,
            account: LedgerAccount::from($request->input('account')),
            direction: LedgerDirection::from($request->input('direction')),
            amountKobo: $request->integer('amount_kobo'),
            reason: $request->input('reason')
        );

        return response()->json([
            'message' => 'Manual adjustment proposed. Requires approval by a second administrator.',
            'adjustment' => $adjustment,
        ], 201);
    }

    /**
     * Approve and execute a manual ledger adjustment (Checker).
     * Enforces dual-approver requirement: requesting admin cannot approve their own adjustment.
     */
    public function approveAdjustment(int $id, Request $request): JsonResponse
    {
        $adjustment = ManualAdjustment::with('targetUser')->findOrFail($id);

        $this->auditLogService->approveAdjustment($adjustment, $request->user());

        return response()->json([
            'message' => 'Manual adjustment approved and posted to ledger.',
            'adjustment' => $adjustment->fresh(),
        ]);
    }

    /**
     * Run real-time ledger invariant verification.
     */
    public function runReconciliation(): JsonResponse
    {
        $result = $this->invariantChecker->check();

        return response()->json([
            'reconciliation' => $result,
        ]);
    }

    /**
     * View administrative audit trail.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AdminAuditLog::with('admin:id,name,phone')
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'audit_logs' => $logs,
        ]);
    }
}
