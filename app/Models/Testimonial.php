<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Testimonial extends Model
{
    protected $fillable = [
        'name',
        'location',
        'quote',
        'image',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected $appends = [
        'avatar_url',
        'default_avatar_url',
        'has_custom_image'
    ];

    protected static function booted()
    {
        static::creating(function ($testimonial) {
            if (is_null($testimonial->sort_order)) {
                $testimonial->sort_order = static::max('sort_order') + 1;
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

    /**
     * Get the avatar URL - either custom uploaded or smart default
     */
    public function getAvatarUrlAttribute(): string
    {
        // If has custom uploaded image, return that
        if ($this->image && $this->hasCustomImage()) {
            return $this->getCustomImageUrl();
        }
        
        // Otherwise return smart default
        return $this->getDefaultAvatarUrlAttribute();
    }

    /**
     * Get custom uploaded image URL
     */
    protected function getCustomImageUrl(): string
    {
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }
        
        return asset('storage/' . $this->image);
    }

    /**
     * Check if testimonial has custom uploaded image
     */
    public function getHasCustomImageAttribute(): bool
    {
        return !empty($this->image) && $this->hasCustomImage();
    }

    /**
     * Verify if the image is a custom upload (exists in storage)
     */
    protected function hasCustomImage(): bool
    {
        if (!$this->image) return false;
        
        // If it's a URL, consider it custom
        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return true;
        }
        
        // Check if file exists in storage
        return Storage::disk('public')->exists($this->image);
    }

    /**
     * Get default avatar URL based on name matching
     */
    public function getDefaultAvatarUrlAttribute(): string
    {
        $defaultAvatarPath = $this->findMatchingDefaultAvatar();
        
        if ($defaultAvatarPath) {
            return asset($defaultAvatarPath);
        }
        
        // Ultimate fallback - use first available avatar
        $testimonialsPath = public_path('images/testimonials');
        if (File::exists($testimonialsPath)) {
            $files = File::files($testimonialsPath);
            if (count($files) > 0) {
                return asset('images/testimonials/' . $files[0]->getFilename());
            }
        }
        
        return asset('images/default-avatar.jpg');
    }

    /**
     * Find matching default avatar based on name
     */
    protected function findMatchingDefaultAvatar(): ?string
    {
        $testimonialsPath = public_path('images/testimonials');
        
        if (!File::exists($testimonialsPath)) {
            return null;
        }
        
        // Get available default avatars
        $availableAvatars = File::files($testimonialsPath);
        $availableNames = [];
        
        foreach ($availableAvatars as $file) {
            $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $availableNames[$filename] = 'images/testimonials/' . $file->getFilename();
        }
        
        // Clean name for matching (remove titles like H., Hj., Ustadz, Bapak)
        $cleanName = preg_replace('/^(H\.|Hj\.|Ustadz|Bapak|Ibu)\s+/i', '', $this->name);
        $cleanName = strtolower(trim($cleanName));
        
        // Try to match based on first name or last name
        $nameParts = explode(' ', $cleanName);
        
        foreach ($availableNames as $avatarName => $path) {
            $avatarNameLower = strtolower($avatarName);
            
            // Check each part of the name
            foreach ($nameParts as $part) {
                if (strlen($part) > 2) { // Skip very short words
                    if ($avatarNameLower === $part || strpos($avatarNameLower, $part) !== false) {
                        return $path;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Legacy compatibility - keep existing getter but make it use avatar_url
     */
    public function getImageUrlAttribute(): string
    {
        return $this->getAvatarUrlAttribute();
    }

    /**
     * Get available default avatars for admin interface
     */
    public static function getAvailableDefaultAvatars(): array
    {
        $testimonialsPath = public_path('images/testimonials');
        
        if (!File::exists($testimonialsPath)) {
            return [];
        }
        
        $avatars = [];
        $files = File::files($testimonialsPath);
        
        foreach ($files as $file) {
            $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $avatars[] = [
                'name' => $filename,
                'url' => asset('images/testimonials/' . $file->getFilename()),
                'filename' => $file->getFilename()
            ];
        }
        
        return $avatars;
    }
}
