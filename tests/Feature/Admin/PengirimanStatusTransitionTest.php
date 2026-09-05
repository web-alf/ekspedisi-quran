<?php

use App\Enums\PermissionEnum;
use App\Models\Pengiriman;
use App\Models\StatusHistory;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Test tidak menjalankan seeder — buat role & permission manual (pola DonaturManagementTest)
    $shipmentPermissions = [
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_UPDATE->value,
        PermissionEnum::SHIPMENTS_BULK_UPDATE->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
    ];

    foreach ($shipmentPermissions as $permissionName) {
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
    }

    $role = Role::firstOrCreate(['name' => 'test-shipping', 'guard_name' => 'web']);
    $role->syncPermissions($shipmentPermissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('test-shipping');

    // Status berurutan — beberapa slug (pemesanan, produksi, dst) mungkin sudah
    // di-insert oleh data migrasi, jadi pakai firstOrCreate agar tidak bentrok unique.
    $this->statusAwal = StatusPengiriman::firstOrCreate(
        ['slug' => 'pemesanan'],
        ['nama' => 'Pemesanan', 'slug' => 'pemesanan', 'urutan' => 1, 'is_final' => false]
    );
    $this->statusLanjut = StatusPengiriman::firstOrCreate(
        ['slug' => 'produksi'],
        ['nama' => 'Produksi', 'slug' => 'produksi', 'urutan' => 2, 'is_final' => false]
    );
    $this->statusAkhir = StatusPengiriman::firstOrCreate(
        ['slug' => 'diterima'],
        ['nama' => 'Diterima', 'slug' => 'diterima', 'urutan' => 7, 'is_final' => true]
    );
    $this->statusBatal = StatusPengiriman::firstOrCreate(
        ['slug' => 'batal'],
        ['nama' => 'Batal', 'slug' => 'batal', 'urutan' => 99, 'is_final' => true]
    );
});

it('menolak transisi dari status final via endpoint update biasa', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusAkhir->id]);

    $this->actingAs($this->user)
        ->put("/admin/pengiriman/{$pengiriman->id}", [
            'status_id' => $this->statusAwal->id,
            'alamat_tujuan' => 'Jl. Test',
        ])
        ->assertSessionHasErrors('error');

    expect($pengiriman->fresh()->status_id)->toBe($this->statusAkhir->id);
    expect(StatusHistory::where('pengiriman_id', $pengiriman->id)->count())->toBe(0);
});

it('menolak transisi mundur via endpoint update biasa', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusLanjut->id]);

    $this->actingAs($this->user)
        ->put("/admin/pengiriman/{$pengiriman->id}", [
            'status_id' => $this->statusAwal->id,
            'alamat_tujuan' => 'Jl. Test',
        ])
        ->assertSessionHasErrors('error');

    expect($pengiriman->fresh()->status_id)->toBe($this->statusLanjut->id);
});

it('mengizinkan transisi maju yang valid via endpoint update biasa', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusAwal->id]);

    $this->actingAs($this->user)
        ->put("/admin/pengiriman/{$pengiriman->id}", [
            'status_id' => $this->statusLanjut->id,
            'alamat_tujuan' => 'Jl. Baru',
        ])
        ->assertSessionHasNoErrors();

    expect($pengiriman->fresh()->status_id)->toBe($this->statusLanjut->id);
    expect(StatusHistory::where('pengiriman_id', $pengiriman->id)->count())->toBe(1);
});

it('mengizinkan update alamat tanpa mengubah status', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusAwal->id]);

    $this->actingAs($this->user)
        ->put("/admin/pengiriman/{$pengiriman->id}", [
            'status_id' => $this->statusAwal->id, // status sama
            'alamat_tujuan' => 'Alamat Baru',
            'nama_penerima' => 'Penerima Baru',
        ])
        ->assertSessionHasNoErrors();

    expect($pengiriman->fresh()->alamat_tujuan)->toBe('Alamat Baru');
    expect($pengiriman->fresh()->status_id)->toBe($this->statusAwal->id);
    // Tidak boleh ada history status karena status tidak berubah
    expect(StatusHistory::where('pengiriman_id', $pengiriman->id)->count())->toBe(0);
});

it('bulk update melewati pengiriman dengan transisi tidak valid', function () {
    $p1 = Pengiriman::factory()->create(['status_id' => $this->statusAwal->id]);   // valid: maju ke produksi
    $p2 = Pengiriman::factory()->create(['status_id' => $this->statusAkhir->id]);  // invalid: final -> produksi

    $response = $this->actingAs($this->user)
        ->postJson('/admin/pengiriman/bulk-status', [
            'pengiriman_ids' => [$p1->id, $p2->id],
            'status_id' => $this->statusLanjut->id,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.updated_count', 1)
        ->assertJsonPath('data.skipped_count', 1);

    expect($p1->fresh()->status_id)->toBe($this->statusLanjut->id);
    expect($p2->fresh()->status_id)->toBe($this->statusAkhir->id); // tidak berubah
});

it('bulk update menolak status final (tidak bisa mundur dari final)', function () {
    $p = Pengiriman::factory()->create(['status_id' => $this->statusAkhir->id]);

    $this->actingAs($this->user)
        ->postJson('/admin/pengiriman/bulk-status', [
            'pengiriman_ids' => [$p->id],
            'status_id' => $this->statusAwal->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.updated_count', 0);

    expect($p->fresh()->status_id)->toBe($this->statusAkhir->id);
});

it('model updateStatus dibungkus transaksi dan mengubah status + history', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusAwal->id]);

    $pengiriman->updateStatus($this->statusLanjut->id, 'Catatan test', $this->user->id);

    expect($pengiriman->fresh()->status_id)->toBe($this->statusLanjut->id);
    expect(StatusHistory::where('pengiriman_id', $pengiriman->id)->first())
        ->not->toBeNull()
        ->status_from->toBe($this->statusAwal->id)
        ->status_to->toBe($this->statusLanjut->id);
});

it('model updateStatus tidak membuat history ganda saat status sama', function () {
    $pengiriman = Pengiriman::factory()->create(['status_id' => $this->statusAwal->id]);

    $pengiriman->updateStatus($this->statusAwal->id, 'Sama', $this->user->id);

    expect(StatusHistory::where('pengiriman_id', $pengiriman->id)->count())->toBe(0);
});
