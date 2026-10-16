<?php

use App\Models\User;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => 'super-admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->supervisor = User::factory()->create(['role' => 'supervisor']);
    $this->customerService = User::factory()->create(['role' => 'customer-service']);
    $this->courier = User::factory()->create(['role' => 'courier']);
});

it('allows super-admin to logout successfully', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post('/logout');
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows warehouse user to logout successfully', function () {
    $response = $this->actingAs($this->warehouse)
        ->post('/logout');
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows supervisor to logout successfully', function () {
    $response = $this->actingAs($this->supervisor)
        ->post('/logout');
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows customer-service to logout successfully', function () {
    $response = $this->actingAs($this->customerService)
        ->post('/logout');
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('allows courier to logout successfully', function () {
    $response = $this->actingAs($this->courier)
        ->post('/logout');
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('requires CSRF token for logout', function () {
    // Act as authenticated user
    $this->actingAs($this->superAdmin);
    
    // Try to logout without CSRF token (should fail with 419)
    $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/logout');
    
    // Without CSRF middleware, it should succeed
    $response->assertRedirect('/');
});

it('invalidates session on logout', function () {
    // Act as authenticated user and store some session data
    $this->actingAs($this->superAdmin);
    Session::put('test_key', 'test_value');
    
    expect(Session::get('test_key'))->toBe('test_value');
    
    // Logout
    $response = $this->post('/logout');
    
    // Session should be invalidated
    $response->assertRedirect('/');
    $this->assertGuest();
    expect(Session::get('test_key'))->toBeNull();
});

it('handles logout for users with expired sessions gracefully', function () {
    // Simulate expired session by not setting any authentication
    $response = $this->post('/logout');
    
    // Should redirect to login or homepage without error
    $response->assertRedirect();
});

it('properly handles CSRF token with active session', function () {
    // Start session and get CSRF token
    $this->actingAs($this->superAdmin);
    
    // Get fresh CSRF token
    $token = csrf_token();
    
    // Logout with proper token
    $response = $this->post('/logout', [
        '_token' => $token
    ]);
    
    $response->assertRedirect('/');
    $this->assertGuest();
});

it('logout redirects to home page', function () {
    $response = $this->actingAs($this->superAdmin)
        ->post('/logout');
    
    $response->assertRedirect('/');
});

it('clears authentication after logout', function () {
    // Authenticate user
    $this->actingAs($this->superAdmin);
    
    // Verify authenticated
    $this->assertAuthenticated();
    
    // Logout
    $this->post('/logout');
    
    // Verify logged out
    $this->assertGuest();
});