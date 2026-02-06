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
        Schema::create('proxies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->enum('type', ['http', 'https', 'socks4', 'socks5'])->default('http');
            $table->string('host');
            $table->integer('port');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_rotating')->default(false); // Rotating proxy service
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('response_time')->nullable(); // Average response time in ms
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proxies');
    }
};
