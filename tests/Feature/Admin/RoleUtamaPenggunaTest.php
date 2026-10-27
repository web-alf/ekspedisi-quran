<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: akun yang memegang LEBIH DARI SATU role harus tampil dengan role yang
 * mewakili wewenangnya, bukan role pertama di tabel pivot.
 *
 * Kejadian 26/10/2026: label `warehouse` berganti menjadi "Freelancer Gudang", dan akun
 * gudang@ (PJ gudang) yang sejak dulu juga memegang role `warehouse` langsung tampil
 * sebagai freelancer di daftar pengguna — padahal wewenangnya PJ.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('memilih role terluas sebagai role utama, bukan urutan pivot', function () {
    $akun = User::factory()->create(['is_active' => true]);
    // Urutan pemasangan sengaja warehouse dulu — ini urutan yang dulu menentukan tampilan.
    $akun->assignRole([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]);

    expect($akun->fresh()->roleUtama()?->name)->toBe(RoleEnum::MANAGER_REPACKING->value)
        ->and($akun->fresh()->role_display)->toBe('Manager Repacking (PJ Gudang)')
        // `$user->role` (dipakai props auth & halaman "role saat ini") ikut role utama.
        ->and($akun->fresh()->role)->toBe(RoleEnum::MANAGER_REPACKING->value);
});

it('daftar pengguna menampilkan role utama akun multi-role', function () {
    $akun = User::factory()->create(['is_active' => true, 'name' => 'PJ Gudang', 'email' => 'gudang@contoh.test']);
    $akun->assignRole([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]);

    $props = $this->actingAs($this->admin)->get('/admin/users?search=gudang@contoh.test')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $baris = collect($props['users']['data'])->firstWhere('email', 'gudang@contoh.test');

    expect($baris['role_display'])->toBe('Manager Repacking (PJ Gudang)')
        ->and($baris['spatie_role'])->toBe(RoleEnum::MANAGER_REPACKING->value);
});

it('akun SPV yang juga memegang manager-repacking tampil sebagai SPV Gudang', function () {
    $akun = User::factory()->create(['is_active' => true]);
    $akun->assignRole([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value]);

    expect($akun->fresh()->role_display)->toBe('SPV Gudang');
});

it('akun ber-role tunggal tetap tampil apa adanya', function (string $peran, string $label) {
    $akun = User::factory()->create(['is_active' => true]);
    $akun->assignRole($peran);

    expect($akun->fresh()->role_display)->toBe($label);
})->with([
    'freelancer' => [RoleEnum::WAREHOUSE->value, 'Freelancer Gudang'],
    'spv' => [RoleEnum::SPV_GUDANG->value, 'SPV Gudang'],
    'kurir' => [RoleEnum::COURIER->value, 'Kurir'],
]);

it('akun tanpa role tidak membuat galat', function () {
    $akun = User::factory()->create(['is_active' => true]);

    expect($akun->fresh()->roleUtama())->toBeNull()
        ->and($akun->fresh()->role_display)->toBe('No Role');
});
