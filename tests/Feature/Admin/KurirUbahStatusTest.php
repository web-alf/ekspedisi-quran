<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Kurir memindai resi untuk MENGUBAH STATUS-nya.
 *
 * Ini alur yang sama artinya dengan tab "Scan QR" di halaman Pengiriman, tapi
 * dari sisi kurir: dia mengantar barang dan mengabarkan perjalanannya.
 *
 * Yang dijaga di sini bukan sekadar "fitur bisa dipakai", melainkan BATASNYA:
 *
 *   1. Kurir hanya ditawari status perjalanan (pengiriman/batal), bukan tahap
 *      gudang. Endpoint info resi dulu mengembalikan SEMUA tahap ke depan, jadi
 *      begitu dipakai kurir, lima tahap gudang ikut bocor lagi.
 *   2. Kurir benar-benar bisa menyimpan perubahan ke status perjalanan.
 *   3. Kurir TIDAK bisa menyimpan ke tahap gudang walau memanggil langsung.
 *
 * Ketiganya berbeda: (1) soal apa yang ditawarkan, (2) soal alur normalnya,
 * (3) soal penegakan di server. Menyembunyikan pilihan bukan pengamanan.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $izin = [
        PermissionEnum::DASHBOARD_VIEW->value,
        PermissionEnum::SHIPMENTS_READ->value,
        PermissionEnum::SHIPMENTS_TRACK->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUSHAF_REQUESTS_READ->value,
        // Diperiksa ScanStatusRequest::authorize() — terpisah dari middleware
        // rute, jadi mudah terlewat padahal kurir di produksi memilikinya.
        PermissionEnum::STATUS_UPDATE->value,
    ];

    foreach ($izin as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => RoleEnum::COURIER->value], ['guard_name' => 'web']);
    Role::where('name', RoleEnum::COURIER->value)->first()->syncPermissions($izin);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Delapan status seperti produksi, urutannya nyata karena aturan maju/mundur
    // bergantung pada urutan.
    StatusPengiriman::query()->delete();

    foreach ([
        'pemesanan' => 1,
        'produksi' => 2,
        'kedatangan' => 3,
        'packing' => 4,
        'selesai-packing' => 5,
        'pengiriman' => 6,
        'diterima' => 7,
        'batal' => 99,
    ] as $slug => $urutan) {
        StatusPengiriman::create([
            'nama' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'urutan' => $urutan,
            'is_active' => true,
            'is_final' => in_array($slug, ['diterima', 'batal'], true),
        ]);
    }

    $this->kurir = User::factory()->create(['is_active' => true]);
    $this->kurir->assignRole(RoleEnum::COURIER->value);
});

function resiDenganStatus(string $slug): Pengiriman
{
    return Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
    ]);
}

// --- Apa yang DITAWARKAN ke kurir ---

it('hanya menawarkan status perjalanan ke kurir di endpoint info resi', function () {
    // Inti kebocoran yang diperbaiki: endpoint ini dipakai halaman scan, dan
    // sebelumnya mengembalikan seluruh tahap ke depan tanpa penyaringan.
    $resi = resiDenganStatus('selesai-packing');

    $this->actingAs($this->kurir)
        ->getJson(route('admin.pengiriman.status-info', $resi->no_resi))
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('validStatuses.0.slug', 'pengiriman')
        ->assertJsonPath('validStatuses.1.slug', 'batal');

    $slug = collect(
        $this->actingAs($this->kurir)
            ->getJson(route('admin.pengiriman.status-info', $resi->no_resi))
            ->json('validStatuses')
    )->pluck('slug')->all();

    expect($slug)->toBe(['pengiriman', 'batal']);
});

