<?php

use App\Models\JobProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires authentication to access active jobs', function () {
    $response = $this->getJson('/api/jobs/active');

    $response->assertUnauthorized();
});

it('can get active jobs for authenticated user', function () {
    // Create a job progress for the authenticated user
    JobProgress::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'processing',
    ]);

    // Create a job progress for another user (should not be returned)
    $otherUser = User::factory()->create();
    JobProgress::factory()->create([
        'user_id' => $otherUser->id,
        'status' => 'processing',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/jobs/active');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'success',
            'data' => [
                'jobs',
                'count',
            ],
        ]);

    $data = $response->json('data');
    expect($data['count'])->toBe(1);
    expect($data['jobs'])->toHaveCount(1);
    expect($data['jobs'][0]['id'])->toBe(1);
});

it('only returns active jobs', function () {
    // Create jobs with different statuses
    JobProgress::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'processing',
    ]);

    JobProgress::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'completed',
    ]);

    JobProgress::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'failed',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/jobs/active');

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data['count'])->toBe(1); // Only processing jobs should be returned
});

it('returns empty data when no active jobs exist', function () {
    $response = $this->actingAs($this->user)->getJson('/api/jobs/active');

    // Should return successful response with empty data when no jobs exist
    $response->assertSuccessful()
        ->assertJson([
            'success' => true,
            'data' => [
                'jobs' => [],
                'count' => 0,
            ],
        ]);
});
