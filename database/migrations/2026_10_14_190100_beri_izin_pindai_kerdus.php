<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin `distribusi.pindai-kerdus` + pemberiannya ke peran pemakainya.
 *
 * Kenapa migrasi, bukan seeder: jalur deploy produksi menjalankan MIGRASI saja, jadi
 * izin yang hanya ada di RolePermissionSeeder tidak akan pernah sampai ke server — dan
 * menu "Pindai Kerdus" tidak akan muncul untuk siapa pun di sana.
 *
 * Perannya dipastikan ADA (firstOrCreate), bukan sekadar diambil: di basis data yang
 * belum pernah di-seed pun migrasi ini harus utuh berjalan, dan role `distribusi`
 * memang baru lahir dari migrasi, bukan dari seeder.
 *
 * SENGAJA TIDAK diberikan kepada kurir, gudang, supervisor, dan customer-service:
 *   - Kurir memuat barang ke perjalanan lewat muatan (muatan.scan).
 *   - Gudang memakai halaman packing & kerdus.
 *   - Yang bertanggung jawab atas kerdus keluar-masuk gudang adalah sisi distribusi.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate([
            'name' => PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value,
            'guard_name' => 'web',
        ]);

        foreach ([
            RoleEnum::MANAGER->value => 'Manager Distribusi',
            RoleEnum::DISTRIBUSI->value => 'Distribusi',
            RoleEnum::SUPER_ADMIN->value => 'Super Admin',
        ] as $nama => $tampilan) {
            $role = Role::firstOrCreate(['name' => $nama], ['display_name' => $tampilan, 'guard_name' => 'web']);
            $role->givePermissionTo(PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([RoleEnum::MANAGER, RoleEnum::DISTRIBUSI, RoleEnum::SUPER_ADMIN] as $roleEnum) {
            $role = Role::where('name', $roleEnum->value)->first();

            if ($role && Permission::where('name', PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value)->where('guard_name', 'web')->exists()) {
                $role->revokePermissionTo(PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value);
            }
        }

        Permission::where('name', PermissionEnum::DISTRIBUSI_PINDAI_KERDUS->value)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
