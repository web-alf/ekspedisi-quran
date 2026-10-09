<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tahap awal distribusi (pemesanan, produksi, kedatangan/penurunan, packing)
 * dijadikan ranah staff gudang.
 *
 * Yang ditambahkan: izin `shipments.update-status` supaya gudang bisa memanggil
 * endpoint ubah status (halaman Ubah Status dan batch), dan `shipments.track`
 * supaya halaman scan QR bisa mencari resinya — tanpa izin itu, halaman scan
 * gagal di langkah "ambil info resi" dan tampak seolah tombolnya rusak.
 *
 * Batas tahapnya TIDAK diatur di sini. Izin hanya mengatakan "boleh mengubah
 * status"; status APA yang boleh dituju ditegakkan oleh
 * PengirimanStageVisibility::bolehPilihStatus() di semua jalur (ubah status,
 * batch, bulk, form edit, scan). Tanpa batas itu, izin ini akan membuka
 * pengiriman/diterima lewat endpoint yang tidak dijaga.
 *
 * Kenapa lewat migrasi, bukan seeder: `RolePermissionSeeder` tidak dijalankan
 * saat deploy, dan menjalankannya justru menulis ulang SELURUH izin tiap role
 * (syncPermissions) — termasuk menghapus izin yang sengaja berbeda di produksi.
 * Migrasi ini hanya MENAMBAH, dan itu aman diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Izin harus ADA lebih dulu. Seeder tidak berjalan saat deploy, dan di
        // lingkungan uji (RefreshDatabase) yang berjalan hanya migrasi — tanpa
        // firstOrCreate ini, migrasi gagal dengan PermissionDoesNotExist.
        foreach ([
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
        ] as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        $gudang = Role::firstOrCreate(
            ['name' => RoleEnum::WAREHOUSE->value],
            ['display_name' => 'Staff Gudang', 'guard_name' => 'web']
        );

        // givePermissionTo bersifat idempoten pada Spatie (izin yang sudah
        // dimiliki tidak diduplikasi), jadi tidak perlu memeriksa satu per satu.
        $gudang->givePermissionTo([
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Izin yang diberikan di atas SENGAJA tidak dicabut saat rollback.
     *
     * `shipments.update-status` dipegang banyak role lain (kurir, manager,
     * super-admin, distribusi). Mencabutnya dari gudang bisa dilakukan dengan
     * aman, tetapi `shipments.track` tidak jelas siapa lagi yang memakainya —
     * dan mencabut saat rollback justru bisa mengunci pekerjaan yang sudah
     * berjalan. Rollback skema izin lebih aman dilakukan dengan sengaja.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
