<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique()->index();
            $table->string('phone', 30)->index();
            $table->string('status', 30)->default('pending_otp')->index();
            $table->timestamp('phone_verified_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('device_id')->nullable();
            $table->string('platform', 30)->default('android');
            $table->string('fcm_token')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_requests');
    }
};
