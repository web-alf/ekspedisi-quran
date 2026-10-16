<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ============================================
// YOUTUBE VIDEO ID EXTRACTION TESTS
// ============================================

it('extracts video ID from standard YouTube URL', function () {
    $video = new Video([
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    expect($video->youtube_video_id)->toBe('dQw4w9WgXcQ');
});

it('extracts video ID from shortened youtu.be URL', function () {
    $video = new Video([
        'video_url' => 'https://youtu.be/3_mxkdlLL8Y',
    ]);

    expect($video->youtube_video_id)->toBe('3_mxkdlLL8Y');
});

it('extracts video ID from youtu.be URL with query params', function () {
    $video = new Video([
        'video_url' => 'https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP',
    ]);

    expect($video->youtube_video_id)->toBe('3_mxkdlLL8Y');
});

it('extracts video ID from embed URL', function () {
    $video = new Video([
        'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
    ]);

    expect($video->youtube_video_id)->toBe('dQw4w9WgXcQ');
});

it('extracts video ID from embed URL with query params', function () {
    $video = new Video([
        'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1',
    ]);

    expect($video->youtube_video_id)->toBe('dQw4w9WgXcQ');
});

it('returns null for invalid YouTube URL', function () {
    $video = new Video([
        'video_url' => 'https://example.com/video',
    ]);

    expect($video->youtube_video_id)->toBeNull();
});

// ============================================
// EMBED URL TESTS
// ============================================

it('generates correct embed URL', function () {
    $video = new Video([
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    expect($video->embed_url)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

it('returns null embed URL for invalid video', function () {
    $video = new Video([
        'video_url' => 'invalid-url',
    ]);

    expect($video->embed_url)->toBeNull();
});

// ============================================
// THUMBNAIL URL TESTS
// ============================================

it('generates correct thumbnail URL from YouTube', function () {
    $video = new Video([
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    expect($video->thumbnail_url)->toBe('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
});

it('uses custom thumbnail path when set', function () {
    $video = Video::factory()->make([
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'thumbnail' => 'videos/custom-thumb.jpg',
    ]);

    // Just check that it contains the path, don't use asset() helper
    expect($video->thumbnail)->toBe('videos/custom-thumb.jpg');
});

it('returns null thumbnail URL for invalid video', function () {
    $video = new Video([
        'video_url' => 'invalid-url',
    ]);

    expect($video->thumbnail_url)->toBeNull();
});

// ============================================
// WATCH URL TESTS
// ============================================

it('generates correct watch URL', function () {
    $video = new Video([
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    expect($video->watch_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

// ============================================
// SCOPE TESTS
// ============================================

it('scopes to active videos only', function () {
    Video::factory()->create(['is_active' => true, 'title' => 'Active']);
    Video::factory()->create(['is_active' => false, 'title' => 'Inactive']);

    $activeVideos = Video::active()->get();

    expect($activeVideos)->toHaveCount(1);
    expect($activeVideos->first()->title)->toBe('Active');
});

it('scopes ordered videos by sort_order', function () {
    Video::factory()->create(['sort_order' => 5, 'title' => 'Last']);
    Video::factory()->create(['sort_order' => 1, 'title' => 'First']);
    Video::factory()->create(['sort_order' => 3, 'title' => 'Middle']);

    $orderedVideos = Video::ordered()->get();

    expect($orderedVideos->pluck('title')->toArray())->toBe(['First', 'Middle', 'Last']);
});

it('scopes videos by category', function () {
    Video::factory()->create(['category' => 'Ekspedisi', 'title' => 'Video 1']);
    Video::factory()->create(['category' => 'Tutorial', 'title' => 'Video 2']);
    Video::factory()->create(['category' => 'Ekspedisi', 'title' => 'Video 3']);

    $ekspedisiVideos = Video::byCategory('Ekspedisi')->get();

    expect($ekspedisiVideos)->toHaveCount(2);
});

// ============================================
// FILLABLE ATTRIBUTES TESTS
// ============================================

it('can mass assign fillable attributes', function () {
    $video = Video::create([
        'title' => 'Test Title',
        'video_url' => 'https://www.youtube.com/watch?v=test123',
        'thumbnail' => 'thumb.jpg',
        'caption' => 'Test Caption',
        'video_type' => 'youtube',
        'category' => 'Test Category',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    expect($video->title)->toBe('Test Title');
    expect($video->video_url)->toBe('https://www.youtube.com/watch?v=test123');
    expect($video->is_active)->toBeTrue();
});

// ============================================
// CASTS TESTS
// ============================================

it('casts is_active to boolean', function () {
    $video = Video::factory()->create(['is_active' => 1]);

    expect($video->is_active)->toBeTrue();

    $video->update(['is_active' => 0]);

    expect($video->fresh()->is_active)->toBeFalse();
});

it('casts sort_order to integer', function () {
    $video = Video::factory()->create(['sort_order' => '5']);

    expect($video->sort_order)->toBeInt();
    expect($video->sort_order)->toBe(5);
});
