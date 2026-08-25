<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'super-admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

it('prevents API calls when user is not authenticated', function () {
    // Make an unauthenticated request to the jobs API endpoint
    $response = $this->getJson('/api/jobs/active');
    
    // Should return 401 for unauthenticated users
    $response->assertUnauthorized();
    
    // Should return proper JSON error response
    $response->assertJson([
        'success' => false,
        'message' => 'User session expired. Please login again.',
        'code' => 'SESSION_EXPIRED',
    ]);
});

it('allows API calls for authenticated admin users', function () {
    // Make authenticated request (empty response is OK)
    $response = $this->actingAs($this->admin)
        ->getJson('/api/jobs/active');
    
    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'data' => [
            'jobs'
        ]
    ]);
    
    // Should return empty array when no jobs exist
    expect($response->json('data.jobs'))->toBeArray();
});

it('allows API calls for authenticated warehouse users', function () {
    // Make authenticated request (empty response is OK)
    $response = $this->actingAs($this->warehouse)
        ->getJson('/api/jobs/active');
    
    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'data' => [
            'jobs'
        ]
    ]);
    
    // Should return empty array when no jobs exist
    expect($response->json('data.jobs'))->toBeArray();
});

it('validates authentication middleware is properly configured', function () {
    // Test various auth scenarios
    
    // Unauthenticated request
    $response = $this->getJson('/api/jobs/active');
    $response->assertUnauthorized();
    
    // Admin authenticated request
    $response = $this->actingAs($this->admin)->getJson('/api/jobs/active');
    $response->assertOk();
    
    // Warehouse authenticated request
    $response = $this->actingAs($this->warehouse)->getJson('/api/jobs/active');
    $response->assertOk();
});

it('returns proper JSON structure for authenticated requests', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/jobs/active');
    
    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'message',
        'data' => [
            'jobs',
            'user_id',
            'total_count'
        ]
    ]);
    
    expect($response->json('success'))->toBe(true);
    expect($response->json('data.user_id'))->toBe($this->admin->id);
    expect($response->json('data.jobs'))->toBeArray();
});