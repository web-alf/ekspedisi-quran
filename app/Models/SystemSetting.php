<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get setting value with proper type casting
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'number' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value
        };
    }

    /**
     * Set value with type validation
     */
    public function setTypedValue($value)
    {
        $this->value = match ($this->type) {
            'number' => (string) $value,
            'boolean' => $value ? 'true' : 'false',
            'json' => json_encode($value),
            default => (string) $value
        };

        return $this->save();
    }

    /**
     * Get setting value by key (static method)
     */
    public static function getValue(string $key, mixed $default = null)
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->typed_value;
    }

    /**
     * Set setting value by key (static method)
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => match ($type) {
                    'number' => (string) $value,
                    'boolean' => $value ? 'true' : 'false',
                    'json' => json_encode($value),
                    default => (string) $value
                },
                'type' => $type,
                'description' => $description,
            ]
        );

        return $setting;
    }

    /**
     * Get all public settings
     */
    public static function getPublicSettings()
    {
        return self::where('is_public', true)
            ->get()
            ->pluck('typed_value', 'key')
            ->toArray();
    }

    /**
     * Get settings by type
     */
    public static function getByType($type)
    {
        return self::where('type', $type)
            ->get()
            ->pluck('typed_value', 'key')
            ->toArray();
    }

    /**
     * Scope: Public settings only
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope: Filter by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Search by key or description
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('key', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
    }
}
