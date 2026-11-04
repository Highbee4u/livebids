<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('iso2', 2)->unique();
            $table->string('iso3', 3)->nullable()->unique();
            $table->string('phone_code', 10)->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->string('currency_symbol', 10)->default('₦');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('state_code', 10)->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['state_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
