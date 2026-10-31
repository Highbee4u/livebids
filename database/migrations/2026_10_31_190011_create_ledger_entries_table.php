<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('account', 40)->index(); // available, locked, escrow, platform_revenue, provider_clearing
            $table->string('direction', 10)->index(); // debit, credit
            $table->unsignedBigInteger('amount'); // integer minor units (kobo)
            $table->string('idempotency_key', 100)->index();
            $table->string('ref_type', 50)->index(); // topup, bid_lock, escrow_hold, escrow_release, refund, withdrawal, manual_adjustment
            $table->string('ref_id', 100)->index();
            $table->string('description');
            $table->timestamp('created_at')->useCurrent()->index();

            // Index for fast audit reconciliation
            $table->index(['account', 'created_at']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
