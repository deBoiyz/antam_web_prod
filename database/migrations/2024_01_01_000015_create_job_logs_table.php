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
        Schema::create('job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_entry_id')->constrained()->onDelete('cascade');
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->foreignId('proxy_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['started', 'step_completed', 'success', 'failed', 'cancelled'])->default('started');
            $table->integer('step_number')->nullable();
            $table->string('step_name')->nullable();
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->text('screenshot_path')->nullable();
            $table->integer('duration_ms')->nullable(); // Step duration
            $table->string('browser_session_id')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['website_id', 'status']);
            $table->index(['data_entry_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_logs');
    }
};
