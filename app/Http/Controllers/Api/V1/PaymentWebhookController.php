<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payment\Enums\TopupStatus;
use App\Domain\Payment\Enums\WithdrawalStatus;
use App\Domain\Payment\Models\ProviderEvent;
use App\Domain\Payment\Models\Topup;
use App\Domain\Payment\Models\Withdrawal;
use App\Domain\Payment\Services\PaystackService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Intake and process webhooks from Paystack with HMAC verification and replay protection.
     */
    public function handlePaystack(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        // 1. HMAC Signature Verification
        if (! $this->paystackService->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Paystack webhook rejected: invalid HMAC signature');

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        // 2. Compute unique event ID for replay protection
        $eventId = (string) ($data['id'] ?? $data['reference'] ?? md5($rawPayload));

        $existing = ProviderEvent::where('provider', 'paystack')
            ->where('event_id', $eventId)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Event already processed.'], 200);
        }

        $providerEvent = ProviderEvent::create([
            'provider' => 'paystack',
            'event_type' => $event,
            'event_id' => $eventId,
            'payload' => $payload,
            'is_processed' => false,
        ]);

        try {
            switch ($event) {
                case 'charge.success':
                    $this->handleChargeSuccess($data);
                    break;

                case 'transfer.success':
                    $this->handleTransferSuccess($data);
                    break;

                case 'transfer.failed':
                case 'transfer.reversed':
                    $this->handleTransferFailed($data);
                    break;

                default:
                    Log::info("Paystack webhook received unhandled event: {$event}");
                    break;
            }

            $providerEvent->update(['is_processed' => true]);
        } catch (\Throwable $e) {
            $providerEvent->update(['error_message' => $e->getMessage()]);
            report($e);
        }

        return response()->json(['message' => 'Webhook received successfully.'], 200);
    }

    protected function handleChargeSuccess(array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $topup = Topup::where('reference', $reference)->first();

        if (! $topup || $topup->status === TopupStatus::Success) {
            return;
        }

        $amountKobo = (int) ($data['amount'] ?? $topup->amount);
        $idempotencyKey = "webhook_topup_{$reference}";

        // Credit wallet via double-entry ledger
        $this->ledgerService->creditTopup($topup->user, $amountKobo, $reference, $idempotencyKey);

        $topup->update([
            'status' => TopupStatus::Success,
            'completed_at' => now(),
            'provider_response' => $data,
        ]);
    }

    protected function handleTransferSuccess(array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $withdrawal = Withdrawal::where('reference', $reference)->first();

        if (! $withdrawal || $withdrawal->status === WithdrawalStatus::Success) {
            return;
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Success,
            'completed_at' => now(),
            'provider_response' => $data,
        ]);
    }

    protected function handleTransferFailed(array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $withdrawal = Withdrawal::where('reference', $reference)->first();

        if (! $withdrawal || $withdrawal->status === WithdrawalStatus::Failed) {
            return;
        }

        // Refund held funds back to user's wallet
        $idempotencyKey = "webhook_fail_{$reference}";
        $this->ledgerService->failWithdrawal($withdrawal->user, $withdrawal->amount, $reference, $idempotencyKey);

        $withdrawal->update([
            'status' => WithdrawalStatus::Failed,
            'failure_reason' => $data['reason'] ?? 'Transfer failed at provider.',
            'provider_response' => $data,
        ]);
    }
}
