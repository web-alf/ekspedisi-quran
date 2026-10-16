<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cabut qr.generate & qr.scan dari Manager Distribusi.
 *
 * Kedua izin itu yang memunculkan tab "Generate QR" dan "Scan QR" di halaman Data
 * Resi, padahal keduanya bukan pekerjaan manager: QR dibuat gudang, dan manager
 * memindai kerdus/resi lewat halaman Muatan.
 *
 * Yang TETAP dimiliki manager:
 *   - qr.verify      memeriksa QR resi
 *   - shipments.read melihat & mengunduh gambar QR resi di Daftar Resi (rute
 *                    qr/display & qr/download kini menerima izin ini)
 *
 * Dijalankan sebagai migrasi, bukan lewat seeder: deploy hanya menjalankan migrasi.
 */
return new class extends Migration
{
    /**
     * @return array<int, string>
     */
    private function izinQr(): array
    {
        return [
            PermissionEnum::QR_GENERATE->value,
            PermissionEnum::QR_SCAN->value,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            // Hanya mencabut yang memang dipegang — izin manager lain dibiarkan utuh,
            // dan izin yang belum terdaftar di DB tidak memicu exception.
            $dipegang = $manager->permissions()->whereIn('name', $this->izinQr())->get();

            if ($dipegang->isNotEmpty()) {
                $manager->revokePermissionTo($dipegang);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            foreach ($this->izinQr() as $izin) {
                Permission::firstOrCreate(['name' => $izin, 'guard_name' => 'web']);
            }

            $manager->givePermissionTo($this->izinQr());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
