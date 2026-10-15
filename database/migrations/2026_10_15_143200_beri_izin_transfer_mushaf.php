<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin `mushaf.transfer` — memindahkan mushaf ber-resi antar gudang.
 *
 * Kenapa migrasi, bukan seeder: jalur deploy produksi hanya menjalankan MIGRASI, jadi izin
 * yang hanya ada di RolePermissionSeeder tidak akan pernah sampai ke server.
 *
 * Pemberiannya SENGAJA ke sisi gudang saja:
 *   - `manager-repacking` (PJ Gudang) & `spv-gudang` (SPV per lokasi) — merekalah yang
 *     memegang serah terima fisik antar gudang; SPV hanya melihat dokumen gudangnya
 *     sendiri (penyaringan di pengendali).
 *   - `super-admin` — pemilik sistem.
 *   - Role `manager` (Manager Distribusi) TIDAK diberi: pekerjaannya memuat & mengirim,
 *     bukan memindahkan stok antar gudang; ia tetap melihat kolom Gudang lewat
 *     `shipments.read`.
 *   - `warehouse` (freelancer) TIDAK diberi: pemindahan bukan keputusan pekerja harian.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate([
            'name' => PermissionEnum::MUSHAF_TRANSFER->value,
            'guard_name' => 'web',
        ]);

        foreach ([
            RoleEnum::MANAGER_REPACKING->value,
            RoleEnum::SPV_GUDANG->value,
            RoleEnum::SUPER_ADMIN->value,
        ] as $nama) {
            Role::firstOrCreate(['name' => $nama, 'guard_name' => 'web'])
                ->givePermissionTo(PermissionEnum::MUSHAF_TRANSFER->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([RoleEnum::MANAGER_REPACKING, RoleEnum::SPV_GUDANG, RoleEnum::SUPER_ADMIN] as $roleEnum) {
            $role = Role::where('name', $roleEnum->value)->first();

            if ($role && Permission::where('name', PermissionEnum::MUSHAF_TRANSFER->value)->where('guard_name', 'web')->exists()) {
                $role->revokePermissionTo(PermissionEnum::MUSHAF_TRANSFER->value);
            }
        }

        Permission::where('name', PermissionEnum::MUSHAF_TRANSFER->value)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
