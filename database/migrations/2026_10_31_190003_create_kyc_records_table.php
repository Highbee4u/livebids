<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('identitypass');
            $table->string('provider_ref')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->string('verified_name')->nullable();
            $table->string('nin_hash')->index();
            $table->string('nin_photo_path')->nullable();
            $table->string('selfie_photo_path')->nullable();
            $table->timestamp('consent_logged_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(1);
            $table->timestamp('cooldown_until')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_records');
    }
};
