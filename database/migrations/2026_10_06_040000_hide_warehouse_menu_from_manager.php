<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hilangkan "Manajemen Gudang" dari sidebar Manager Distribusi.
 *
 * Menu itu muncul karena keempat belas izin di bawah ini dipegang manager.
 * AdminLayout memunculkan induk dropdown bila manager memegang induk ATAU salah
 * satu anaknya, jadi selama satu saja izin gudang tersisa, menunya tetap tampil
 * beserta anak-anak yang izinnya masih ada. Karena itu pencabutannya harus
 * lengkap.
 *
 * Izin-izin ini juga yang MENJAGA URL-nya (middleware `permission:`), sehingga
 * mencabutnya sekaligus menutup akses langsung ke halaman gudang — bukan sekadar
 * menyembunyikan tautan. Frontend tidak perlu diubah.
 *
 * Yang TETAP dimiliki manager (bukan bagian menu ini):
 *   - muatan.*           alur distribusi (lihat & menyelesaikan)
 *   - shipments.*        halaman Pengemasan
 *   - mushaf-requests.*  permintaan mushaf
 *   - qr.generate/scan/verify  alat kerjanya atas resi
 *   - dashboard.*, status.*, system.monitor  halaman Sistem
 *
 * Dijalankan sebagai migrasi, bukan lewat seeder: `php artisan migrate` yang
 * dijalankan deploy-lah yang menyentuh produksi, dan seeder tidak dijalankan
 * saat deploy.
 */
return new class extends Migration
{
    /**
     * Izin yang membuat menu "Manajemen Gudang" tampil.
     *
     * @return array<int, string>
     */
    private function izinMenuGudang(): array
    {
        return [
            // Induk dropdown.
            PermissionEnum::WAREHOUSE_DASHBOARD->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,

            // Anak-anaknya.
            PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
            PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
            PermissionEnum::WAREHOUSE_QR_VERIFY->value,

            // Hanya muncul di grup menu ini — cabang laporan Analitik Kinerja
            // (/admin/supervisor/performance-report).
            PermissionEnum::SUPERVISOR_PERFORMANCE_REPORTS->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_VIEW->value,

            // Tidak punya rute sendiri, tetapi tetap izin "gudang" pada manager.
            PermissionEnum::SUPERVISOR_DASHBOARD->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_REDISTRIBUTE->value,
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            // Hanya mencabut, bukan syncPermissions: izin manager di luar grup ini
            // dibiarkan utuh, dan izin yang memang tidak ia miliki tidak ikut
            // tercabut dari role lain.
            $manager->revokePermissionTo($this->izinMenuGudang());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            // Mengembalikan keadaan SEBELUM migrasi ini, bukan keadaan yang
            // diinginkan: manager memang pernah memegang seluruh izin ini.
            $manager->givePermissionTo($this->izinMenuGudang());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
