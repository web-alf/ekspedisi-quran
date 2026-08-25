<?php

use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::firstOrCreate(['name' => 'shipments.read', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'qr.generate', 'guard_name' => 'web']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(StatusPengirimanSeeder::class);

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->givePermissionTo(['shipments.read', 'qr.generate']);
});

it('shows pengiriman with pemesanan status on generate qr page', function () {
    $pemesananStatusId = StatusPengiriman::where('slug', 'pemesanan')->value('id');

    $pengiriman = Pengiriman::factory()->create([
        'status_id' => $pemesananStatusId,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/pengiriman?mode=generate-qr');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pengiriman/GenerateQR')
        ->has('pengiriman.data')
        ->where('pengiriman.data', fn ($data) => $data->contains('id', $pengiriman->id))
    );
});

it('shows pengiriman with packing status on generate qr page', function () {
    $packingStatusId = StatusPengiriman::where('slug', 'packing')->value('id');

    $pengiriman = Pengiriman::factory()->create([
        'status_id' => $packingStatusId,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/pengiriman?mode=generate-qr');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pengiriman/GenerateQR')
        ->has('pengiriman.data')
        ->where('pengiriman.data', fn ($data) => $data->contains('id', $pengiriman->id))
    );
});

it('hides pengiriman with final status diterima on generate qr page', function () {
    $diterimaStatusId = StatusPengiriman::where('slug', 'diterima')->value('id');

    $pengiriman = Pengiriman::factory()->create([
        'status_id' => $diterimaStatusId,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/pengiriman?mode=generate-qr');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pengiriman/GenerateQR')
        ->where('pengiriman.data', fn ($data) => ! $data->contains('id', $pengiriman->id))
    );
});

it('hides pengiriman with final status batal on generate qr page', function () {
    $batalStatusId = StatusPengiriman::where('slug', 'batal')->value('id');

    $pengiriman = Pengiriman::factory()->create([
        'status_id' => $batalStatusId,
    ]);

    $response = $this->actingAs($this->user)->get('/admin/pengiriman?mode=generate-qr');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pengiriman/GenerateQR')
        ->where('pengiriman.data', fn ($data) => ! $data->contains('id', $pengiriman->id))
    );
});

it('returns stats covering all active non-final statuses', function () {
    $pemesananStatusId = StatusPengiriman::where('slug', 'pemesanan')->value('id');
    $packingStatusId = StatusPengiriman::where('slug', 'packing')->value('id');
    $diterimaStatusId = StatusPengiriman::where('slug', 'diterima')->value('id');

    Pengiriman::factory()->create(['status_id' => $pemesananStatusId, 'qr_code_data' => null]);
    Pengiriman::factory()->create(['status_id' => $packingStatusId, 'qr_code_data' => 'existing-qr']);
    Pengiriman::factory()->create(['status_id' => $diterimaStatusId, 'qr_code_data' => null]);

    $response = $this->actingAs($this->user)->get('/admin/pengiriman?mode=generate-qr');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pengiriman/GenerateQR')
        ->where('stats.total_pengiriman', 2)
        ->where('stats.pending_qr', 1)
        ->where('stats.has_qr', 1)
        ->where('stats.status_filter', 'Semua Status Aktif')
    );
});
