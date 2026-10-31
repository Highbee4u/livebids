<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Invariants;

use App\Domain\Ledger\Enums\LedgerAccount;
use App\Domain\Ledger\Enums\LedgerDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\Wallet;
use Illuminate\Support\Facades\DB;

class LedgerInvariantChecker
{
    /**
     * Run all ledger invariant checks and return a diagnostic result.
     *
     * @return array{
     *   passed: bool,
     *   errors: array<string>,
     *   total_debits: int,
     *   total_credits: int,
     *   wallets_checked: int
     * }
     */
    public function check(): array
    {
        $errors = [];

        // 1. Check Global Debit == Credit Invariant
        $totals = LedgerEntry::select(
            DB::raw("SUM(CASE WHEN direction = '".LedgerDirection::Debit->value."' THEN amount ELSE 0 END) as total_debit"),
            DB::raw("SUM(CASE WHEN direction = '".LedgerDirection::Credit->value."' THEN amount ELSE 0 END) as total_credit")
        )->first();

        $totalDebit = (int) ($totals->total_debit ?? 0);
        $totalCredit = (int) ($totals->total_credit ?? 0);

        if ($totalDebit !== $totalCredit) {
            $errors[] = "Global ledger imbalance! Total debits ({$totalDebit}) != Total credits ({$totalCredit}). Difference: ".($totalDebit - $totalCredit);
        }

        // 2. Check each wallet's materialized balance matches its ledger history
        $wallets = Wallet::all();
        $walletsChecked = 0;

        foreach ($wallets as $wallet) {
            $walletsChecked++;

            if ($wallet->available_balance < 0) {
                $errors[] = "Wallet #{$wallet->id} (User #{$wallet->user_id}) has negative available balance: {$wallet->available_balance}";
            }

            if ($wallet->locked_balance < 0) {
                $errors[] = "Wallet #{$wallet->id} (User #{$wallet->user_id}) has negative locked balance: {$wallet->locked_balance}";
            }

            // Calculate derived available balance
            $availableHistory = LedgerEntry::where('wallet_id', $wallet->id)
                ->where('account', LedgerAccount::Available)
                ->select(
                    DB::raw("SUM(CASE WHEN direction = '".LedgerDirection::Credit->value."' THEN amount ELSE -amount END) as net_balance")
                )->value('net_balance') ?? 0;

            if ((int) $availableHistory !== $wallet->available_balance) {
                $errors[] = "Wallet #{$wallet->id} Available balance mismatch! Materialized: {$wallet->available_balance}, Ledger: {$availableHistory}";
            }

            // Calculate derived locked balance
            $lockedHistory = LedgerEntry::where('wallet_id', $wallet->id)
                ->where('account', LedgerAccount::Locked)
                ->select(
                    DB::raw("SUM(CASE WHEN direction = '".LedgerDirection::Credit->value."' THEN amount ELSE -amount END) as net_balance")
                )->value('net_balance') ?? 0;

            if ((int) $lockedHistory !== $wallet->locked_balance) {
                $errors[] = "Wallet #{$wallet->id} Locked balance mismatch! Materialized: {$wallet->locked_balance}, Ledger: {$lockedHistory}";
            }
        }

        return [
            'passed' => empty($errors),
            'errors' => $errors,
            'total_debits' => $totalDebit,
            'total_credits' => $totalCredit,
            'wallets_checked' => $walletsChecked,
        ];
    }
}
