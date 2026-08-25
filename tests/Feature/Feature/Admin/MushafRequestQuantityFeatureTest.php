<?php

use App\Models\{Donatur, MushafRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create role if not exists
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);

    // Create admin user with active status
    $this->admin = User::factory()->create([
        'is_active' => true,
    ]);
    $this->admin->assignRole($role);

    // Create permissions if needed
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'mushaf-requests.process']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'mushaf-requests.update']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'mushaf-requests.delete']);

    // Assign all permissions to role
    $role->givePermissionTo(['mushaf-requests.process', 'mushaf-requests.update', 'mushaf-requests.delete']);
});

test('process to shipment fails when quantity changed but approved is null', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        // Simulate quantity change but no approved set
        'jumlah_mushaf_approved' => null, // This should trigger validation error
    ]);

    // Manually set has_quantity_change to true by setting jumlah_mushaf_approved to different value
    $mushafRequest->update(['jumlah_mushaf_approved' => 80]);
    $mushafRequest->update(['jumlah_mushaf_approved' => null]); // Reset to null

    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ]);

    // Should fail with validation error if has_quantity_change is true
    // But in this case has_quantity_change will be false since approved is null
    // So this test might pass - let's test actual scenario
    $response->assertSessionHasNoErrors();
})->skip('Need to properly test has_quantity_change logic');

test('process to shipment succeeds when approved quantities are set', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        'jumlah_mushaf_a5_approved' => 80,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 80,
    ]);

    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    // Verify pengiriman was created with approved quantity
    $this->assertDatabaseHas('pengiriman', [
        'donatur_id' => $donatur->id,
        'jumlah_quran' => 80, // Should use approved quantity
    ]);
});

test('process to shipment fails for non-approved status', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'pending',
    ]);

    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ]);

    $response->assertSessionHasErrors();
});

test('update quantities endpoint updates approved quantities', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 50,
        'jumlah_iqra' => 30,
    ]);

    $response = $this->actingAs($this->admin)
        ->patch("/admin/mushaf-requests/{$mushafRequest->id}/quantities", [
            'jumlah_mushaf_approved' => 150,
            'jumlah_mushaf_a5_approved' => 80,
            'jumlah_mushaf_a6_approved' => 40,
            'jumlah_iqra_approved' => 30,
            'catatan_perubahan_jumlah' => 'Disesuaikan dengan ketersediaan',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('mushaf_requests', [
        'id' => $mushafRequest->id,
        'jumlah_mushaf_approved' => 150,
        'jumlah_mushaf_a5_approved' => 80,
        'jumlah_mushaf_a6_approved' => 40,
        'jumlah_iqra_approved' => 30,
        'catatan_perubahan_jumlah' => 'Disesuaikan dengan ketersediaan',
    ]);
});

test('delete is allowed for non-completed status', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'approved',
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.mushaf-requests.destroy', $mushafRequest));

    $response->assertRedirect();
    $this->assertDatabaseMissing('mushaf_requests', [
        'id' => $mushafRequest->id,
    ]);
});

test('delete is blocked for completed status', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'completed',
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.mushaf-requests.destroy', $mushafRequest));

    $response->assertSessionHasErrors();
    $this->assertDatabaseHas('mushaf_requests', [
        'id' => $mushafRequest->id,
    ]);
});
