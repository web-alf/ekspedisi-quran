<?php

use App\Models\Donatur;
use App\Models\MushafRequest;
use App\Models\User;
use Database\Seeders\JenisQuranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create role if not exists
    $role = Role::firstOrCreate(['name' => 'super-admin']);

    // Create admin user with active status
    $this->admin = User::factory()->create([
        'is_active' => true,
    ]);
    $this->admin->assignRole($role);

    // Create permissions if needed
    Permission::firstOrCreate(['name' => 'mushaf-requests.process']);
    Permission::firstOrCreate(['name' => 'mushaf-requests.update']);
    Permission::firstOrCreate(['name' => 'mushaf-requests.delete']);

    // Assign all permissions to role
    $role->givePermissionTo(['mushaf-requests.process', 'mushaf-requests.update', 'mushaf-requests.delete']);

    // `pengiriman.jenis_quran_id` punya foreign key ke tabel ini, dan tabelnya
    // kosong di database test sehingga proses ke pengiriman gagal.
    $this->seed(JenisQuranSeeder::class);
});

test('process to shipment fails when approved quantity is zero', function () {
    $mushafRequest = MushafRequest::factory()->create([
        'status' => 'approved',
        'jumlah_mushaf_a5' => 100,
        'jumlah_mushaf_a6' => 0,
        'jumlah_iqra' => 0,
        // Jumlah disetujui sudah ditetapkan (penanda terisi) tetapi hasilnya 0:
        // tidak ada yang bisa dikirim, jadi proses harus ditolak.
        'jumlah_mushaf_a5_approved' => 0,
        'jumlah_mushaf_a6_approved' => 0,
        'jumlah_iqra_approved' => 0,
        'jumlah_mushaf_approved' => 0,
    ]);

    $donatur = Donatur::factory()->create();

    $response = $this->actingAs($this->admin)
        ->post("/admin/mushaf-requests/{$mushafRequest->id}/process", [
            'donatur_id' => $donatur->id,
            'tanggal_wakaf' => now()->format('Y-m-d'),
        ]);

    $response->assertSessionHasErrors();
});

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

    // Controller mengembalikan flash 'error', bukan validation error:
    // `back()->with('error', ...)`.
    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('mushaf_requests', [
        'id' => $mushafRequest->id,
    ]);
});
