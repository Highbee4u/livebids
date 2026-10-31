<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // integer minor units (kobo)
            $table->unsignedSmallInteger('commission_rate')->default(10); // percentage (10 = 10%)
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('seller_payout_amount');
            $table->string('status', 30)->default('awaiting_handover')->index();
            $table->string('handover_code', 10)->nullable();
            $table->string('handover_code_hash');
            $table->unsignedTinyInteger('handover_code_attempts')->default(0);
            $table->timestamp('handover_code_cooldown_until')->nullable();
            $table->string('handover_type', 30)->default('pickup'); // pickup, delivery
            $table->json('handover_details')->nullable(); // place, scheduled_time, notes
            $table->timestamp('handover_agreed_at')->nullable();
            $table->timestamp('deadline_at')->index(); // auto-release deadline
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
        Schema::dropIfExists('orders');
    }
};
