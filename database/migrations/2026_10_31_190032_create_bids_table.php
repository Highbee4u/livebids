<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // integer minor units (kobo)
            $table->string('status', 30)->default('active')->index(); // active, won, released
            $table->string('lock_ledger_ref', 100)->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auction_id', 'amount']);
            $table->index(['auction_id', 'bidder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
