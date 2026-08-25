<?php

use App\Models\User;
use App\Models\Video;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed required data
    $this->seed(\Database\Seeders\StatusPengirimanSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\VideoSettingsSeeder::class);

    // Create users after roles are seeded
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');

    $this->csUser = User::factory()->create(['is_active' => true]);
    $this->csUser->assignRole('customer-service');
});

// ============================================
// INDEX TESTS
// ============================================

it('can display video index page', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/videos');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) =>
        $page->component('Admin/Videos/Index')
            ->has('videos')
            ->has('videoSettings')
    );
});

it('requires settings.read permission to view video index', function () {
    $userWithoutPermission = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($userWithoutPermission)
        ->get('/admin/videos');

    $response->assertForbidden();
});

// ============================================
// CREATE TESTS
// ============================================

it('can create a video with valid YouTube URL', function () {
    $videoData = [
        'title' => 'Test Video Title',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'caption' => 'Test caption',
        'category' => 'Test Category',
        'sort_order' => 1,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/videos', $videoData);

    $response->assertRedirect('/admin/videos');
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('videos', [
        'title' => 'Test Video Title',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'video_type' => 'youtube',
    ]);
});

it('can create a video with youtu.be URL format', function () {
    $videoData = [
        'title' => 'Short YouTube URL',
        'video_url' => 'https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP',
        'caption' => null,
        'category' => null,
        'sort_order' => null,
        'is_active' => true,
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/videos', $videoData);

    $response->assertRedirect('/admin/videos');

    $this->assertDatabaseHas('videos', [
        'title' => 'Short YouTube URL',
        'video_url' => 'https://youtu.be/3_mxkdlLL8Y?si=Qhr1VIjv78FYh7iP',
        'sort_order' => 0, // Should default to 0
    ]);
});

it('cannot create a video with invalid URL', function () {
    $videoData = [
        'title' => 'Invalid Video',
        'video_url' => 'https://example.com/video',
        'caption' => 'Test',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->admin)
        ->post('/admin/videos', $videoData);

    $response->assertSessionHasErrors('video_url');
});

it('requires settings.write permission to create video', function () {
    $videoData = [
        'title' => 'Test Video',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->csUser)
        ->post('/admin/videos', $videoData);

    $response->assertForbidden();
});

it('validates required fields when creating video', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/videos', []);

    $response->assertSessionHasErrors(['title', 'video_url']);
});

// ============================================
// UPDATE TESTS
// ============================================

it('can update a video', function () {
    $video = Video::factory()->create([
        'title' => 'Old Title',
        'video_url' => 'https://www.youtube.com/watch?v=old123',
    ]);

    $updateData = [
        'title' => 'New Title',
        'video_url' => 'https://www.youtube.com/watch?v=new456',
        'caption' => 'Updated caption',
        'category' => 'Updated Category',
        'sort_order' => 5,
        'is_active' => false,
    ];

    $response = $this->actingAs($this->admin)
        ->put("/admin/videos/{$video->id}", $updateData);

    $response->assertRedirect('/admin/videos');

    $this->assertDatabaseHas('videos', [
        'id' => $video->id,
        'title' => 'New Title',
        'video_url' => 'https://www.youtube.com/watch?v=new456',
        'is_active' => false,
    ]);
});

it('cannot update video with invalid YouTube URL', function () {
    $video = Video::factory()->create();

    $updateData = [
        'title' => 'New Title',
        'video_url' => 'https://vimeo.com/123456',
    ];

    $response = $this->actingAs($this->admin)
        ->put("/admin/videos/{$video->id}", $updateData);

    $response->assertSessionHasErrors('video_url');
});

// ============================================
// DELETE TESTS
// ============================================

it('can delete a video', function () {
    $video = Video::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete("/admin/videos/{$video->id}");

    $response->assertRedirect('/admin/videos');

    $this->assertDatabaseMissing('videos', [
        'id' => $video->id,
    ]);
});

it('requires settings.delete permission to delete video', function () {
    $video = Video::factory()->create();

    $response = $this->actingAs($this->csUser)
        ->delete("/admin/videos/{$video->id}");

    $response->assertForbidden();
});

// ============================================
// TOGGLE STATUS TESTS
// ============================================

it('can toggle video status', function () {
    $video = Video::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/videos/{$video->id}/toggle-status");

    $response->assertRedirect();

    $this->assertDatabaseHas('videos', [
        'id' => $video->id,
        'is_active' => false,
    ]);
});

// ============================================
// UPDATE ORDER TESTS
// ============================================

it('can update video order', function () {
    $video1 = Video::factory()->create(['sort_order' => 1]);
    $video2 = Video::factory()->create(['sort_order' => 2]);

    $response = $this->actingAs($this->admin)
        ->post('/admin/videos/update-order', [
            'items' => [
                ['id' => $video1->id, 'sort_order' => 5],
                ['id' => $video2->id, 'sort_order' => 3],
            ],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('videos', [
        'id' => $video1->id,
        'sort_order' => 5,
    ]);

    $this->assertDatabaseHas('videos', [
        'id' => $video2->id,
        'sort_order' => 3,
    ]);
});

// ============================================
// SETTINGS UPDATE TESTS
// ============================================

it('can update video settings', function () {
    $setting = Setting::where('key', 'landing_video_title')->first();

    $response = $this->actingAs($this->admin)
        ->post('/admin/videos/update-settings', [
            'settings' => [
                [
                    'id' => $setting->id,
                    'value' => 'Custom Video Title',
                ],
            ],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('settings', [
        'id' => $setting->id,
        'value' => 'Custom Video Title',
    ]);
});
