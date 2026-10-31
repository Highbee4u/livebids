<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payment\Models\Withdrawal;
use App\Domain\Payment\Services\PayoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestWithdrawalRequest;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WithdrawalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        protected PayoutService $payoutService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Submit a withdrawal payout request.
     */
    public function requestWithdrawal(RequestWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->payoutService->requestWithdrawal(
            user: $request->user(),
            amountKobo: $request->integer('amount_kobo'),
            bankAccountId: $request->integer('bank_account_id'),
            pin: $request->input('pin')
        );

        $wallet = $this->ledgerService->getOrCreateWallet($request->user());

        return response()->json([
            'message' => 'Withdrawal request submitted and processing.',
            'withdrawal' => new WithdrawalResource($withdrawal->load('bankAccount')),
            'wallet' => new WalletResource($wallet),
        ], 201);
    }

    /**
     * View withdrawal payout history.
     */
    public function history(Request $request): JsonResponse
    {
        $withdrawals = Withdrawal::with('bankAccount')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'withdrawals' => WithdrawalResource::collection($withdrawals),
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }
}
