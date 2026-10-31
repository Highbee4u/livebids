<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 20);
            $table->string('bank_name', 100);
            $table->string('account_number', 20);
            $table->string('account_name', 150);
            $table->string('status', 30)->default('unverified')->index();
            $table->string('recipient_code', 100)->nullable(); // Paystack transfer recipient code
            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'bank_code', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
