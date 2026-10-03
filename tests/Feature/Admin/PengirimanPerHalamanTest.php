<?php

use App\Enums\RoleEnum;
use App\Http\Controllers\Admin\PengirimanController;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: ukuran halaman tabel pengiriman.
 *
 * Dulu ukurannya dipatok 100 baris. Seratus baris sekaligus membuat tabel berat
 * dan sulit dibaca, dan pengguna tidak punya cara memilih. Sekarang bawaannya 20
 * dan pengguna boleh memilih 10/20/50/100/200.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['pemesanan', 'produksi', 'kedatangan', 'packing', 'selesai-packing', 'pengiriman'] as $i => $slug) {
        StatusPengiriman::firstOrCreate(
            ['slug' => $slug],
            ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $i + 1, 'is_active' => true, 'is_final' => false]
        );
    }
});

function perHalamanAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user;
}

it('menawarkan pilihan ukuran halaman 10, 20, 50, 100, 200', function () {
    expect(PengirimanController::UKURAN_HALAMAN)->toBe([10, 20, 50, 100, 200]);
});

it('memakai 20 baris sebagai bawaan, bukan 100', function () {
    expect(PengirimanController::UKURAN_HALAMAN_BAWAAN)->toBe(20);

    // Buat 30 baris: dengan bawaan 20, halaman pertama berisi 20 dan tersisa 2 halaman.
    for ($i = 0; $i < 30; $i++) {
        Pengiriman::factory()->create(['no_resi' => 'EQ-2026-'.str_pad((string) (50000 + $i), 5, '0', STR_PAD_LEFT)]);
    }

    $this->actingAs(perHalamanAdmin())
        ->get('/admin/pengiriman')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('perPage', 20)
            ->where('pengiriman.per_page', 20)
            ->where('pengiriman.total', 30)
        );
});

it('menghormati pilihan ukuran halaman dari pengguna', function (int $ukuran) {
    for ($i = 0; $i < 12; $i++) {
        Pengiriman::factory()->create(['no_resi' => 'EQ-2026-'.str_pad((string) (60000 + $i), 5, '0', STR_PAD_LEFT)]);
    }

    $this->actingAs(perHalamanAdmin())
        ->get('/admin/pengiriman?per_page='.$ukuran)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('perPage', $ukuran)
            ->where('pengiriman.per_page', $ukuran)
        );
})->with([10, 20, 50, 100, 200]);

it('menolak ukuran halaman di luar daftar, kembali ke bawaan', function () {
    // Tanpa penjagaan ini, ?per_page=100000 bisa menarik seluruh 26.000 baris
    // sekaligus dan menggantungkan server.
    foreach (['999999', '0', '-5', 'abc', ''] as $asal) {
        $this->actingAs(perHalamanAdmin())
            ->get('/admin/pengiriman?per_page='.$asal)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('perPage', 20));
    }
});

it('membawa pilihan ukuran halaman ke tautan halaman berikutnya', function () {
    for ($i = 0; $i < 25; $i++) {
        Pengiriman::factory()->create(['no_resi' => 'EQ-2026-'.str_pad((string) (70000 + $i), 5, '0', STR_PAD_LEFT)]);
    }

    $this->actingAs(perHalamanAdmin())
        ->get('/admin/pengiriman?per_page=10')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pengiriman.per_page', 10)
            // Tautan halaman 2 harus memuat per_page, kalau tidak pilihan pengguna
            // hilang begitu ia menekan "Selanjutnya".
            ->where('pengiriman.next_page_url', fn ($url) => str_contains((string) $url, 'per_page=10'))
        );
});

it('tetap menyaring nomor baris dengan benar pada ukuran halaman yang lebih kecil', function () {
    // Fitur "No. Dari / No. Sampai" dulu menghitung halaman memakai patokan 100.
    // Diuji dengan 10 supaya salah hitung langsung terlihat.
    for ($i = 0; $i < 30; $i++) {
        Pengiriman::factory()->create(['no_resi' => 'EQ-2026-'.str_pad((string) (80000 + $i), 5, '0', STR_PAD_LEFT)]);
    }

    $response = $this->actingAs(perHalamanAdmin())
        ->get('/admin/pengiriman?per_page=10&number_from=5&number_to=8')
        ->assertOk();

    $props = $response->viewData('page')['props']['pengiriman'];

    // Rentangnya 4 baris, dan halaman yang dibuka harus halaman 1 (baris 5-8 ada di
    // halaman 1 kalau satu halaman berisi 10 baris).
    expect($props['data'])->toHaveCount(4)
        ->and($props['current_page'])->toBe(1)
        ->and($props['per_page'])->toBe(10);

    // Urutan bawaan no_resi desc, jadi baris 5-8 adalah EQ-...-80025 s/d 80022.
    expect(array_column($props['data'], 'no_resi'))
        ->toBe(['EQ-2026-80025', 'EQ-2026-80024', 'EQ-2026-80023', 'EQ-2026-80022']);
});
