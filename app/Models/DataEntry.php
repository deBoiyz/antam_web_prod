<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'identifier',
        'data',
        'status',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'scheduled_at',
        'result_message',
        'result_data',
        'error_message',
        'screenshot_path',
        'proxy_id',
        'priority',
        'batch_id',
    ];

    protected $casts = [
        'data' => 'array',
        'result_data' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'priority' => 'integer',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'queued' => 'Queued',
        'processing' => 'Processing',
        'success' => 'Success',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(Proxy::class);
    }

    public function jobLogs(): HasMany
    {
        return $this->hasMany(JobLog::class)->orderBy('created_at', 'desc');
    }

    public function getDataValueAttribute(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
        ]);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markAsSuccess(string $message = null, array $resultData = null, string $screenshotPath = null): void
    {
        $this->update([
            'status' => 'success',
            'result_message' => $message,
            'result_data' => $resultData,
            'screenshot_path' => $screenshotPath,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $errorMessage = null, string $screenshotPath = null): void
    {
        $status = $this->attempts >= $this->max_attempts ? 'failed' : 'pending';
        
        $this->update([
            'status' => $status,
            'error_message' => $errorMessage,
            'screenshot_path' => $screenshotPath,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
        ]);
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts && $this->status !== 'success';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeReadyToProcess($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');
    }
}
