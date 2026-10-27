<?php

use App\Enums\RoleEnum;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\PengirimanStageVisibility;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: status "Proses Produksi" (`produksi`) DIHILANGKAN dari aplikasi.
 *
 * Keputusan pemilik 27/10/2026. Baris di `status_pengiriman` tidak dihapus karena riwayat
 * lama masih merujuk id-nya, jadi yang diuji di sini adalah: status itu tidak aktif, tidak
 * ditawarkan di pilihan/filter mana pun, tidak dihitung di dashboard, dan tidak muncul di
 * tahap beban gudang — sementara riwayat lama tetap bisa dibaca.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('menonaktifkan status produksi tanpa menghapus barisnya', function () {
    $status = StatusPengiriman::where('slug', 'produksi')->first();

    expect($status)->not->toBeNull()
        ->and($status->is_active)->toBeFalse()
        ->and($status->nama)->toBe('Proses Produksi');
});

it('tidak menawarkan produksi di daftar status aktif', function () {
    $slug = StatusPengiriman::active()->ordered()->pluck('slug')->all();

    expect($slug)->not->toContain('produksi')
        ->and($slug)->toContain('pemesanan')
        ->and($slug)->toContain('packing');
});

it('tidak memuat produksi di tahap riwayat publik', function () {
    expect(config('pengiriman.riwayat_publik_tahap'))->not->toContain('produksi');
});

it('tidak menawarkan produksi ke kurir maupun manager saat memindahkan status', function () {
    $kurir = User::factory()->create(['is_active' => true]);
    $kurir->assignRole(RoleEnum::COURIER->value);

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([$kurir, $manager] as $pelaku) {
        $slug = PengirimanStageVisibility::visibleProgressStatuses($pelaku)->pluck('slug')->all();
        expect($slug)->not->toContain('produksi');
    }
});

it('tidak menghitung resi berstatus produksi sebagai beban maupun pending', function () {
    $produksi = StatusPengiriman::where('slug', 'produksi')->firstOrFail();
    $pemesanan = StatusPengiriman::where('slug', 'pemesanan')->firstOrFail();

    $resiProduksi = Pengiriman::factory()->create(['status_id' => $produksi->id]);
    $resiPemesanan = Pengiriman::factory()->create(['status_id' => $pemesanan->id]);

    $pending = Pengiriman::whereHas('status', fn ($q) => $q->where('slug', 'pemesanan'))->count();

    expect($pending)->toBe(1)
        ->and($resiProduksi->status->slug)->toBe('produksi')
        ->and($resiPemesanan->status->slug)->toBe('pemesanan');
});

it('tetap membaca riwayat lama yang menyebut produksi', function () {
    // 52 baris status_histories + 25 tracking_history di produksi merujuk status ini;
    // menonaktifkannya tidak boleh membuat labelnya hilang dari riwayat.
    $status = StatusPengiriman::where('slug', 'produksi')->firstOrFail();

    expect($status->dashboard_label)->toBe('Pending/Diproses')
        ->and($status->category)->toBe('pending')
        ->and($status->icon)->not->toBeEmpty();
});

it('tidak lagi membuat status produksi maupun kedatangan pada pemasangan baru', function () {
    $slug = collect(StatusPengiriman::getDefaultStatuses())->pluck('slug')->all();

    expect($slug)->not->toContain('produksi')
        ->and($slug)->not->toContain('kedatangan')
        ->and($slug)->toContain('pemesanan')
        ->and($slug)->toContain('antrian')
        ->and($slug)->toContain('selesai-packing');
});

/*
|--------------------------------------------------------------------------
| "Proses Kedatangan/Penurunan" — ditutup rapat 27/10/2026
|--------------------------------------------------------------------------
| Sudah dihapus dari alur gudang sejak 13/10/2026, tetapi masih tersisa di tahap
| dashboard dan daftar status halaman QR. Sisa itu yang dibuang sekarang.
*/

it('menutup status kedatangan tanpa menghapus barisnya', function () {
    $status = StatusPengiriman::where('slug', 'kedatangan')->first();

    // Seeder tidak lagi membuatnya; kalau barisnya tidak ada di DB uji, tidak ada yang
    // perlu ditutup — yang penting ia tidak pernah muncul di daftar mana pun.
    if ($status === null) {
        expect(StatusPengiriman::active()->pluck('slug'))->not->toContain('kedatangan');

        return;
    }

    expect($status->is_active)->toBeFalse();
});

it('tidak memuat kedatangan di tahap riwayat publik maupun pilihan status', function () {
    expect(config('pengiriman.riwayat_publik_tahap'))->not->toContain('kedatangan');

    $gudang = User::factory()->create(['is_active' => true]);
    $gudang->assignRole(RoleEnum::WAREHOUSE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(PengirimanStageVisibility::visibleStatuses($gudang)->pluck('slug')->all())
        ->not->toContain('kedatangan');
});
