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
        Schema::create('form_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->string('url_pattern')->nullable(); // URL pattern to match for this step
            $table->string('wait_for_selector')->nullable(); // Wait for element before proceeding
            $table->integer('wait_timeout')->default(10000);
            $table->enum('action_type', ['fill_form', 'click', 'wait', 'screenshot', 'extract_data', 'navigate', 'custom_script'])->default('fill_form');
            $table->text('custom_script')->nullable(); // Custom JS to execute
            $table->string('success_indicator')->nullable(); // Selector that indicates success
            $table->string('failure_indicator')->nullable(); // Selector that indicates failure
            $table->text('success_message_selector')->nullable();
            $table->text('failure_message_selector')->nullable();
            $table->boolean('is_final_step')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_steps');
    }
};
