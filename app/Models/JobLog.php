<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'data_entry_id',
        'website_id',
        'proxy_id',
        'status',
        'step_number',
        'step_name',
        'message',
        'details',
        'screenshot_path',
        'duration_ms',
        'browser_session_id',
        'executed_at',
    ];

    protected $casts = [
        'details' => 'array',
        'step_number' => 'integer',
        'duration_ms' => 'integer',
        'executed_at' => 'datetime',
    ];

    public const STATUSES = [
        'started' => 'Started',
        'step_completed' => 'Step Completed',
        'success' => 'Success',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
    ];

    public function dataEntry(): BelongsTo
    {
        return $this->belongsTo(DataEntry::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(Proxy::class);
    }

    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeByWebsite($query, int $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public static function logStart(DataEntry $entry, ?int $proxyId = null, ?string $sessionId = null): self
    {
        return self::create([
            'data_entry_id' => $entry->id,
            'website_id' => $entry->website_id,
            'proxy_id' => $proxyId,
            'status' => 'started',
            'message' => 'Job started',
            'browser_session_id' => $sessionId,
            'executed_at' => now(),
        ]);
    }

    public static function logStepCompleted(
        DataEntry $entry,
        int $stepNumber,
        string $stepName,
        int $durationMs,
        ?string $message = null,
        ?array $details = null
    ): self {
        return self::create([
            'data_entry_id' => $entry->id,
            'website_id' => $entry->website_id,
            'status' => 'step_completed',
            'step_number' => $stepNumber,
            'step_name' => $stepName,
            'message' => $message ?? "Step {$stepNumber} completed",
            'details' => $details,
            'duration_ms' => $durationMs,
            'executed_at' => now(),
        ]);
    }

    public static function logSuccess(
        DataEntry $entry,
        string $message,
        ?array $details = null,
        ?string $screenshotPath = null,
        ?int $durationMs = null
    ): self {
        return self::create([
            'data_entry_id' => $entry->id,
            'website_id' => $entry->website_id,
            'status' => 'success',
            'message' => $message,
            'details' => $details,
            'screenshot_path' => $screenshotPath,
            'duration_ms' => $durationMs,
            'executed_at' => now(),
        ]);
    }

    public static function logFailure(
        DataEntry $entry,
        string $message,
        ?array $details = null,
        ?string $screenshotPath = null,
        ?int $durationMs = null
    ): self {
        return self::create([
            'data_entry_id' => $entry->id,
            'website_id' => $entry->website_id,
            'status' => 'failed',
            'message' => $message,
            'details' => $details,
            'screenshot_path' => $screenshotPath,
            'duration_ms' => $durationMs,
            'executed_at' => now(),
        ]);
    }
}
