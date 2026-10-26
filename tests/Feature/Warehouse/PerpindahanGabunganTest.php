<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\TransferStok;
use App\Models\User;
use App\Services\StokMushafService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: dokumen transfer STOK pindah ke halaman Perpindahan Mushaf (tab "Stok tanpa
 * muwakif", 26/10/2026) dan dokumen yang menunggu diterima muncul di kartu "Perlu
 * Tindakan" dashboard.
 *
 * Sebelumnya form + daftar dokumen hanya ada di halaman Stok Mushaf, sehingga dokumen
 * yang belum diterima tidak pernah terlihat dari dashboard maupun dari halaman
 * perpindahan — barang bisa "menggantung" tanpa ada yang menagih.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->jenis->update(['default_capacity' => 20]);

    $this->buatAkun = function (string $peran, ?Gudang $gudang = null): User {
        $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $user->assignRole($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** Isi stok lewat jalur produksi (service), bukan menulis tabel langsung. */
function pgIsiStok(User $pelaku, Gudang $gudang, JenisQuran $jenis, int $eks, bool $terjemahan = false): void
{
    app(StokMushafService::class)->catatMasuk($gudang, $jenis, $eks, $terjemahan, 'Isi awal uji', $pelaku);
}

function pgDokumenTransfer(User $pembuat, Gudang $asal, Gudang $tujuan, JenisQuran $jenis, int $eks = 20): TransferStok
{
    return app(StokMushafService::class)->buatTransfer($asal, $tujuan, $jenis, $eks, false, null, $pembuat);
}

it('halaman perpindahan mengirim data tab stok yang ter-coup ke gudang SPV', function () {
    $spvSmg = ($this->buatAkun)(RoleEnum::SPV_GUDANG->value, $this->smg);

    pgIsiStok($this->admin, $this->smg, $this->jenis, 100);
    pgIsiStok($this->admin, $this->btl, $this->jenis, 60);
    $dokumen = pgDokumenTransfer($this->admin, $this->smg, $this->btl, $this->jenis, 20);

    $props = $this->actingAs($spvSmg)->get('/admin/transfer-mushaf')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['bolehTransferStok'])->toBeTrue()
        ->and($props['jenisList'])->not->toBeEmpty()
        ->and(collect($props['stokList'])->pluck('gudang_id')->unique()->all())->toBe([$this->smg->id])
        ->and(collect($props['transferStokList'])->pluck('kode')->all())->toBe([$dokumen->kode])
        ->and($props['transferStokList'][0])->toMatchArray([
            'asal' => $this->smg->nama,
            'tujuan' => $this->btl->nama,
            'terjemahan' => 'Tanpa terjemahan',
            'jumlah_eks' => 20,
            'status' => 'diproses',
        ]);
});

it('freelancer boleh melihat halaman tetapi tidak boleh membuat dokumen transfer stok', function () {
    $freelancer = ($this->buatAkun)(RoleEnum::WAREHOUSE->value, $this->smg);

    $props = $this->actingAs($freelancer)->get('/admin/transfer-mushaf')
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['bolehTransferStok'])->toBeFalse()
        ->and($props['bolehTransfer'])->toBeFalse();
});

it('dokumen transfer stok yang menunggu diterima muncul di Perlu Tindakan gudang tujuan', function () {
    $spvSmg = ($this->buatAkun)(RoleEnum::SPV_GUDANG->value, $this->smg);
    $spvBtl = ($this->buatAkun)(RoleEnum::SPV_GUDANG->value, $this->btl);

    pgIsiStok($this->admin, $this->smg, $this->jenis, 100);
    $dokumen = pgDokumenTransfer($this->admin, $this->smg, $this->btl, $this->jenis, 20);

    $perlu = $this->actingAs($spvBtl)->get('/admin/warehouse')->assertSuccessful()
        ->viewData('page')['props']['ringkasanGudang']['perlu_tindakan'];

    expect(collect($perlu['transfer_stok_menunggu'])->pluck('kode')->all())->toBe([$dokumen->kode])
        ->and($perlu['transfer_stok_menunggu'][0])->toMatchArray([
            'asal' => $this->smg->nama,
            'jumlah_eks' => 20,
        ]);

    // Gudang ASAL tidak menerima dokumennya sendiri di kartu itu.
    $perluAsal = $this->actingAs($spvSmg)->get('/admin/warehouse')->assertSuccessful()
        ->viewData('page')['props']['ringkasanGudang']['perlu_tindakan'];

    expect($perluAsal['transfer_stok_menunggu'])->toBe([]);
});

it('dashboard PJ menampilkan dokumen transfer stok yang menunggu di semua gudang', function () {
    $pj = ($this->buatAkun)(RoleEnum::MANAGER_REPACKING->value);

    pgIsiStok($this->admin, $this->smg, $this->jenis, 100);
    $dokumen = pgDokumenTransfer($this->admin, $this->smg, $this->btl, $this->jenis, 34);

    $perlu = $this->actingAs($pj)->get('/admin/warehouse')->assertSuccessful()
        ->viewData('page')['props']['ringkasanManager']['transfer_stok_menunggu'];

    expect(collect($perlu)->pluck('kode')->all())->toBe([$dokumen->kode])
        ->and($perlu[0])->toMatchArray([
            'tujuan' => $this->btl->nama,
            'jenis' => $this->jenis->nama_jenis,
            'jumlah_eks' => 34,
        ]);
});

it('dokumen yang sudah diterima tidak lagi muncul di Perlu Tindakan', function () {
    $spvBtl = ($this->buatAkun)(RoleEnum::SPV_GUDANG->value, $this->btl);

    pgIsiStok($this->admin, $this->smg, $this->jenis, 100);
    $dokumen = pgDokumenTransfer($this->admin, $this->smg, $this->btl, $this->jenis, 20);
    app(StokMushafService::class)->terimaTransfer($dokumen, $spvBtl);

    $perlu = $this->actingAs($spvBtl)->get('/admin/warehouse')->assertSuccessful()
        ->viewData('page')['props']['ringkasanGudang']['perlu_tindakan'];

    expect($perlu['transfer_stok_menunggu'])->toBe([]);
});
