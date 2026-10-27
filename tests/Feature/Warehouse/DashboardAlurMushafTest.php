<?php

use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\StokMushaf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: empat kartu "Alur Mushaf" di dashboard gudang (keputusan pemilik 27/10/2026).
 *
 * Kartu: Proses Pemesanan · Quran Ready belum ada muwakif · Proses Packing ·
 * Selesai Packing siap distribusi. Yang diuji adalah PROPS-nya (angka yang benar-benar
 * dikirim ke halaman), untuk SPV (satu gudang) dan Manager (dua gudang).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->spvSmg = User::factory()->create(['name' => 'SPV Semarang', 'is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->spvSmg->syncRoles(['warehouse', 'spv-gudang']);

    $this->manager = User::factory()->create(['name' => 'Manager Gudang', 'is_active' => true, 'gudang_id' => null]);
    $this->manager->syncRoles(['manager-repacking']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->jenis->update(['default_capacity' => 20]);
});

function alurResi(string $slug, ?Gudang $gudang, int $eks = 1): Pengiriman
{
    return Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'gudang_id' => $gudang?->id,
        'jumlah_quran' => $eks,
    ]);
}

function alurStok(Gudang $gudang, JenisQuran $jenis, int $eks): void
{
    StokMushaf::updateOrCreate(
        ['gudang_id' => $gudang->id, 'jenis_quran_id' => $jenis->id, 'terjemahan' => false],
        ['jumlah_eks' => $eks],
    );
}

it('mengirim empat angka alur untuk SPV, ter-coup ke gudangnya', function () {
    alurResi('pemesanan', null, 300);
    alurResi('pemesanan', null, 700);
    foreach ([1, 2, 3] as $_) {
        alurResi('packing', $this->smg);
    }
    foreach ([1, 2] as $_) {
        alurResi('packing', $this->btl);
    }
    foreach ([1, 2, 3, 4, 5] as $_) {
        alurResi('selesai-packing', $this->smg);
    }
    alurStok($this->smg, $this->jenis, 120);
    alurStok($this->btl, $this->jenis, 80);

    $alur = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanGudang']['alur'];

    // Pemesanan bersifat GLOBAL: resi tahap ini belum bertagih gudang.
    expect($alur['pemesanan'])->toMatchArray(['resi' => 2, 'eks' => 1000])
        ->and($alur['packing']['resi'])->toBe(3)
        ->and($alur['siap']['resi'])->toBe(5)
        ->and($alur['ready']['eks'])->toBe(120)
        // SPV hanya menerima baris gudangnya sendiri.
        ->and($alur['packing']['per_gudang'])->toHaveCount(1)
        ->and($alur['packing']['per_gudang'][0]['kode'])->toBe('SMG');
});

it('mengirim alur gabungan dua gudang untuk Manager beserta rinciannya', function () {
    alurResi('pemesanan', null, 500);
    foreach ([1, 2, 3, 4] as $_) {
        alurResi('packing', $this->smg);
    }
    foreach ([1, 2, 3, 4, 5, 6] as $_) {
        alurResi('packing', $this->btl);
    }
    foreach ([1, 2] as $_) {
        alurResi('selesai-packing', $this->smg);
    }
    foreach ([1, 2, 3, 4, 5, 6, 7] as $_) {
        alurResi('selesai-packing', $this->btl);
    }
    alurStok($this->smg, $this->jenis, 60);
    alurStok($this->btl, $this->jenis, 40);

    $alur = $this->actingAs($this->manager)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanManager']['alur'];

    expect($alur['pemesanan'])->toMatchArray(['resi' => 1, 'eks' => 500])
        ->and($alur['packing']['resi'])->toBe(10)
        ->and($alur['siap']['resi'])->toBe(9)
        ->and($alur['ready']['eks'])->toBe(100)
        ->and($alur['packing']['per_gudang'])->toHaveCount(2)
        ->and(collect($alur['packing']['per_gudang'])->pluck('packing_resi', 'kode')->all())
        ->toBe(['SMG' => 4, 'BTL' => 6]);
});

it('menghitung stok ready per gudang dari saldo stok mushaf yang sama dengan kartu stok', function () {
    alurStok($this->smg, $this->jenis, 90);

    $props = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props'];

    $dariAlur = $props['ringkasanGudang']['alur']['ready']['eks'];
    $dariKartu = collect($props['ringkasanGudang']['stok_kartu'])->sum('eks');

    expect($dariAlur)->toBe($dariKartu)->and($dariAlur)->toBe(90);
});

it('menghitung resi packing/siap yang belum bertagih gudang secara terpisah', function () {
    // Keadaan produksi: 2 resi packing + 19 selesai-packing lama tanpa gudang_id.
    alurResi('packing', null);
    alurResi('packing', null);
    alurResi('selesai-packing', null);
    alurResi('packing', $this->smg);

    $alur = $this->actingAs($this->spvSmg)->get('/admin/warehouse')
        ->assertOk()->viewData('page')['props']['ringkasanGudang']['alur'];

    expect($alur['packing']['resi'])->toBe(1)
        ->and($alur['tanpa_gudang'])->toMatchArray(['packing' => 2, 'siap' => 1, 'total' => 3]);
});

it('tidak menampilkan rincian gudang untuk peran yang bukan SPV atau Manager', function () {
    $staf = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $staf->assignRole('warehouse');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($staf)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($props['ringkasanGudang'] ?? null)->toBeNull()
        ->and($props['ringkasanManager'] ?? null)->toBeNull();
});
