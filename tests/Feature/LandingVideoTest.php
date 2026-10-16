<?php

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed video settings
    $this->seed(\Database\Seeders\VideoSettingsSeeder::class);
});

// ============================================
// LANDING PAGE VIDEO SECTION TESTS
// ============================================

it('displays videos on landing page', function () {
    Video::factory()->create([
        'title' => 'Test Video 1',
        'video_url' => 'https://www.youtube.com/watch?v=test123',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Video::factory()->create([
        'title' => 'Test Video 2',
        'video_url' => 'https://www.youtube.com/watch?v=test456',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->component('Landing')
            ->has('videos')
            ->where('videos', function ($videos) {
                // Videos is passed as a Collection, so we use count() method
                return $videos->count() === 2;
            })
    );
});

it('only shows active videos on landing page', function () {
    Video::factory()->create([
        'title' => 'Active Video',
        'is_active' => true,
    ]);

    Video::factory()->create([
        'title' => 'Inactive Video',
        'is_active' => false,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->where('videos', function ($videos) {
            return $videos->count() === 1 && $videos->first()['title'] === 'Active Video';
        })
    );
});

it('shows videos in correct order on landing page', function () {
    Video::factory()->create([
        'title' => 'Second',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    Video::factory()->create([
        'title' => 'First',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Video::factory()->create([
        'title' => 'Third',
        'sort_order' => 3,
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->where('videos', function ($videos) {
            $titles = $videos->pluck('title')->toArray();
            return $titles === ['First', 'Second', 'Third'];
        })
    );
});

it('videos are passed to landing page component', function () {
    Video::factory()->create(['is_active' => true]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->has('videos')
            ->has('settings')
    );
});

it('videos collection contains expected video data', function () {
    Video::factory()->create([
        'title' => 'Test Video',
        'video_url' => 'https://www.youtube.com/watch?v=test789',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->where('videos', function ($videos) {
            $video = $videos->first();
            return $video['title'] === 'Test Video'
                && str_contains($video['video_url'], 'test789');
        })
    );
});

it('passes video settings to landing page', function () {
    $response = $this->get('/');

    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->has('settings')
            ->where('settings.landing_video_enabled', '1')
            ->where('settings.landing_video_title', 'Video Ekspedisi')
            ->where('settings.landing_video_subtitle', 'Dokumentasi video perjalanan mushaf Al-Qur\'an')
    );
});
