<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payment\Models\BankAccount;
use App\Domain\Payment\Services\PayoutService;
use App\Domain\Payment\Services\PaystackService;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected PayoutService $payoutService
    ) {}

    /**
     * List supported commercial banks.
     */
    public function listBanks(): JsonResponse
    {
        $banks = $this->paystackService->listBanks();

        return response()->json([
            'banks' => $banks,
        ]);
    }

    /**
     * Verify and register a bank account with KYC name verification.
     */
    public function verifyAccount(VerifyBankAccountRequest $request): JsonResponse
    {
        $account = $this->payoutService->verifyAndSaveBankAccount(
            user: $request->user(),
            accountNumber: $request->input('account_number'),
            bankCode: $request->input('bank_code'),
            bankName: $request->input('bank_name')
        );

        return response()->json([
            'message' => 'Bank account verified and registered successfully.',
            'bank_account' => new BankAccountResource($account),
        ], 201);
    }

    /**
     * List user's registered bank accounts.
     */
    public function myAccounts(Request $request): JsonResponse
    {
        $accounts = BankAccount::where('user_id', $request->user()->id)->get();

        return response()->json([
            'bank_accounts' => BankAccountResource::collection($accounts),
        ]);
    }
}
