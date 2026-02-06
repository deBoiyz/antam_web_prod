<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'name',
        'order',
        'url_pattern',
        'wait_for_selector',
        'wait_timeout',
        'action_type',
        'custom_script',
        'success_indicator',
        'failure_indicator',
        'success_message_selector',
        'failure_message_selector',
        'is_final_step',
    ];

    protected $casts = [
        'order' => 'integer',
        'wait_timeout' => 'integer',
        'is_final_step' => 'boolean',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function getFullConfigAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'url_pattern' => $this->url_pattern,
            'wait_for_selector' => $this->wait_for_selector,
            'wait_timeout' => $this->wait_timeout,
            'action_type' => $this->action_type,
            'custom_script' => $this->custom_script,
            'success_indicator' => $this->success_indicator,
            'failure_indicator' => $this->failure_indicator,
            'success_message_selector' => $this->success_message_selector,
            'failure_message_selector' => $this->failure_message_selector,
            'is_final_step' => $this->is_final_step,
            'fields' => $this->formFields->map(fn($field) => $field->full_config)->toArray(),
        ];
    }
}
