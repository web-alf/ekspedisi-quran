<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin `gudang.penempatan` — memindahkan akun SPV/staf gudang ke Semarang atau Boyolali.
 *
 * Migrasi, bukan seeder: deploy produksi hanya menjalankan migrasi.
 *
 * Diberikan ke `super-admin` dan `manager-repacking` (PJ Gudang). SPV di produksi juga
 * memegang role manager-repacking, sehingga izinnya ikut terbawa — penolakan SPV
 * ditegakkan di User::bolehAturPenempatanGudang() (server) dan menu disembunyikan untuk
 * role `spv-gudang`.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => PermissionEnum::GUDANG_PENEMPATAN->value, 'guard_name' => 'web']);

        foreach ([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SUPER_ADMIN->value] as $nama) {
            Role::firstOrCreate(['name' => $nama, 'guard_name' => 'web'])
                ->givePermissionTo(PermissionEnum::GUDANG_PENEMPATAN->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SUPER_ADMIN->value] as $nama) {
            Role::where('name', $nama)->first()?->revokePermissionTo(PermissionEnum::GUDANG_PENEMPATAN->value);
        }

        Permission::where('name', PermissionEnum::GUDANG_PENEMPATAN->value)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