it('tidak menawarkan tahap gudang dari status awal', function () {
    // Dari "pemesanan" ada banyak tahap ke depan. Kurir tidak boleh ditawari
    // satu pun tahap gudang itu.
    $resi = resiDenganStatus('pemesanan');

    $slug = collect(
        $this->actingAs($this->kurir)
            ->getJson(route('admin.pengiriman.status-info', $resi->no_resi))
            ->assertSuccessful()
            ->json('validStatuses')
    )->pluck('slug')->all();

    expect($slug)->not->toContain('produksi')
        ->and($slug)->not->toContain('packing')
        ->and($slug)->not->toContain('kedatangan')
        ->and($slug)->not->toContain('selesai-packing')
        ->and($slug)->not->toContain('diterima');
});

it('tetap menawarkan tahap lanjutan penuh ke role tanpa batas', function () {
    // Penyaringan harus spesifik ke kurir, bukan memotong semua orang.
    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => RoleEnum::SUPER_ADMIN->value], ['guard_name' => 'web']));
    $superAdmin->givePermissionTo([
        PermissionEnum::SHIPMENTS_TRACK->value,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
    ]);

    $resi = resiDenganStatus('pemesanan');

    $slug = collect(
        $this->actingAs($superAdmin)
            ->getJson(route('admin.pengiriman.status-info', $resi->no_resi))
            ->assertSuccessful()
            ->json('validStatuses')
    )->pluck('slug')->all();

    expect($slug)->toContain('produksi', 'packing', 'pengiriman', 'diterima');
});

// --- Alur normalnya ---

it('kurir bisa mengubah status resi ke perjalanan lewat endpoint scan', function () {
    $resi = resiDenganStatus('selesai-packing');

    $this->actingAs($this->kurir)
        ->postJson(route('admin.pengiriman.scan-status.update'), [
            'no_resi' => $resi->no_resi,
            'status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
            'catatan' => 'dibawa kurir',
            'lokasi' => 'Jakarta Selatan',
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('kurir bisa membatalkan resi yang gagal antar', function () {
    // "Batal" harus selalu tersedia: kurir yang menemukan alamat tidak ada
    // harus bisa mengabarkannya.
    $resi = resiDenganStatus('pengiriman');

    $this->actingAs($this->kurir)
        ->postJson(route('admin.pengiriman.scan-status.update'), [
            'no_resi' => $resi->no_resi,
            'status_id' => StatusPengiriman::where('slug', 'batal')->value('id'),
            'catatan' => 'alamat tidak ditemukan',
        ])
        ->assertSuccessful();

    expect($resi->fresh()->status->slug)->toBe('batal');
});

// --- Penegakan di server ---

it('MENOLAK kurir memindahkan resi ke tahap gudang walau memanggil endpoint langsung', function () {
    $resi = resiDenganStatus('pemesanan');

    $this->actingAs($this->kurir)
        ->postJson(route('admin.pengiriman.scan-status.update'), [
            'no_resi' => $resi->no_resi,
            'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
        ])
        ->assertForbidden();

    expect($resi->fresh()->status->slug)->toBe('pemesanan');
});

it('kurir tidak bisa menandai resi diterima penerima', function () {
    // "Diterima" diselesaikan role distribusi/manager dengan verifikasi manual,
    // bukan oleh kurir di jalan.
    $resi = resiDenganStatus('pengiriman');

    $this->actingAs($this->kurir)
        ->postJson(route('admin.pengiriman.scan-status.update'), [
            'no_resi' => $resi->no_resi,
            'status_id' => StatusPengiriman::where('slug', 'diterima')->value('id'),
        ])
        ->assertForbidden();

    expect($resi->fresh()->status->slug)->toBe('pengiriman');
});

it('mencatat riwayat status saat kurir mengubahnya', function () {
    // Perubahan status tanpa riwayat membuat perjalanan resi tidak bisa ditelusuri.
    $resi = resiDenganStatus('selesai-packing');

    $this->actingAs($this->kurir)
        ->postJson(route('admin.pengiriman.scan-status.update'), [
            'no_resi' => $resi->no_resi,
            'status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
            'catatan' => 'dibawa kurir',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('status_histories', [
        'pengiriman_id' => $resi->id,
        'status_to' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
    ]);
});
