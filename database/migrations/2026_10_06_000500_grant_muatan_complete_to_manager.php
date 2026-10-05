<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manager Distribusi boleh menyelesaikan distribusi.
 *
 * Alasan: distribusi butuh VERIFIKASI MANUAL sebelum dinyatakan tuntas, dan
 * manager distribusi adalah orang yang memverifikasi. Tanpa wewenang ini, satu-
 * satunya yang bisa menutup muatan adalah super-admin, sehingga pekerjaan rutin
 * itu menggantung pada satu peran.
 *
 * Manager juga memang sudah dibatasi ke tahap distribusi saja lewat
 * PengirimanStageVisibility (hanya selesai-packing, pengiriman, diterima),
 * sehingga perannya sudah tepat untuk tugas ini.
 *
 * Sifat wewenangnya MEMANTAU, bukan mengelola: manager hanya boleh MELIHAT
 * muatan dan MENYELESAIKANNYA. Ia tidak diberi muatan.create/update/delete —
 * menyiapkan muatan dan memindai barang adalah pekerjaan operasional gudang dan
 * kurir, bukan pengawas. Pola ini sama dengan izin warehouse manager yang
 * sengaja hanya membaca.
 *
 * Kurir TETAP tidak boleh: ia tidak punya muatan.complete, dan MuatanController
 * juga menolaknya di tingkat peran sebagai sabuk pengaman kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Dibuat bila belum ada supaya migrasi ini tidak bergantung pada migrasi
        // lain atau pada seeder — di lingkungan uji (RefreshDatabase) hanya
        // migrasi yang berjalan.
        foreach ([
            PermissionEnum::MUATAN_COMPLETE->value,
            PermissionEnum::MUATAN_READ->value,
        ] as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        // Role-nya dipastikan ada, bukan hanya diambil. Di produksi manager sudah
        // ada (dibuat seeder), tetapi di basis data yang belum pernah di-seed role
        // itu belum ada — dan migrasi yang diam-diam melewati langkahnya sendiri
        // adalah migrasi yang gagal tanpa bersuara. Pola ini sama dengan migrasi
        // 2026_10_04_010000 yang membuat role kurir dan super-admin.
        $manager = Role::firstOrCreate(
            ['name' => RoleEnum::MANAGER->value],
            ['display_name' => 'Manager Distribusi', 'guard_name' => 'web']
        );

        // Hanya dua izin, dan sengaja HANYA dua. Sisanya dibiarkan apa adanya:
        // migrasi ini menambah, bukan menggantikan, sehingga izin manager yang
        // sudah ada (shipments.*, mushaf-requests.*, dan seterusnya) tidak ikut
        // terhapus.
        $manager->givePermissionTo([
            PermissionEnum::MUATAN_READ->value,
            PermissionEnum::MUATAN_COMPLETE->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            $manager->revokePermissionTo([
                PermissionEnum::MUATAN_READ->value,
                PermissionEnum::MUATAN_COMPLETE->value,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
