<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('terms_and_conditions', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type', 50)->index()->comment('general, bidding_escrow, seller_agreement, privacy_policy, pickup_delivery');
            $table->string('version', 20)->default('1.0');
            $table->longText('content');
            $table->text('summary_of_changes')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_required')->default(true);
            $table->timestamp('effective_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version'], 'idx_terms_type_version');
            $table->index(['type', 'is_active', 'effective_date'], 'idx_terms_active_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms_and_conditions');
    }
};
