<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manager Distribusi boleh MEMBUAT muatan dan MEMINDAI barang masuk muatan.
 *
 * Latar: manager (nama tampilannya "Manager Distribusi") sudah bisa membuka
 * halaman Muatan dan menyelesaikan distribusi, TETAPI tidak bisa membuat muatan
 * maupun memindai barang. Akibatnya orang yang bertanggung jawab atas distribusi
 * harus meminta orang lain menyiapkan muatannya — dan penyiapan itu justru
 * bagian dari pekerjaannya.
 *
 * Ditambahkan HANYA dua izin:
 *   muatan.create  — membuat muatan
 *   muatan.scan    — memindai resi/kerdus masuk muatan
 *
 * TIDAK ditambahkan muatan.update/delete. Manager sudah punya keduanya dari
 * seeder, dan itu memang disengaja: ia perlu mengoreksi muatan yang salah.
 * Yang belum dimilikinya adalah membuat dan memindai — dua hal yang membuat
 * muatan benar-benar bisa disiapkan seorang diri.
 *
 * Sifatnya menambah, bukan menggantikan: izin manager yang sudah ada
 * (shipments.*, mushaf-requests.*, warehouse.*, supervisor.*) tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Dibuat bila belum ada supaya migrasi ini tidak bergantung pada migrasi
        // lain atau pada seeder — di lingkungan uji (RefreshDatabase) hanya migrasi
        // yang berjalan.
        foreach ([
            PermissionEnum::MUATAN_CREATE->value,
            PermissionEnum::MUATAN_SCAN->value,
        ] as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        // Role dipastikan ada, bukan hanya diambil: di basis data yang belum pernah
        // di-seed role manager belum ada, dan migrasi yang diam-diam melewati
        // langkahnya sendiri adalah migrasi yang gagal tanpa bersuara.
        $manager = Role::firstOrCreate(
            ['name' => RoleEnum::MANAGER->value],
            ['display_name' => 'Manager Distribusi', 'guard_name' => 'web']
        );

        $manager->givePermissionTo([
            PermissionEnum::MUATAN_CREATE->value,
            PermissionEnum::MUATAN_SCAN->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

        if ($manager) {
            foreach ([
                PermissionEnum::MUATAN_CREATE->value,
                PermissionEnum::MUATAN_SCAN->value,
            ] as $nama) {
                if (Permission::where('name', $nama)->where('guard_name', 'web')->exists()
                    && $manager->hasPermissionTo($nama)) {
                    $manager->revokePermissionTo($nama);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
