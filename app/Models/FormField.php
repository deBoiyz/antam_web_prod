<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_step_id',
        'name',
        'label',
        'selector',
        'type',
        'data_source_field',
        'default_value',
        'options',
        'is_required',
        'order',
        'validation_regex',
        'captcha_label_selector',
        'captcha_config',
        'delay_before',
        'delay_after',
        'clear_before_fill',
        'custom_handler',
    ];

    protected $casts = [
        'options' => 'array',
        'captcha_config' => 'array',
        'is_required' => 'boolean',
        'order' => 'integer',
        'delay_before' => 'integer',
        'delay_after' => 'integer',
        'clear_before_fill' => 'boolean',
    ];

    public const TYPES = [
        'text' => 'Text Input',
        'number' => 'Number Input',
        'email' => 'Email Input',
        'password' => 'Password Input',
        'select' => 'Select Dropdown',
        'radio' => 'Radio Button',
        'checkbox' => 'Checkbox',
        'date' => 'Date Picker',
        'file' => 'File Upload',
        'hidden' => 'Hidden Field',
        'captcha_arithmetic' => 'CAPTCHA - Arithmetic',
        'captcha_image' => 'CAPTCHA - Image',
        'captcha_recaptcha' => 'CAPTCHA - reCAPTCHA',
        'captcha_turnstile' => 'CAPTCHA - Turnstile',
        'click_button' => 'Click Button',
        'custom' => 'Custom Handler',
    ];

    public function formStep(): BelongsTo
    {
        return $this->belongsTo(FormStep::class);
    }

    public function isCaptcha(): bool
    {
        return str_starts_with($this->type, 'captcha_');
    }

    public function getFullConfigAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'selector' => $this->selector,
            'type' => $this->type,
            'data_source_field' => $this->data_source_field,
            'default_value' => $this->default_value,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'order' => $this->order,
            'validation_regex' => $this->validation_regex,
            'captcha_label_selector' => $this->captcha_label_selector,
            'captcha_config' => $this->captcha_config,
            'delay_before' => $this->delay_before,
            'delay_after' => $this->delay_after,
            'clear_before_fill' => $this->clear_before_fill,
            'custom_handler' => $this->custom_handler,
        ];
    }
}
