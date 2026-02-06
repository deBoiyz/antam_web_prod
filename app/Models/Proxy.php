<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proxy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'host',
        'port',
        'username',
        'password',
        'country',
        'is_active',
        'is_rotating',
        'success_count',
        'failure_count',
        'last_used_at',
        'last_checked_at',
        'response_time',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
        'is_rotating' => 'boolean',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'last_used_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'response_time' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    public const TYPES = [
        'http' => 'HTTP',
        'https' => 'HTTPS',
        'socks4' => 'SOCKS4',
        'socks5' => 'SOCKS5',
    ];

    public function dataEntries(): HasMany
    {
        return $this->hasMany(DataEntry::class);
    }

    public function jobLogs(): HasMany
    {
        return $this->hasMany(JobLog::class);
    }

    public function getConnectionUrlAttribute(): string
    {
        $auth = '';
        if ($this->username && $this->password) {
            $auth = "{$this->username}:{$this->password}@";
        }
        
        return "{$this->type}://{$auth}{$this->host}:{$this->port}";
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->success_count + $this->failure_count;
        if ($total === 0) return 0;
        
        return round(($this->success_count / $total) * 100, 2);
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_count');
        $this->update(['last_used_at' => now()]);
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
        $this->update(['last_used_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySuccessRate($query)
    {
        return $query->orderByRaw('(success_count / NULLIF(success_count + failure_count, 0)) DESC');
    }

    public function getConfigAttribute(): array
    {
        return [
            'type' => $this->type,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'url' => $this->connection_url,
        ];
    }
}
