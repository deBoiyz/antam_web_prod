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
        Schema::create('data_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('identifier')->nullable(); // Primary identifier (like NIK)
            $table->json('data'); // All form data as JSON
            $table->enum('status', ['pending', 'queued', 'processing', 'success', 'failed', 'cancelled'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('result_message')->nullable();
            $table->json('result_data')->nullable(); // Extracted data from result
            $table->text('error_message')->nullable();
            $table->string('screenshot_path')->nullable();
            $table->foreignId('proxy_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('priority')->default(0); // Higher = process first
            $table->string('batch_id')->nullable()->index(); // For grouping imports
            $table->timestamps();

            $table->index(['website_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_entries');
    }
};
