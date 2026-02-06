<?php

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
        Schema::create('captcha_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['2captcha', 'capsolver', 'anticaptcha', 'manual'])->default('2captcha');
            $table->string('api_key')->nullable();
            $table->string('api_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('balance', 10, 4)->nullable();
            $table->timestamp('balance_checked_at')->nullable();
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->integer('average_solve_time')->nullable(); // in seconds
            $table->json('supported_types')->nullable(); // ['recaptcha_v2', 'recaptcha_v3', 'turnstile', 'image']
            $table->integer('priority')->default(0); // Higher = use first
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('captcha_services');
    }
};
