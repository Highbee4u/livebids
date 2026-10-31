<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PaystackService
{
    protected string $secretKey;

    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('services.paystack.secret_key', '');
        $this->baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
    }

    /**
     * Initialize a checkout charge for top-up.
     *
     * @return array{authorization_url: string, access_code: string, reference: string}
     */
    public function initializeTransaction(User $user, int $amountKobo, string $reference, ?string $callbackUrl = null): array
    {
        if (empty($this->secretKey)) {
            // Mock sandbox fallback for testing
            return [
                'authorization_url' => "https://checkout.paystack.com/mock-{$reference}",
                'access_code' => "mock_acc_{$reference}",
                'reference' => $reference,
            ];
        }

        $email = $user->email ?: (preg_replace('/[^\d]/', '', $user->phone).'@livebids.ng');

        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/transaction/initialize", array_filter([
                'email' => $email,
                'amount' => $amountKobo,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'app' => 'livebids',
                ],
            ]));

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            $msg = $data['message'] ?? 'Unable to initialize transaction with payment provider.';
            throw new BusinessRuleException($msg, 'PAYSTACK_INIT_FAILED', 502);
        }

        return [
            'authorization_url' => $data['data']['authorization_url'],
            'access_code' => $data['data']['access_code'],
            'reference' => $data['data']['reference'],
        ];
    }

    /**
     * Verify payment status directly with Paystack.
     */
    public function verifyTransaction(string $reference): array
    {
        if (empty($this->secretKey)) {
            // Mock sandbox response
            return [
                'status' => 'success',
                'amount' => 100000,
                'reference' => $reference,
                'paid_at' => now()->toIso8601String(),
            ];
        }

        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new BusinessRuleException(
                $data['message'] ?? 'Verification failed on payment provider.',
                'PAYSTACK_VERIFY_FAILED',
                502
            );
        }

        return $data['data'];
    }

    /**
     * Fetch Nigerian commercial banks list (cached 24 hours).
     */
    public function listBanks(): array
    {
        return Cache::remember('paystack_banks_ng', 86400, function () {
            if (empty($this->secretKey)) {
                return [
                    ['name' => 'Access Bank', 'code' => '044'],
                    ['name' => 'Guaranty Trust Bank', 'code' => '058'],
                    ['name' => 'United Bank for Africa', 'code' => '033'],
                    ['name' => 'Zenith Bank', 'code' => '057'],
                    ['name' => 'Kuda Bank', 'code' => '50211'],
                ];
            }

            $response = Http::withToken($this->secretKey)
                ->timeout(15)
                ->get("{$this->baseUrl}/bank?currency=NGN&country=nigeria");

            return $response->json()['data'] ?? [];
        });
    }

    /**
     * Resolve account number to get bank account name for KYC verification.
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array
    {
        if (empty($this->secretKey)) {
            return [
                'account_number' => $accountNumber,
                'account_name' => 'TEST VERIFIED USER',
                'bank_id' => 1,
            ];
        }

        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/bank/resolve?account_number={$accountNumber}&bank_code={$bankCode}");

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new BusinessRuleException(
                $data['message'] ?? 'Could not resolve bank account details. Verify bank and account number.',
                'BANK_ACCOUNT_NOT_FOUND',
                422
            );
        }

        return $data['data'];
    }

    /**
     * Create Paystack Transfer Recipient code (e.g. RCP_...).
     */
    public function createTransferRecipient(string $name, string $accountNumber, string $bankCode): string
    {
        if (empty($this->secretKey)) {
            return 'RCP_mock_'.uniqid();
        }

        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/transferrecipient", [
                'type' => 'nuban',
                'name' => $name,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => 'NGN',
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new BusinessRuleException(
                $data['message'] ?? 'Failed to create transfer recipient on payment provider.',
                'PAYSTACK_RECIPIENT_FAILED',
                502
            );
        }

        return $data['data']['recipient_code'];
    }

    /**
     * Initiate bank transfer payout.
     */
    public function initiateTransfer(string $recipientCode, int $amountKobo, string $reference, string $reason): array
    {
        if (empty($this->secretKey)) {
            return [
                'transfer_code' => 'TRF_mock_'.uniqid(),
                'status' => 'success',
                'reference' => $reference,
            ];
        }

        $response = Http::withToken($this->secretKey)
            ->timeout(15)
            ->post("{$this->baseUrl}/transfer", [
                'source' => 'balance',
                'amount' => $amountKobo,
                'recipient' => $recipientCode,
                'reason' => $reason,
                'reference' => $reference,
            ]);

        $data = $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            throw new BusinessRuleException(
                $data['message'] ?? 'Paystack transfer payout failed.',
                'PAYSTACK_TRANSFER_FAILED',
                502
            );
        }

        return $data['data'];
    }

    /**
     * Verify Paystack Webhook HMAC-SHA512 Signature.
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signature): bool
    {
        if (empty($this->secretKey) || empty($signature)) {
            return ! app()->isProduction(); // allow in local dev if no key set
        }

        $expected = hash_hmac('sha512', $rawPayload, $this->secretKey);

        return hash_equals($expected, $signature);
    }
}
