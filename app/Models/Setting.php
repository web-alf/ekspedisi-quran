<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'label',
        'value',
        'type',
        'description',
        'options',
        'group',
        'sort_order',
        'is_public',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('app_settings');
            Cache::forget('public_settings');
            Cache::forget('basic_settings_for_layout');
        });

        static::deleted(function () {
            Cache::forget('app_settings');
            Cache::forget('public_settings');
            Cache::forget('basic_settings_for_layout');
        });
    }

    public static function get(string $key, mixed $default = null)
    {
        $settings = Cache::rememberForever('app_settings', function () {
            return static::where('is_active', true)->pluck('value', 'key');
        });

        return $settings->get($key, $default);
    }

    public static function getPublic(string $key, mixed $default = null)
    {
        $settings = Cache::rememberForever('public_settings', function () {
            return static::where('is_active', true)
                ->where('is_public', true)
                ->pluck('value', 'key');
        });

        return $settings->get($key, $default);
    }

    public static function set($key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function getValueAttribute($value)
    {
        switch ($this->type) {
            case 'boolean':
                return (bool) $value;
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'json':
                // Don't auto-decode JSON to keep it as string for display
                // Only decode when explicitly needed
                return $value;
            default:
                return $value;
        }
    }

    public function getDecodedValueAttribute()
    {
        if ($this->type === 'json' && $this->attributes['value']) {
            return json_decode($this->attributes['value'], true);
        }

        return $this->value;
    }

    public function setValueAttribute($value)
    {
        switch ($this->type) {
            case 'boolean':
                $this->attributes['value'] = $value ? '1' : '0';
                break;
            case 'json':
                // Only encode if it's not already a JSON string
                if (is_array($value) || is_object($value)) {
                    $this->attributes['value'] = json_encode($value);
                } else {
                    $this->attributes['value'] = $value;
                }
                break;
            default:
                $this->attributes['value'] = $value;
                break;
        }
    }

    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
