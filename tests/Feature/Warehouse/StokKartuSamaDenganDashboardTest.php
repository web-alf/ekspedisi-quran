<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\User;
use App\Services\RingkasanGudangService;
use App\Services\StokMushafService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: kartu "Isi Stok" di halaman Stok Mushaf memakai sumber data & komponen yang
 * SAMA dengan dashboard (27/10/2026).
 *
 * Sebelumnya halaman ini menyusun kartunya sendiri, sehingga bentuknya beda dengan kartu
 * stok yang sudah ada di dashboard. Sekarang props-nya berasal dari
 * RingkasanGudangService::stokKartu() — bentuk baris yang dibaca komponen KartuStok.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->jenis->update(['default_capacity' => 20]);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->spvSmg = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->spvSmg->syncRoles([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function skIsiStok(User $pelaku, Gudang $gudang, JenisQuran $jenis, int $eks, bool $terjemahan = false): void
{
    app(StokMushafService::class)->catatMasuk($gudang, $jenis, $eks, $terjemahan, 'Isi awal uji', $pelaku);
}

it('mengirim kartu stok dengan bentuk baris yang sama seperti dashboard', function () {
    skIsiStok($this->admin, $this->smg, $this->jenis, 90);

    $props = $this->actingAs($this->spvSmg)->get('/admin/warehouse/stok')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $grup = collect($props['stokKartu'])->firstWhere('gudang_id', $this->smg->id);

    expect($grup)->not->toBeNull()
        ->and($grup['gudang'])->toBe($this->smg->nama)
        ->and($grup['baris'][0])->toHaveKeys(['kode_jenis', 'nama_jenis', 'varian', 'eks', 'dus', 'sisa_eks', 'status'])
        ->and($grup['baris'][0])->toMatchArray([
            'kode_jenis' => 'A5',
            'eks' => 90,
            'dus' => 4,
            'sisa_eks' => 10,
            'status' => 'normal',
        ]);
});

it('bentuknya identik dengan stok_kartu milik dashboard', function () {
    skIsiStok($this->admin, $this->smg, $this->jenis, 90);
    skIsiStok($this->admin, $this->smg, $this->jenis, 15, true);

    $props = $this->actingAs($this->spvSmg)->get('/admin/warehouse/stok')
        ->assertSuccessful()->viewData('page')['props'];
    $dariHalaman = collect($props['stokKartu'])->firstWhere('gudang_id', $this->smg->id)['baris'];

    $dariDashboard = app(RingkasanGudangService::class)->stokKartu($this->smg->id);

    expect($dariHalaman)->toEqual($dariDashboard);
});

it('hanya mengirim kartu gudang SPV, bukan kedua gudang', function () {
    skIsiStok($this->admin, $this->smg, $this->jenis, 40);
    skIsiStok($this->admin, $this->btl, $this->jenis, 25);

    $props = $this->actingAs($this->spvSmg)->get('/admin/warehouse/stok')
        ->assertSuccessful()->viewData('page')['props'];

    expect(collect($props['stokKartu'])->pluck('gudang_id')->all())->toBe([$this->smg->id]);
});

it('tidak mengirim grup kosong bila gudang belum punya stok', function () {
    skIsiStok($this->admin, $this->smg, $this->jenis, 40);

    $props = $this->actingAs($this->admin)->get('/admin/warehouse/stok')
        ->assertSuccessful()->viewData('page')['props'];

    expect(collect($props['stokKartu'])->pluck('gudang_id')->all())->toBe([$this->smg->id]);
});
