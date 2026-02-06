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
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('headers')->nullable(); // Custom headers for requests
            $table->json('cookies')->nullable(); // Pre-set cookies
            $table->integer('timeout')->default(30000); // Timeout in ms
            $table->integer('retry_attempts')->default(3);
            $table->integer('retry_delay')->default(5000); // Delay between retries in ms
            $table->integer('concurrency_limit')->default(5); // Max concurrent bots
            $table->integer('max_jobs_per_minute')->default(10); // Rate limiting
            $table->integer('priority')->default(0); // Priority for job scheduling
            $table->string('user_agent')->nullable();
            $table->boolean('use_stealth')->default(true);
            $table->boolean('use_proxy')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
