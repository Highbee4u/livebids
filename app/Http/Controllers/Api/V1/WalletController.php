<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Get wallet balance (available, locked, and escrow).
     */
    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->ledgerService->getOrCreateWallet($request->user());

        return response()->json([
            'wallet' => new WalletResource($wallet),
        ]);
    }

    /**
     * Get paginated ledger transaction history.
     */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->ledgerService->getOrCreateWallet($request->user());

        $transactions = LedgerEntry::where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'wallet' => new WalletResource($wallet),
            'transactions' => LedgerEntryResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
