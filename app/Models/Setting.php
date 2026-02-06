<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    public const TYPES = [
        'string' => 'String',
        'integer' => 'Integer',
        'boolean' => 'Boolean',
        'json' => 'JSON',
    ];

    public const GROUPS = [
        'general' => 'General',
        'bot' => 'Bot Settings',
        'captcha' => 'CAPTCHA',
        'proxy' => 'Proxy',
        'notification' => 'Notifications',
    ];

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValueAttribute($value)
    {
        if ($this->type === 'json' && is_array($value)) {
            $value = json_encode($value);
        } elseif ($this->type === 'boolean') {
            $value = $value ? 'true' : 'false';
        }

        if ($this->is_encrypted && $value) {
            $value = Crypt::encryptString($value);
        }

        $this->attributes['value'] = $value;
    }

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value, array $attributes = []): self
    {
        $setting = self::firstOrNew(['key' => $key]);
        
        foreach ($attributes as $attr => $val) {
            $setting->{$attr} = $val;
        }
        
        $setting->value = $value;
        $setting->save();
        
        return $setting;
    }

    public static function getGroup(string $group): array
    {
        return self::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    public static function getAllGrouped(): array
    {
        return self::all()
            ->groupBy('group')
            ->map(fn($items) => $items->pluck('value', 'key'))
            ->toArray();
    }
}
