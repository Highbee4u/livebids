<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('condition', 30)->default('like_new');
            $table->string('teaser_video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('starting_bid')->default(100000); // in integer kobo (default ₦1,000)
            $table->unsignedBigInteger('reserve_price')->nullable(); // hidden from public buyers
            $table->string('pickup_area', 150);
            $table->json('delivery_options')->nullable();
            $table->timestamp('scheduled_start_at')->useCurrent()->index();
            $table->unsignedSmallInteger('live_duration_seconds')->default(180);
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('live_started_at')->nullable();
            $table->timestamp('live_ended_at')->nullable();
            $table->unsignedBigInteger('winning_bid_id')->nullable()->index();
            $table->unsignedBigInteger('winning_amount')->nullable();
            $table->unsignedTinyInteger('extensions_count')->default(0);
            $table->timestamp('decision_window_ends_at')->nullable();
            $table->boolean('skipped_higher_bid')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
