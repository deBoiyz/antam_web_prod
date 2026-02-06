<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaService extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'api_url',
        'is_active',
        'is_default',
        'balance',
        'balance_checked_at',
        'success_count',
        'failure_count',
        'average_solve_time',
        'supported_types',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'balance' => 'decimal:4',
        'balance_checked_at' => 'datetime',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'average_solve_time' => 'integer',
        'supported_types' => 'array',
        'priority' => 'integer',
    ];

    protected $hidden = [
        'api_key',
    ];

    public const PROVIDERS = [
        '2captcha' => '2Captcha',
        'capsolver' => 'CapSolver',
        'anticaptcha' => 'Anti-Captcha',
        'manual' => 'Manual',
    ];

    public const CAPTCHA_TYPES = [
        'recaptcha_v2' => 'reCAPTCHA v2',
        'recaptcha_v3' => 'reCAPTCHA v3',
        'hcaptcha' => 'hCaptcha',
        'turnstile' => 'Cloudflare Turnstile',
        'image' => 'Image CAPTCHA',
        'funcaptcha' => 'FunCaptcha',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeSupportsType($query, string $type)
    {
        return $query->whereJsonContains('supported_types', $type);
    }

    public function supportsType(string $type): bool
    {
        return in_array($type, $this->supported_types ?? []);
    }

    public function incrementSuccess(int $solveTime = null): void
    {
        $this->increment('success_count');
        
        if ($solveTime) {
            $totalTime = ($this->average_solve_time ?? 0) * ($this->success_count - 1) + $solveTime;
            $this->update([
                'average_solve_time' => intval($totalTime / $this->success_count),
            ]);
        }
    }

    public function incrementFailure(): void
    {
        $this->increment('failure_count');
    }

    public function updateBalance(float $balance): void
    {
        $this->update([
            'balance' => $balance,
            'balance_checked_at' => now(),
        ]);
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->success_count + $this->failure_count;
        if ($total === 0) return 0;
        
        return round(($this->success_count / $total) * 100, 2);
    }

    public function getConfigAttribute(): array
    {
        return [
            'provider' => $this->provider,
            'api_key' => $this->api_key,
            'api_url' => $this->api_url,
        ];
    }

    public static function getDefaultService(): ?self
    {
        return self::active()->default()->first() 
            ?? self::active()->byPriority()->first();
    }

    public static function getServiceForType(string $type): ?self
    {
        return self::active()
            ->supportsType($type)
            ->byPriority()
            ->first();
    }
}
