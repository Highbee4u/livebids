<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payment\Enums\BankAccountStatus;
use App\Domain\Payment\Enums\WithdrawalStatus;
use App\Domain\Payment\Models\BankAccount;
use App\Domain\Payment\Models\Withdrawal;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PayoutService
{
    public function __construct(
        protected PaystackService $paystackService,
        protected LedgerService $ledgerService
    ) {}

    /**
     * Verify and register a bank account with strict KYC name matching.
     *
     * @throws BusinessRuleException
     */
    public function verifyAndSaveBankAccount(User $user, string $accountNumber, string $bankCode, string $bankName): BankAccount
    {
        if (! $user->isKycVerified() || empty($user->legal_name)) {
            throw new BusinessRuleException(
                'You must complete NIN identity verification before adding a payout bank account.',
                'KYC_REQUIRED_FOR_BANK',
                403
            );
        }

        // 1. Resolve account via Paystack NUBAN lookup
        $resolved = $this->paystackService->resolveAccountNumber($accountNumber, $bankCode);
        $resolvedName = strtoupper(trim((string) ($resolved['account_name'] ?? '')));

        // 2. Strict Name Match against verified legal name
        $legalName = strtoupper(trim($user->legal_name));

        if (! $this->namesMatch($resolvedName, $legalName)) {
            throw new BusinessRuleException(
                "Bank account name '{$resolvedName}' does not match your verified legal name '{$legalName}'. For fraud prevention, payouts can only be sent to bank accounts in your name.",
                'BANK_NAME_MISMATCH',
                422,
                ['resolved_name' => $resolvedName, 'legal_name' => $legalName]
            );
        }

        // 3. Create Transfer Recipient on Paystack
        $recipientCode = $this->paystackService->createTransferRecipient($resolvedName, $accountNumber, $bankCode);

        // 4. Reset previous defaults and save new verified account
        BankAccount::where('user_id', $user->id)->update(['is_default' => false]);

        return BankAccount::updateOrCreate(
            ['user_id' => $user->id, 'bank_code' => $bankCode, 'account_number' => $accountNumber],
            [
                'bank_name' => $bankName,
                'account_name' => $resolvedName,
                'status' => BankAccountStatus::Verified,
                'recipient_code' => $recipientCode,
                'is_default' => true,
            ]
        );
    }

    /**
     * Request a withdrawal payout to a verified bank account.
     *
     * @throws BusinessRuleException
     */
    public function requestWithdrawal(User $user, int $amountKobo, int $bankAccountId, ?string $pin = null): Withdrawal
    {
        if (! config('auction.features.withdrawals_enabled', true)) {
            throw new BusinessRuleException('Withdrawals are temporarily paused for maintenance.', 'WITHDRAWALS_DISABLED', 503);
        }

        if ($user->isRestricted()) {
            throw new BusinessRuleException('Account restricted. Withdrawals are not permitted.', 'ACCOUNT_RESTRICTED', 403);
        }

        if (! $user->isKycVerified()) {
            throw new BusinessRuleException('Identity verification (NIN) is mandatory before withdrawing.', 'KYC_REQUIRED', 403);
        }

        // Validate withdrawal PIN if user has one configured
        if (! empty($user->withdrawal_pin_hash)) {
            if (empty($pin) || ! Hash::check($pin, $user->withdrawal_pin_hash)) {
                throw new BusinessRuleException('Invalid withdrawal PIN.', 'INVALID_WITHDRAWAL_PIN', 422);
            }
        }

        // Enforce New Account Anti-Fraud Hold
        $holdHours = config('auction.new_account_withdrawal_hold_hours', 168); // 7 days default
        if ($user->created_at->addHours($holdHours)->isFuture()) {
            $hasPreviousSuccess = Withdrawal::where('user_id', $user->id)
                ->where('status', WithdrawalStatus::Success)
                ->exists();

            if (! $hasPreviousSuccess) {
                $releaseDate = $user->created_at->addHours($holdHours)->toFormattedDateString();
                throw new BusinessRuleException(
                    "As a security safeguard for newly created accounts, initial withdrawals are held for {$holdHours} hours. Available for payout on {$releaseDate}.",
                    'NEW_ACCOUNT_PAYOUT_HOLD',
                    403
                );
            }
        }

        // Validate Bank Account
        $bankAccount = BankAccount::where('id', $bankAccountId)
            ->where('user_id', $user->id)
            ->where('status', BankAccountStatus::Verified)
            ->first();

        if (! $bankAccount || empty($bankAccount->recipient_code)) {
            throw new BusinessRuleException('Selected bank account is unverified or invalid.', 'INVALID_BANK_ACCOUNT', 422);
        }

        $reference = 'WTH_'.strtoupper(Str::random(12));
        $idempotencyKey = "withdrawal_{$reference}";

        // Check available balance and lock in ledger
        $wallet = $this->ledgerService->getOrCreateWallet($user);
        if ($wallet->available_balance < $amountKobo) {
            throw new BusinessRuleException('Insufficient available funds for this withdrawal amount.', 'INSUFFICIENT_FUNDS', 422);
        }

        $this->ledgerService->holdForWithdrawal($user, $amountKobo, $reference, $idempotencyKey);

        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => $amountKobo,
            'fee' => 0,
            'reference' => $reference,
            'status' => WithdrawalStatus::Processing,
        ]);

        try {
            $transfer = $this->paystackService->initiateTransfer(
                $bankAccount->recipient_code,
                $amountKobo,
                $reference,
                "LiveBids seller payout for User #{$user->id}"
            );

            $withdrawal->update([
                'transfer_code' => $transfer['transfer_code'] ?? null,
                'provider_response' => $transfer,
            ]);
        } catch (\Throwable $e) {
            // Refund funds back to available wallet immediately if Paystack rejects initiation
            $this->ledgerService->failWithdrawal($user, $amountKobo, $reference, "fail_{$idempotencyKey}");
            $withdrawal->update([
                'status' => WithdrawalStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $withdrawal;
    }

    /**
     * Compare bank account name and KYC legal name using token-set intersection.
     */
    protected function namesMatch(string $bankName, string $legalName): bool
    {
        $bankTokens = array_filter(explode(' ', preg_replace('/[^A-Z\s]/', '', $bankName)));
        $legalTokens = array_filter(explode(' ', preg_replace('/[^A-Z\s]/', '', $legalName)));

        // At least two name tokens must match (e.g. First Name and Last Name)
        $matches = array_intersect($bankTokens, $legalTokens);

        return count($matches) >= min(2, count($legalTokens));
    }
}
