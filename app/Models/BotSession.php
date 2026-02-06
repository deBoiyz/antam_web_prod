<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'website_id',
        'status',
        'current_job_id',
        'processed_count',
        'success_count',
        'failure_count',
        'started_at',
        'last_activity_at',
        'system_info',
        'worker_hostname',
        'worker_pid',
    ];

    protected $casts = [
        'current_job_id' => 'integer',
        'processed_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'system_info' => 'array',
        'worker_pid' => 'integer',
    ];

    public const STATUSES = [
        'idle' => 'Idle',
        'running' => 'Running',
        'paused' => 'Paused',
        'stopped' => 'Stopped',
        'error' => 'Error',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['running', 'idle', 'paused']);
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'last_activity_at' => now(),
        ]);
    }

    public function markAsIdle(): void
    {
        $this->update([
            'status' => 'idle',
            'current_job_id' => null,
            'last_activity_at' => now(),
        ]);
    }

    public function markAsStopped(): void
    {
        $this->update([
            'status' => 'stopped',
            'current_job_id' => null,
            'last_activity_at' => now(),
        ]);
    }

    public function markAsError(string $error = null): void
    {
        $systemInfo = $this->system_info ?? [];
        if ($error) {
            $systemInfo['last_error'] = $error;
        }
        
        $this->update([
            'status' => 'error',
            'system_info' => $systemInfo,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Check if session is stale (no activity for X minutes)
     */
    public function isStale(int $minutes = 2): bool
    {
        return $this->last_activity_at && 
               $this->last_activity_at->lt(now()->subMinutes($minutes));
    }

    /**
     * Mark all stale sessions as error
     */
    public static function cleanupStale(int $minutes = 2): int
    {
        $staleSessions = static::active()
            ->where('last_activity_at', '<', now()->subMinutes($minutes))
            ->get();
        
        $count = 0;
        foreach ($staleSessions as $session) {
            $session->markAsError('Session became unresponsive');
            $count++;
        }
        
        return $count;
    }

    public function recordSuccess(): void
    {
        $this->increment('processed_count');
        $this->increment('success_count');
        $this->update(['last_activity_at' => now()]);
    }

    public function recordFailure(): void
    {
        $this->increment('processed_count');
        $this->increment('failure_count');
        $this->update(['last_activity_at' => now()]);
    }

    public function setCurrentJob(int $jobId): void
    {
        $this->update([
            'current_job_id' => $jobId,
            'last_activity_at' => now(),
        ]);
    }

    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_count === 0) return 0;
        return round(($this->success_count / $this->processed_count) * 100, 2);
    }

    public function getUptimeAttribute(): ?string
    {
        if (!$this->started_at) return null;
        return $this->started_at->diffForHumans(['parts' => 2, 'short' => true], true);
    }
}
