<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payment\Enums\TopupStatus;
use App\Domain\Payment\Models\Topup;
use App\Domain\Payment\Services\PaystackService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InitializeTopupRequest;
use App\Http\Resources\TopupResource;
use App\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopupController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Initialize a wallet top-up payment checkout.
     */
    public function initialize(InitializeTopupRequest $request): JsonResponse
    {
        $user = $request->user();
        $amountKobo = $request->integer('amount_kobo');
        $reference = 'TOP_'.strtoupper(Str::random(12));

        $initData = $this->paystackService->initializeTransaction(
            user: $user,
            amountKobo: $amountKobo,
            reference: $reference,
            callbackUrl: $request->input('callback_url')
        );

        $topup = Topup::create([
            'user_id' => $user->id,
            'amount' => $amountKobo,
            'reference' => $reference,
            'status' => TopupStatus::Pending,
            'channel' => 'paystack',
            'checkout_url' => $initData['authorization_url'],
            'access_code' => $initData['access_code'],
        ]);

        return response()->json([
            'message' => 'Topup initiated. Redirect user to authorization URL.',
            'topup' => new TopupResource($topup),
        ], 201);
    }

    /**
     * Verify a topup payment and credit the user's wallet immediately.
     */
    public function verify(string $reference, Request $request): JsonResponse
    {
        $topup = Topup::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($topup->status === TopupStatus::Success) {
            $wallet = $this->ledgerService->getOrCreateWallet($request->user());

            return response()->json([
                'message' => 'Topup already verified and credited.',
                'topup' => new TopupResource($topup),
                'wallet' => new WalletResource($wallet),
            ]);
        }

        $verification = $this->paystackService->verifyTransaction($reference);

        if (($verification['status'] ?? '') === 'success') {
            $paidAmount = (int) ($verification['amount'] ?? $topup->amount);

            // Double check amount matches
            if ($paidAmount < $topup->amount) {
                $topup->update(['status' => TopupStatus::Failed, 'provider_response' => $verification]);
                throw new BusinessRuleException('Paid amount is less than initiated topup amount.', 'PAYMENT_AMOUNT_MISMATCH', 422);
            }

            // Post to double-entry ledger atomically
            $idempotencyKey = "topup_{$topup->reference}";
            $this->ledgerService->creditTopup($request->user(), $topup->amount, $topup->reference, $idempotencyKey);

            $topup->update([
                'status' => TopupStatus::Success,
                'completed_at' => now(),
                'provider_response' => $verification,
            ]);

            $wallet = $this->ledgerService->getOrCreateWallet($request->user());

            return response()->json([
                'message' => 'Wallet funded successfully.',
                'topup' => new TopupResource($topup),
                'wallet' => new WalletResource($wallet),
            ]);
        }

        $topup->update([
            'status' => TopupStatus::Failed,
            'provider_response' => $verification,
        ]);

        return response()->json([
            'message' => 'Payment was not successful.',
            'topup' => new TopupResource($topup),
        ], 422);
    }
}
