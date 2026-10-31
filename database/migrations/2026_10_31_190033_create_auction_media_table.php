<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('media_type', 30)->default('teaser_video'); // teaser_video, thumbnail, photo
            $table->string('mime_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 30)->default('ready'); // uploading, ready, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_media');
    }
};
