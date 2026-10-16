<?php

use App\Models\User;
use App\Models\Donatur;
use App\Models\Pengiriman;
use App\Models\WakafBatch;
use App\Models\DailyPackingTask;
use App\Models\PackingBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    // Create super admin user for testing
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('super-admin');

    // Create warehouse user to be deleted
    $this->userToDelete = User::factory()->create(['is_active' => true]);
    $this->userToDelete->assignRole('warehouse');
});

it('prevents user from deleting themselves', function () {
    $response = $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->admin));

    $response->assertSessionHasErrors('error');
    expect(User::find($this->admin->id))->not->toBeNull();
});

it('can delete user without related data', function () {
    $response = $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->userToDelete));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');
    expect(User::find($this->userToDelete->id))->toBeNull();
});

it('prevents deletion when user has pengiriman', function () {
    // Create pengiriman owned by user to be deleted
    $pengiriman = Pengiriman::factory()->create([
        'created_by' => $this->userToDelete->id,
    ]);

    expect($pengiriman->created_by)->toBe($this->userToDelete->id);

    // Try to delete user
    $response = $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->userToDelete));

    // Should be prevented with error message
    $response->assertSessionHasErrors('error');

    // Check user is NOT deleted
    expect(User::find($this->userToDelete->id))->not->toBeNull();

    // Check pengiriman still belongs to original user
    $pengiriman->refresh();
    expect($pengiriman->created_by)->toBe($this->userToDelete->id);
});

it('prevents deletion when user has donatur', function () {
    $donatur = Donatur::factory()->create([
        'created_by' => $this->userToDelete->id,
    ]);

    expect($donatur->created_by)->toBe($this->userToDelete->id);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->userToDelete));

    $response->assertSessionHasErrors('error');
    expect(User::find($this->userToDelete->id))->not->toBeNull();

    $donatur->refresh();
    expect($donatur->created_by)->toBe($this->userToDelete->id);
});

it('shows detailed error message when user has multiple related data', function () {
    // Create multiple related data
    $donatur = Donatur::factory()->create([
        'created_by' => $this->userToDelete->id,
    ]);

    $pengiriman = Pengiriman::factory()->create([
        'created_by' => $this->userToDelete->id,
    ]);

    // Try to delete user
    $response = $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->userToDelete));

    // Should show detailed error message
    $response->assertSessionHasErrors('error');

    // Check user NOT deleted
    expect(User::find($this->userToDelete->id))->not->toBeNull();

    // Check data still belongs to original user
    $donatur->refresh();
    $pengiriman->refresh();

    expect($donatur->created_by)->toBe($this->userToDelete->id);
    expect($pengiriman->created_by)->toBe($this->userToDelete->id);
});

it('requires users.delete permission to delete user', function () {
    $regularUser = User::factory()->create(['is_active' => true]);
    $regularUser->assignRole('customer-service');

    $response = $this->actingAs($regularUser)
        ->delete(route('admin.users.destroy', $this->userToDelete));

    $response->assertStatus(403);
    expect(User::find($this->userToDelete->id))->not->toBeNull();
});
