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
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('website_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['idle', 'running', 'paused', 'stopped', 'error'])->default('idle');
            $table->integer('current_job_id')->nullable();
            $table->integer('processed_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('system_info')->nullable(); // CPU, memory usage
            $table->string('worker_hostname')->nullable();
            $table->integer('worker_pid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
