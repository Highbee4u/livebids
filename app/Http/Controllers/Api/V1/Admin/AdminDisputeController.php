<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Dispute\Models\Dispute;
use App\Domain\Dispute\Services\DisputeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDisputeController extends Controller
{
    public function __construct(
        protected DisputeService $disputeService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $disputes = Dispute::with(['order', 'openedByUser', 'evidence'])
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['disputes' => $disputes]);
    }

    public function resolve(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'outcome' => ['required', 'string', 'in:resolved_buyer,resolved_seller,resolved_split'],
            'buyer_split_kobo' => ['nullable', 'integer'],
            'seller_split_kobo' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $dispute = Dispute::findOrFail($id);

        $resolved = $this->disputeService->resolveDispute(
            dispute: $dispute,
            admin: $request->user(),
            outcome: $request->input('outcome'),
            buyerSplitKobo: $request->input('buyer_split_kobo'),
            sellerSplitKobo: $request->input('seller_split_kobo'),
            notes: $request->input('notes')
        );

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'resolved_dispute',
            targetType: 'Dispute',
            targetId: (string) $dispute->id,
            after: ['outcome' => $request->input('outcome')]
        );

        return response()->json([
            'message' => 'Dispute resolved and ledger settlements posted.',
            'dispute' => $resolved,
        ]);
    }
}
