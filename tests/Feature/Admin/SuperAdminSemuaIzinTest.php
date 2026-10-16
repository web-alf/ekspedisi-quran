<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * super-admin harus bisa MELIHAT dan MEMBUKA semua halaman.
 *
 * Produksi 12 Okt 2026: super-admin bisa membuka semua URL (Gate::before dan cabang
 * hasRole di PermissionMiddleware meloloskannya), tetapi menunya tidak muncul.
 * AdminLayout.svelte menyaring menu dengan `permissions.includes(...)` dan daftar
 * izin itu berasal dari isi tabel — di sana peran super-admin hanya memegang 90 dari
 * 95 izin, bolong tepat di lima izin `muatan.*` karena migrasi 2026_10_04 hanya
 * memberinya `muatan.complete`.
 *
 * Uji ini menjaga dua hal: datanya lengkap (migrasi 2026_10_12) DAN daftar izin yang
 * dikirim ke browser tidak bergantung pada kelengkapan data itu
 * (HandleInertiaRequests). Uji kedua tetap lulus walau perannya sengaja dibolongkan
 * — itulah perlindungan terhadap izin baru yang belum sempat diberikan lewat migrasi.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function superAdminSemuaIzin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user->fresh();
}

it('memberi super-admin seluruh izin yang ada di basis data', function () {
    $peran = Role::findByName(RoleEnum::SUPER_ADMIN->value);

    expect($peran->permissions->pluck('name')->sort()->values()->all())
        ->toBe(Permission::pluck('name')->sort()->values()->all());
});

it('memberi super-admin setiap izin yang dipakai kode', function () {
    $dimiliki = Role::findByName(RoleEnum::SUPER_ADMIN->value)
        ->permissions->pluck('name')->all();

    $kurang = array_values(array_diff(
        array_map(fn (PermissionEnum $izin): string => $izin->value, PermissionEnum::cases()),
        $dimiliki
    ));

    expect($kurang)->toBe([]);
});

it('tetap mengirim seluruh izin ke browser walau peran super-admin bolong', function () {
    // Meniru persis keadaan produksi: peran super-admin tidak memegang muatan.read
    // dan muatan.scan, padahal keduanya yang membuka menu "Muatan & Distribusi" dan
    // "Tugas Kurir" di sidebar.
    Role::findByName(RoleEnum::SUPER_ADMIN->value)->revokePermissionTo([
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_SCAN->value,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $response = $this->actingAs(superAdminSemuaIzin())->get('/admin/dashboard');
    $response->assertOk();

    $permissions = $response->viewData('page')['props']['auth']['user']['permissions'];

    expect($permissions)->toContain(PermissionEnum::MUATAN_READ->value)
        ->and($permissions)->toContain(PermissionEnum::MUATAN_SCAN->value);
});

it('tetap mengirim seluruh izin ke browser untuk halaman muatan', function () {
    Role::findByName(RoleEnum::SUPER_ADMIN->value)->revokePermissionTo([
        PermissionEnum::MUATAN_READ->value,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $response = $this->actingAs(superAdminSemuaIzin())->get('/admin/muatan');
    $response->assertOk();

    $permissions = $response->viewData('page')['props']['auth']['user']['permissions'];

    expect($permissions)->toContain(PermissionEnum::MUATAN_READ->value);
});

it('tidak memberi daftar izin berlebih itu kepada peran lain', function () {
    $gudang = User::factory()->create(['is_active' => true]);
    $gudang->assignRole(RoleEnum::WAREHOUSE->value);

    $response = $this->actingAs($gudang->fresh())->get('/admin/warehouse');
    $response->assertOk();

    $permissions = $response->viewData('page')['props']['auth']['user']['permissions'];

    expect($permissions)->not->toContain(PermissionEnum::MUATAN_DELETE->value)
        ->and($permissions)->not->toContain(PermissionEnum::MUATAN_COMPLETE->value)
        ->and($permissions)->toBe($gudang->fresh()->getAllPermissions()->pluck('name')->all());
});
