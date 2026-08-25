<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Video extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'video_url',
        'thumbnail',
        'caption',
        'video_type',
        'category',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active videos
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Extract YouTube video ID from various URL formats
     */
    public function getYoutubeVideoIdAttribute(): ?string
    {
        $url = $this->video_url;

        // youtu.be format
        if (preg_match('/youtu\.be\/([^?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // youtube.com/watch?v= format
        if (preg_match('/[?&]v=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // youtube.com/embed/ format
        if (preg_match('/embed\/([^?]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get embed URL for YouTube
     */
    public function getEmbedUrlAttribute(): ?string
    {
        $videoId = $this->youtube_video_id;

        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
    }

    /**
     * Get thumbnail URL (auto-generate from YouTube if not set)
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail) {
            return asset('storage/' . $this->thumbnail);
        }

        $videoId = $this->youtube_video_id;

        return $videoId ? "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg" : null;
    }

    /**
     * Get watch URL for YouTube
     */
    public function getWatchUrlAttribute(): ?string
    {
        $videoId = $this->youtube_video_id;

        return $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null;
    }
}
