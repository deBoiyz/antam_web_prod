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
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_step_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Field identifier
            $table->string('label')->nullable();
            $table->string('selector'); // CSS selector
            $table->enum('type', [
                'text', 
                'number', 
                'email', 
                'password', 
                'select', 
                'radio', 
                'checkbox', 
                'date', 
                'file',
                'hidden',
                'captcha_arithmetic',
                'captcha_image',
                'captcha_recaptcha',
                'captcha_turnstile',
                'click_button',
                'custom'
            ])->default('text');
            $table->string('data_source_field')->nullable(); // Maps to data_entries column
            $table->string('default_value')->nullable();
            $table->json('options')->nullable(); // For select/radio fields
            $table->boolean('is_required')->default(false);
            $table->integer('order')->default(0);
            $table->text('validation_regex')->nullable();
            $table->string('captcha_label_selector')->nullable(); // For arithmetic captcha
            $table->json('captcha_config')->nullable(); // Additional captcha settings
            $table->integer('delay_before')->default(0); // Delay before filling in ms
            $table->integer('delay_after')->default(0); // Delay after filling in ms
            $table->boolean('clear_before_fill')->default(true);
            $table->text('custom_handler')->nullable(); // Custom JS for field
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
