<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Migrasi 2026_10_25_165257: produksi tidak pernah menjalankan seeder, jadi izin operasional
 * Manager Gudang dan SPV Gudang harus dipasang lewat migrasi — tanpa mencabut apa pun.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
});

function jalankanMigrasiIzinGudang(): void
{
    $migrasi = require database_path('migrations/2026_10_25_165257_selaraskan_izin_operasional_manager_dan_spv_gudang.php');
    $migrasi->up();
}

it('memasang izin operasional yang hilang tanpa mencabut izin tambahan', function (string $peran) {
    $role = Role::findByName($peran);
    $role->revokePermissionTo([PermissionEnum::WAREHOUSE_DASHBOARD->value, PermissionEnum::STOK_UPDATE->value]);
    Permission::firstOrCreate(['name' => 'izin.tambahan-produksi', 'guard_name' => 'web']);
    $role->givePermissionTo('izin.tambahan-produksi');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    jalankanMigrasiIzinGudang();

    $role = Role::findByName($peran);
    expect($role->hasPermissionTo(PermissionEnum::WAREHOUSE_DASHBOARD->value))->toBeTrue()
        ->and($role->hasPermissionTo(PermissionEnum::STOK_UPDATE->value))->toBeTrue()
        ->and($role->hasPermissionTo('izin.tambahan-produksi'))->toBeTrue();
})->with([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value]);

it('menghasilkan izin yang sama dengan seeder dan aman dijalankan ulang', function () {
    $sebelum = Role::findByName(RoleEnum::SPV_GUDANG->value)->permissions->pluck('name')->sort()->values()->all();

    jalankanMigrasiIzinGudang();
    jalankanMigrasiIzinGudang();

    expect(Role::findByName(RoleEnum::SPV_GUDANG->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe($sebelum);
});
