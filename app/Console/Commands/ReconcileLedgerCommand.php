<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\Invariants\LedgerInvariantChecker;
use Illuminate\Console\Command;

class ReconcileLedgerCommand extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Run ledger double-entry parity and wallet balance invariant reconciliation';

    public function handle(LedgerInvariantChecker $checker): int
    {
        $this->info('Starting double-entry ledger reconciliation...');

        $result = $checker->check();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Wallets Checked', $result['wallets_checked']],
                ['Total Debits (kobo)', number_format($result['total_debits'])],
                ['Total Credits (kobo)', number_format($result['total_credits'])],
                ['Parity Status', $result['passed'] ? 'PASSED (Sum = 0)' : 'FAILED'],
            ]
        );

        if (! $result['passed']) {
            $this->error('CRITICAL: Ledger invariant violations detected!');
            foreach ($result['errors'] as $error) {
                $this->error(" - {$error}");
            }

            return Command::FAILURE;
        }

        $this->info('All ledger invariants verified successfully.');

        return Command::SUCCESS;
    }
}
