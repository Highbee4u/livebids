<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AdminAuditLog;
use App\Domain\Audit\Models\ManualAdjustment;
use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Services\LedgerService;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Log every administrative action for regulatory audit trails.
     */
    public function log(
        User $admin,
        string $action,
        string $targetType,
        string $targetId,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null
    ): AdminAuditLog {
        return AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_state' => $before,
            'after_state' => $after,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }

    /**
     * Propose a manual ledger adjustment (Maker step).
     */
    public function proposeAdjustment(
        User $requester,
        User $targetUser,
        LedgerAccount $account,
        LedgerDirection $direction,
        int $amountKobo,
        string $reason
    ): ManualAdjustment {
        return ManualAdjustment::create([
            'requested_by' => $requester->id,
            'target_user_id' => $targetUser->id,
            'account' => $account,
            'direction' => $direction,
            'amount' => $amountKobo,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /**
     * Approve and execute a manual ledger adjustment (Checker step).
     * Enforces dual-approver rule: author cannot approve their own adjustment.
     *
     * @throws BusinessRuleException
     */
    public function approveAdjustment(ManualAdjustment $adjustment, User $approver): void
    {
        if ($adjustment->status !== 'pending') {
            throw new BusinessRuleException('Adjustment is not in a pending state.', 'ADJUSTMENT_NOT_PENDING', 422);
        }

        // Strict dual-approver enforcement
        if ($approver->id === $adjustment->requested_by) {
            throw new BusinessRuleException(
                'Dual-approver violation: You cannot approve a manual ledger adjustment that you created.',
                'SELF_APPROVAL_PROHIBITED',
                403
            );
        }

        DB::transaction(function () use ($adjustment, $approver) {
            $idempotencyKey = "manual_adj_exec_{$adjustment->id}";

            $this->ledgerService->manualAdjustment(
                targetUser: $adjustment->targetUser,
                account: $adjustment->account,
                direction: $adjustment->direction,
                amountKobo: $adjustment->amount,
                reason: $adjustment->reason,
                idempotencyKey: $idempotencyKey
            );

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
            ]);

            $this->log(
                admin: $approver,
                action: 'approved_manual_ledger_adjustment',
                targetType: 'User',
                targetId: (string) $adjustment->target_user_id,
                before: ['status' => 'pending'],
                after: ['status' => 'approved', 'amount_kobo' => $adjustment->amount]
            );
        });
    }
}
