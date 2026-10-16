<?php

use App\Models\Donatur;
use App\Models\User;
use App\Models\WakafItem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses()->group('admin', 'wakaf-items');

beforeEach(function () {
    // Ensure permissions exist
    Permission::firstOrCreate(['name' => 'donatur.update']);
    Permission::firstOrCreate(['name' => 'donatur.read']);

    $role = Role::firstOrCreate(['name' => 'test-cs', 'guard_name' => 'web']);
    $role->givePermissionTo(['donatur.update', 'donatur.read']);

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('test-cs');
});

test('authorized user can update wakaf item doa and relationship', function () {
    $donatur = Donatur::factory()->create();
    $wakafItem = WakafItem::factory()->create([
        'donatur_id' => $donatur->id,
        'status' => 'pending',
        'wakif_name' => 'Original Name',
        'doa_request' => 'Original Doa',
        'relationship_to_donatur' => 'Diri sendiri',
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/admin/donatur/{$donatur->id}/wakaf-items/{$wakafItem->id}", [
            'wakif_name' => 'Updated Name',
            'doa_request' => 'Semoga diberkahkan',
            'relationship_to_donatur' => 'Anak',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Item wakaf berhasil diperbarui!');

    $this->assertDatabaseHas('wakaf_items', [
        'id' => $wakafItem->id,
        'wakif_name' => 'Updated Name',
        'doa_request' => 'Semoga diberkahkan',
        'relationship_to_donatur' => 'Anak',
    ]);
});

test('processed wakaf item cannot be updated', function () {
    $donatur = Donatur::factory()->create();
    $wakafItem = WakafItem::factory()->create([
        'donatur_id' => $donatur->id,
        'status' => 'processed',
        'wakif_name' => 'Original Name',
        'doa_request' => 'Original Doa',
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/admin/donatur/{$donatur->id}/wakaf-items/{$wakafItem->id}", [
            'wakif_name' => 'Updated Name',
            'doa_request' => 'Semoga diberkahkan',
        ]);

    $response->assertSessionHasErrors(['error']);

    $this->assertDatabaseHas('wakaf_items', [
        'id' => $wakafItem->id,
        'wakif_name' => 'Original Name',
        'doa_request' => 'Original Doa',
    ]);
});

test('unauthorized user cannot update wakaf item', function () {
    $donatur = Donatur::factory()->create();
    $wakafItem = WakafItem::factory()->create([
        'donatur_id' => $donatur->id,
        'status' => 'pending',
    ]);

    $unauthorizedUser = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($unauthorizedUser)
        ->patch("/admin/donatur/{$donatur->id}/wakaf-items/{$wakafItem->id}", [
            'wakif_name' => 'Updated Name',
        ]);

    $response->assertForbidden();
});
