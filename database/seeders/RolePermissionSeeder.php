<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Dashboard permissions
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::DASHBOARD_ANALYTICS->value,

            // User management
            PermissionEnum::USERS_CREATE->value,
            PermissionEnum::USERS_READ->value,
            PermissionEnum::USERS_UPDATE->value,
            PermissionEnum::USERS_DELETE->value,
            PermissionEnum::USERS_TOGGLE->value,

            // Role management
            PermissionEnum::ROLES_CREATE->value,
            PermissionEnum::ROLES_READ->value,
            PermissionEnum::ROLES_UPDATE->value,
            PermissionEnum::ROLES_DELETE->value,

            // Permission management
            PermissionEnum::PERMISSIONS_CREATE->value,
            PermissionEnum::PERMISSIONS_READ->value,
            PermissionEnum::PERMISSIONS_UPDATE->value,
            PermissionEnum::PERMISSIONS_DELETE->value,

            // Donatur management
            PermissionEnum::DONATUR_CREATE->value,
            PermissionEnum::DONATUR_READ->value,
            PermissionEnum::DONATUR_UPDATE->value,
            PermissionEnum::DONATUR_DELETE->value,
            PermissionEnum::DONATUR_IMPORT->value,
            PermissionEnum::DONATUR_EXPORT->value,

            // Shipment management
            PermissionEnum::SHIPMENTS_CREATE->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_UPDATE->value,
            PermissionEnum::SHIPMENTS_DELETE->value,
            PermissionEnum::SHIPMENTS_EXPORT->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::SHIPMENTS_BULK_UPDATE->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,

            // Mushaf request management
            PermissionEnum::MUSHAF_REQUESTS_CREATE->value,
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::MUSHAF_REQUESTS_UPDATE->value,
            PermissionEnum::MUSHAF_REQUESTS_DELETE->value,
            PermissionEnum::MUSHAF_REQUESTS_APPROVE->value,
            PermissionEnum::MUSHAF_REQUESTS_REJECT->value,
            PermissionEnum::MUSHAF_REQUESTS_PROCESS->value,

            // Certificate management
            PermissionEnum::CERTIFICATES_CREATE->value,
            PermissionEnum::CERTIFICATES_READ->value,
            PermissionEnum::CERTIFICATES_UPDATE->value,
            PermissionEnum::CERTIFICATES_DELETE->value,
            PermissionEnum::CERTIFICATES_GENERATE->value,
            PermissionEnum::CERTIFICATES_DOWNLOAD->value,

            // Template management
            PermissionEnum::TEMPLATES_CREATE->value,
            PermissionEnum::TEMPLATES_READ->value,
            PermissionEnum::TEMPLATES_UPDATE->value,
            PermissionEnum::TEMPLATES_DELETE->value,
            PermissionEnum::TEMPLATES_SET_DEFAULT->value,
            PermissionEnum::TEMPLATES_TOGGLE->value,

            // Warehouse specific permissions (granular)
            PermissionEnum::WAREHOUSE_DASHBOARD->value,
            PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
            PermissionEnum::WAREHOUSE_PACKING_SCAN->value,
            PermissionEnum::WAREHOUSE_PACKING_SEAL->value,
            PermissionEnum::WAREHOUSE_QR_GENERATE->value,
            PermissionEnum::WAREHOUSE_QR_SCAN->value,
            PermissionEnum::WAREHOUSE_QR_VERIFY->value,
            PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value,
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_UPDATE->value,
            PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
            PermissionEnum::WAREHOUSE_BOXES_SEAL->value,
            // Box overrides (new granular permissions)
            PermissionEnum::WAREHOUSE_BOX_UPDATE_SEALED->value,
            PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value,

            // Supervisor permissions
            PermissionEnum::SUPERVISOR_DASHBOARD->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_REDISTRIBUTE->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_VIEW->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_REPORTS->value,

            // QR Code system permissions
            PermissionEnum::QR_GENERATE->value,
            PermissionEnum::QR_SCAN->value,
            PermissionEnum::QR_VERIFY->value,
            PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value,

            // Status management
            PermissionEnum::STATUS_UPDATE->value,
            PermissionEnum::STATUS_TRACK->value,

            // Wakaf batch permissions
            PermissionEnum::WAKAF_BATCH_CREATE->value,
            PermissionEnum::WAKAF_BATCH_READ->value,
            PermissionEnum::WAKAF_BATCH_UPDATE->value,
            PermissionEnum::WAKAF_BATCH_DELETE->value,

            // Settings management
            PermissionEnum::SETTINGS_READ->value,
            PermissionEnum::SETTINGS_WRITE->value,
            PermissionEnum::SETTINGS_DELETE->value,

            // System monitoring
            PermissionEnum::SYSTEM_MONITOR->value,
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin Role — akses penuh ke seluruh izin.
        //
        // Dulu role ini sengaja TIDAK diberi izin operasional gudang, dengan niat
        // "pengawas strategis yang tidak menjalankan operasi gudang". Niat itu
        // ditinggalkan karena tidak pernah benar-benar ditegakkan, dan keadaan
        // setengah jalan itu justru menyesatkan:
        //
        //   - Gate::before        -> super-admin lolos SEMUA pemeriksaan izin
        //   - PermissionMiddleware-> super-admin lolos SEMUA pemeriksaan route
        //   - sidebar/daftar menu -> TIDAK punya cabang super-admin, murni cek
        //                            izin mentah
        //
        // Akibatnya halaman gudang tetap bisa dibuka lewat URL, tetapi menunya
        // HILANG dari sidebar begitu izinnya tidak ada. Pemilik sistem mengira
        // dirinya tidak punya akses padahal punya — persis kebingungan yang
        // pernah terjadi pada /admin/warehouse/packing.
        //
        // Sekarang: super-admin = akses penuh, satu aturan di semua lapisan.
        $superAdmin = Role::firstOrCreate(
            ['name' => RoleEnum::SUPER_ADMIN->value],
            ['display_name' => 'Super Admin', 'guard_name' => 'web']
        );

        $superAdmin->syncPermissions(Permission::all());

        // Customer Service Role
        $cs = Role::firstOrCreate(
            ['name' => RoleEnum::CUSTOMER_SERVICE->value],
            ['display_name' => 'Customer Service', 'guard_name' => 'web']
        );
        $cs->syncPermissions([
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::DONATUR_CREATE->value,
            PermissionEnum::DONATUR_READ->value,
            PermissionEnum::DONATUR_UPDATE->value,
            PermissionEnum::DONATUR_IMPORT->value,
            PermissionEnum::DONATUR_EXPORT->value,
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::MUSHAF_REQUESTS_UPDATE->value,
            PermissionEnum::MUSHAF_REQUESTS_APPROVE->value,
            PermissionEnum::MUSHAF_REQUESTS_REJECT->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::STATUS_TRACK->value,
        ]);

        // Warehouse Role - includes granular warehouse permissions
        $warehouse = Role::firstOrCreate(
            ['name' => RoleEnum::WAREHOUSE->value],
            ['display_name' => 'Staff Gudang', 'guard_name' => 'web']
        );
        $warehouse->syncPermissions([
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::DONATUR_READ->value,
            PermissionEnum::SHIPMENTS_CREATE->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_UPDATE->value,
            PermissionEnum::SHIPMENTS_EXPORT->value,
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::MUSHAF_REQUESTS_PROCESS->value,
            PermissionEnum::CERTIFICATES_GENERATE->value,
            PermissionEnum::CERTIFICATES_READ->value,
            PermissionEnum::CERTIFICATES_DOWNLOAD->value,
            // Warehouse specific permissions
            PermissionEnum::WAREHOUSE_DASHBOARD->value,
            PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
            PermissionEnum::WAREHOUSE_PACKING_SCAN->value,
            PermissionEnum::WAREHOUSE_PACKING_SEAL->value,
            PermissionEnum::WAREHOUSE_QR_GENERATE->value,
            PermissionEnum::WAREHOUSE_QR_SCAN->value,
            PermissionEnum::WAREHOUSE_QR_VERIFY->value,
            PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value,
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_UPDATE->value,
            PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
            PermissionEnum::WAREHOUSE_BOXES_SEAL->value,
            PermissionEnum::WAREHOUSE_BOX_UPDATE_SEALED->value,
            PermissionEnum::WAREHOUSE_BOX_UPDATE_ANY->value,
            PermissionEnum::QR_GENERATE->value,
            PermissionEnum::QR_SCAN->value,
            PermissionEnum::QR_VERIFY->value,
            PermissionEnum::WAREHOUSE_QR_BULK_GENERATE->value,
            PermissionEnum::STATUS_UPDATE->value,
            PermissionEnum::WAKAF_BATCH_READ->value,
        ]);

        // Supervisor Role - can monitor and manage warehouse operations (but NOT operational warehouse tasks)
        $supervisor = Role::firstOrCreate(
            ['name' => RoleEnum::SUPERVISOR->value],
            ['display_name' => 'Supervisor Gudang', 'guard_name' => 'web']
        );
        $supervisor->syncPermissions([
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::DONATUR_READ->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_UPDATE->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::CERTIFICATES_READ->value,
            // Supervisor specific permissions
            PermissionEnum::SUPERVISOR_DASHBOARD->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_REDISTRIBUTE->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_VIEW->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_REPORTS->value,
            // Can view warehouse analytics and boxes (but NOT operational dashboard)
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_VIEW->value,
            PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
            PermissionEnum::QR_VERIFY->value,
            PermissionEnum::STATUS_TRACK->value,
            PermissionEnum::WAKAF_BATCH_READ->value,
            PermissionEnum::WAKAF_BATCH_UPDATE->value,
        ]);

        // Manager Role — peran pengawas/manajerial (mis. pimpinan yayasan).
        // Izinnya luas untuk baca & ubah, TETAPI sengaja TIDAK diberi:
        //   - permissions.*    : definisi izin adalah wilayah super-admin;
        //                        manager bisa mengubah/menghapusnya = merusak
        //                        integritas sistem izin.
        //   - *.delete         : penghapusan data produksi (donatur, pengiriman,
        //                        mushaf request, sertifikat, wakaf batch, template)
        //                        tidak dapat dipulihkan -> wewenang super-admin.
        // Catatan: role ini sebelumnya HANYA ada di database produksi (dibuat manual,
        // 70 izin) dan tidak tercatat di kode. Didaftarkan di sini agar dapat
        // di-review, di-diff, dan dipulihkan lewat seeder.
        $manager = Role::firstOrCreate(
            ['name' => RoleEnum::MANAGER->value],
            ['display_name' => 'Manager Distribusi', 'guard_name' => 'web']
        );
        $manager->syncPermissions([
            // Dashboard
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::DASHBOARD_ANALYTICS->value,

            // Donatur — SENGAJA TIDAK DIBERIKAN kepada manager.
            // Halaman "Kelola Donatur" diminta dihilangkan untuk role manager supaya
            // manajer fokus pada alur permintaan → pengiriman, bukan mengelola data
            // donatur (wilayah customer-service). Karena AdminLayout memfilter menu
            // berdasarkan izin ini, mencabutnya sekaligus menghilangkan menunya dan
            // membuat akses langsung ke URL-nya ditolak.
            // Aman: daftar donatur yang dipakai halaman Pengiriman diambil langsung
            // di controller (tidak lewat izin donatur.*), dan seluruh route
            // pengiriman dijaga izin shipments.*.

            // Shipments (tanpa delete)
            PermissionEnum::SHIPMENTS_CREATE->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_UPDATE->value,
            PermissionEnum::SHIPMENTS_EXPORT->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
            PermissionEnum::SHIPMENTS_BULK_UPDATE->value,

            // Mushaf requests (tanpa delete)
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::MUSHAF_REQUESTS_CREATE->value,
            PermissionEnum::MUSHAF_REQUESTS_UPDATE->value,
            PermissionEnum::MUSHAF_REQUESTS_APPROVE->value,
            PermissionEnum::MUSHAF_REQUESTS_REJECT->value,
            PermissionEnum::MUSHAF_REQUESTS_PROCESS->value,

            // Certificates — SENGAJA TIDAK DIBERIKAN kepada manager.
            //
            // Sertifikat adalah dokumen pertanggungjawaban wakaf. Dulu manager
            // memegang certificates.read/create/update/generate/download, sehingga
            // menu "Sertifikat" tampil di sidebar-nya (AdminLayout memfilter menu
            // berdasarkan izin certificates.read) dan seluruh alur sertifikat
            // terbuka baginya. Pengelolaan sertifikat bukan bagian alur kerja
            // manager distribusi (permintaan → pengiriman → pemantauan gudang).
            //
            // Aman dicabut: izin certificates.* hanya dipakai oleh rute sertifikat
            // itu sendiri; tidak ada halaman lain (Pengiriman, Box Tracking) yang
            // bergantung padanya. Template sertifikat TETAP tidak diberikan agar
            // seluruh grup menu "Sertifikat" hilang sekaligus, bukan separuh.

            // Certificate templates — juga tidak diberikan, lihat catatan di atas.

            // Wakaf batch (tanpa delete)
            PermissionEnum::WAKAF_BATCH_READ->value,
            PermissionEnum::WAKAF_BATCH_CREATE->value,
            PermissionEnum::WAKAF_BATCH_UPDATE->value,

            // Supervisor & monitoring
            PermissionEnum::SUPERVISOR_DASHBOARD->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value,
            PermissionEnum::SUPERVISOR_WAREHOUSE_REDISTRIBUTE->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_VIEW->value,
            PermissionEnum::SUPERVISOR_PERFORMANCE_REPORTS->value,
            PermissionEnum::SYSTEM_MONITOR->value,

            // Warehouse — MEMANTAU saja, bukan MENJALANKAN.
            //
            // Konteks: manager dulu memegang SELURUH 15 izin warehouse.*, persis
            // sama dengan Staff Gudang. Artinya manager bisa mengemas, menyegel,
            // dan MEMBONGKAR kerdus yang sudah tersegel — padahal perannya
            // mengawasi distribusi, bukan mengerjakan operasinya.
            //
            // Perbandingan peran: supervisor juga "Gudang", tapi hanya memantau
            // (supervisor.* + warehouse.tasks.view + warehouse.boxes.view +
            // warehouse.performance.view, tanpa izin operasional). Manager
            // sekarang mengikuti pola yang sama.
            //
            // Diambil dari manager (operasional — mengubah keadaan fisik):
            //   warehouse.packing.scan        memindai & memasukkan mushaf ke kerdus
            //   warehouse.packing.seal        menyegel kerdus
            //   warehouse.boxes.seal          menyegel kerdus (jalur lain)
            //   warehouse.box.update_any      mengubah isi kerdus siapa pun
            //   warehouse.box.update_sealed   menembus kerdus tersegel (bongkar!)
            //   warehouse.tasks.update        membatalkan & mengulang job
            //   warehouse.qr.generate         membuat QR baru
            //   warehouse.qr.bulk_generate    membuat QR massal
            //   warehouse.qr.scan             memindai QR kerdus
            //
            // Tetap diberikan (memantau & membaca):
            //   warehouse.dashboard, warehouse.performance.view,
            //   warehouse.packing.view, warehouse.boxes.view,
            //   warehouse.tasks.view, warehouse.qr.verify, qr.*
            PermissionEnum::WAREHOUSE_DASHBOARD->value,
            PermissionEnum::WAREHOUSE_PERFORMANCE_VIEW->value,
            PermissionEnum::WAREHOUSE_PACKING_VIEW->value,
            PermissionEnum::WAREHOUSE_BOXES_VIEW->value,
            PermissionEnum::WAREHOUSE_TASKS_VIEW->value,

            // QR
            PermissionEnum::QR_GENERATE->value,
            PermissionEnum::QR_SCAN->value,
            PermissionEnum::QR_VERIFY->value,
            PermissionEnum::WAREHOUSE_QR_VERIFY->value,

            // Status
            PermissionEnum::STATUS_UPDATE->value,
            PermissionEnum::STATUS_TRACK->value,
        ]);

        // Courier Role
        $courier = Role::firstOrCreate(
            ['name' => RoleEnum::COURIER->value],
            ['display_name' => 'Kurir', 'guard_name' => 'web']
        );
        $courier->syncPermissions([
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_UPDATE->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::DONATUR_READ->value,
            PermissionEnum::STATUS_UPDATE->value,
            PermissionEnum::STATUS_TRACK->value,
        ]);

        $this->command->info('✅ Roles and permissions created successfully!');
        $this->command->info('🔐 Super Admin: All permissions');
        $this->command->info('👥 Customer Service: Donatur + Mushaf Request management');
        $this->command->info('📦 Warehouse: Full warehouse operations + QR system');
        $this->command->info('👨‍💼 Supervisor: Warehouse monitoring + performance reports');
        $this->command->info('🚚 Courier: Shipment tracking + status updates');
    }
}
