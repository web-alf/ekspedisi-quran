<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Selaraskan izin operasional Manager Gudang (manager-repacking) dan SPV Gudang (spv-gudang)
 * di produksi dengan RolePermissionSeeder (25/10/2026).
 *
 * Kedua role ini lahir lewat seeder, dan deploy produksi hanya menjalankan migrasi. Akibatnya
 * isi izinnya di produksi tidak pernah dijamin — catatan lama di routes/web.php menyebut
 * keduanya tidak memegang `warehouse.dashboard`, padahal Dashboard Gudang (ringkasan SPV dan
 * dua gudang), Stok Mushaf, dan Proses Packing semuanya bergerbang izin itu.
 *
 * Hanya MENAMBAH yang belum ada (givePermissionTo); tidak ada izin yang dicabut, jadi izin
 * tambahan yang mungkin dipasang manual di produksi tetap utuh. Daftar di bawah adalah
 * salinan $izinOperasionalGudang di seeder pada tanggal ini — sengaja ditulis ulang supaya
 * migrasi tidak berubah makna bila seeder diubah kelak.
 */
return new class extends Migration
{
    /** @return array<int, string> */
    private function izinOperasional(): array
    {
        return array_map(fn (PermissionEnum $izin) => $izin->value, [
            PermissionEnum::DASHBOARD_VIEW,
            PermissionEnum::SHIPMENTS_READ,
            PermissionEnum::STOK_READ,
            PermissionEnum::STOK_UPDATE,
            PermissionEnum::STOK_TRANSFER,
            PermissionEnum::MUSHAF_TRANSFER,
            PermissionEnum::SHIPMENTS_TRACK,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS,
            PermissionEnum::SHIPMENTS_EXPORT,
            PermissionEnum::MUSHAF_REQUESTS_READ,
            PermissionEnum::MUSHAF_REQUESTS_PROCESS,
            PermissionEnum::CERTIFICATES_GENERATE,
            PermissionEnum::CERTIFICATES_READ,
            PermissionEnum::CERTIFICATES_DOWNLOAD,
            PermissionEnum::WAREHOUSE_DASHBOARD,
            PermissionEnum::WAREHOUSE_PACKING_VIEW,
            PermissionEnum::WAREHOUSE_PACKING_SCAN,
            PermissionEnum::WAREHOUSE_PACKING_SEAL,
            PermissionEnum::WAREHOUSE_QR_GENERATE,
            PermissionEnum::WAREHOUSE_QR_SCAN,
            PermissionEnum::WAREHOUSE_QR_VERIFY,
            PermissionEnum::WAREHOUSE_QR_BULK_GENERATE,
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW,
            PermissionEnum::WAREHOUSE_TASKS_VIEW,
            PermissionEnum::WAREHOUSE_TASKS_UPDATE,
            PermissionEnum::WAREHOUSE_BOXES_VIEW,
            PermissionEnum::WAREHOUSE_BOXES_SEAL,
            PermissionEnum::WAREHOUSE_BOX_UPDATE_SEALED,
            PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY,
            PermissionEnum::QR_GENERATE,
            PermissionEnum::QR_SCAN,
            PermissionEnum::QR_VERIFY,
            PermissionEnum::STATUS_UPDATE,
            PermissionEnum::WAKAF_BATCH_READ,
            PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR,
            PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN,
        ]);
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $izin = $this->izinOperasional();

        foreach ($izin as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        $peran = [
            RoleEnum::MANAGER_REPACKING->value => 'Manager Repacking (PJ Gudang)',
            RoleEnum::SPV_GUDANG->value => 'SPV Gudang',
        ];

        foreach ($peran as $nama => $tampilan) {
            $role = Role::firstOrCreate(['name' => $nama, 'guard_name' => 'web'], ['display_name' => $tampilan]);
            $belumAda = array_values(array_diff($izin, $role->permissions()->pluck('name')->all()));

            if ($belumAda !== []) {
                $role->givePermissionTo($belumAda);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Tidak dicabut saat rollback: migrasi ini tidak mencatat izin mana yang tadinya belum
     * ada, dan mencabut semuanya akan mematikan akses gudang yang sudah berjalan.
     */
    public function down(): void {}
};
