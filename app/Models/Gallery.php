<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Gallery extends Model
{
    protected $fillable = [
        'title',
        'image',
        'caption',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected $appends = [
        'image_url'
    ];

    protected static function booted()
    {
        static::creating(function ($gallery) {
            if (is_null($gallery->sort_order)) {
                $gallery->sort_order = static::max('sort_order') + 1;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at', 'desc');
    }


    public function getImageUrlAttribute(): string
    {
        if (!$this->image) {
            return '';
        }
        
        // If it's already a full URL, return as is
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }
        
        // If it starts with /, it's already a public path
        if (str_starts_with($this->image, '/')) {
            return asset($this->image);
        }
        
        // Check if the image exists in public directory first (for legacy/seeded images)
        $publicPath = public_path($this->image);
        if (file_exists($publicPath)) {
            return asset($this->image);
        }
        
        // Otherwise, assume it's in storage
        return asset('storage/' . $this->image);
    }
}
