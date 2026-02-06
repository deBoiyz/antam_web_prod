<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'description',
        'is_active',
        'headers',
        'cookies',
        'timeout',
        'retry_attempts',
        'retry_delay',
        'concurrency_limit',
        'max_jobs_per_minute',
        'priority',
        'user_agent',
        'use_stealth',
        'use_proxy',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'headers' => 'array',
        'cookies' => 'array',
        'timeout' => 'integer',
        'retry_attempts' => 'integer',
        'retry_delay' => 'integer',
        'concurrency_limit' => 'integer',
        'max_jobs_per_minute' => 'integer',
        'priority' => 'integer',
        'use_stealth' => 'boolean',
        'use_proxy' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($website) {
            if (empty($website->slug)) {
                $website->slug = Str::slug($website->name);
            }
        });
    }

    public function formSteps(): HasMany
    {
        return $this->hasMany(FormStep::class)->orderBy('order');
    }

    public function dataEntries(): HasMany
    {
        return $this->hasMany(DataEntry::class);
    }

    public function jobLogs(): HasMany
    {
        return $this->hasMany(JobLog::class);
    }

    public function botSessions(): HasMany
    {
        return $this->hasMany(BotSession::class);
    }

    public function getPendingEntriesCountAttribute(): int
    {
        return $this->dataEntries()->where('status', 'pending')->count();
    }

    public function getSuccessEntriesCountAttribute(): int
    {
        return $this->dataEntries()->where('status', 'success')->count();
    }

    public function getFailedEntriesCountAttribute(): int
    {
        return $this->dataEntries()->where('status', 'failed')->count();
    }

    public function getProcessingEntriesCountAttribute(): int
    {
        return $this->dataEntries()->whereIn('status', ['queued', 'processing'])->count();
    }

    public function getFullConfigAttribute(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'base_url' => $this->base_url,
            'headers' => $this->headers ?? [],
            'cookies' => $this->cookies ?? [],
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retry_attempts,
            'retry_delay' => $this->retry_delay,
            'concurrency_limit' => $this->concurrency_limit ?? 5,
            'max_jobs_per_minute' => $this->max_jobs_per_minute ?? 10,
            'priority' => $this->priority ?? 0,
            'user_agent' => $this->user_agent,
            'use_stealth' => $this->use_stealth,
            'use_proxy' => $this->use_proxy,
            'steps' => $this->formSteps->map(fn($step) => $step->full_config)->toArray(),
        ];
    }

    /**
     * Get queue name for this website
     */
    public function getQueueNameAttribute(): string
    {
        return 'website-' . $this->slug;
    }
}
