<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cabut mushaf-requests.read & mushaf-requests.process dari role warehouse (Freelancer Gudang).
 *
 * Menu Permintaan Mushaf tidak ada di sidebar gudang, tapi lewat URL langsung:
 *   - mushaf-requests.read    membuka daftar & detail Permintaan Mushaf dan api/donatur-list
 *                             — nama semua donatur serta alamat, nama pengurus, dan WhatsApp
 *                             lembaga;
 *   - mushaf-requests.process membuat resi dari permintaan yang disetujui (POST
 *                             mushaf-requests/{id}/process).
 * Tidak ada alur packing yang membutuhkan keduanya.
 *
 * SPV Gudang dan Manager Repacking tetap memegangnya lewat role mereka sendiri.
 *
 * Dijalankan sebagai migrasi, bukan lewat seeder: deploy hanya menjalankan migrasi.
 */
return new class extends Migration
{
    /**
     * @return array<int, string>
     */
    private function izinPermintaanMushaf(): array
    {
        return [
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::MUSHAF_REQUESTS_PROCESS->value,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

        if ($freelancer) {
            // Hanya mencabut yang memang dipegang — izin yang belum terdaftar di DB
            // tidak memicu exception.
            $dipegang = $freelancer->permissions()->whereIn('name', $this->izinPermintaanMushaf())->get();

            if ($dipegang->isNotEmpty()) {
                $freelancer->revokePermissionTo($dipegang);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $freelancer = Role::where('name', RoleEnum::WAREHOUSE->value)->first();

        if ($freelancer) {
            foreach ($this->izinPermintaanMushaf() as $izin) {
                Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
            }

            $freelancer->givePermissionTo($this->izinPermintaanMushaf());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
