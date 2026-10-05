<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role "distribusi" + izin muatan, dan mencabut "Kelola Donatur" dari kurir.
 *
 * Tiga hal, semuanya diminta tim:
 *
 * 1. Role BARU `distribusi` — satu-satunya role (selain super-admin) yang boleh
 *    menyelesaikan distribusi, yaitu mengubah status pengiriman menjadi
 *    "Diterima". Kurir hanya boleh memindahkan status perjalanan.
 *
 * 2. Izin `muatan.*` untuk halaman Management Muatan & Distribusi. Muatan adalah
 *    sekumpulan resi yang diantar satu kurir dalam satu perjalanan; status
 *    distribusinya dipantau per muatan.
 *
 * 3. Mencabut `donatur.read` dari kurir supaya menu "Kelola Donatur" hilang —
 *    AdminLayout menyaring menu berdasarkan izin, jadi mencabutnya sekaligus
 *    menolak akses langsung ke URL-nya. Kurir tidak perlu data donatur: yang
 *    dibutuhkannya hanya resi, alamat tujuan, dan nama penerima.
 *
 * Kenapa lewat migrasi, bukan seeder: story `deploy` di Envoy.blade.php TIDAK
 * menjalankan seeder, jadi perubahan RolePermissionSeeder saja tidak akan sampai
 * ke server produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // --- 1. Izin muatan & distribusi ---
        $izinBaru = [
            PermissionEnum::MUATAN_READ->value,
            PermissionEnum::MUATAN_CREATE->value,
            PermissionEnum::MUATAN_UPDATE->value,
            PermissionEnum::MUATAN_DELETE->value,
            PermissionEnum::MUATAN_SCAN->value,
            PermissionEnum::MUATAN_COMPLETE->value,
        ];

        // Semua izin yang akan diberikan di bawah, termasuk yang seharusnya sudah
        // ada dari seeder. WAJIB dibuat bila belum ada: seeder tidak dijalankan
        // saat deploy, dan di lingkungan uji (RefreshDatabase) yang berjalan hanya
        // migrasi — tanpa ini, migrasi gagal dengan "no permission named ...".
        $izinDasar = [
            PermissionEnum::DASHBOARD_VIEW->value,
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
            PermissionEnum::STATUS_TRACK->value,
        ];

        foreach (array_merge($izinBaru, $izinDasar) as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        // --- 2. Role distribusi ---
        $distribusi = Role::firstOrCreate(
            ['name' => RoleEnum::DISTRIBUSI->value],
            ['display_name' => 'Distribusi', 'guard_name' => 'web']
        );

        // Hanya diberi what it takes untuk memantau dan menyelesaikan muatan.
        // SENGAJA tanpa donatur.* dan tanpa warehouse.* — role ini menyelesaikan
        // distribusi, bukan mengelola data atau mengurus gudang.
        $distribusi->syncPermissions([
            PermissionEnum::DASHBOARD_VIEW->value,

            // Melihat resi yang akan/sedang diantar
            PermissionEnum::SHIPMENTS_READ->value,
            PermissionEnum::SHIPMENTS_TRACK->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,

            // Halaman muatan
            PermissionEnum::MUATAN_READ->value,
            PermissionEnum::MUATAN_CREATE->value,
            PermissionEnum::MUATAN_UPDATE->value,
            PermissionEnum::MUATAN_DELETE->value,
            PermissionEnum::MUATAN_SCAN->value,
            PermissionEnum::MUATAN_COMPLETE->value,

            // Data calon permintaan mushaf yang sudah disetujui
            PermissionEnum::MUSHAF_REQUESTS_READ->value,

            PermissionEnum::STATUS_TRACK->value,
        ]);

        // --- 3. Kurir: kelola donatur dihapus, izin muatan ditambah ---
        //
        // Role-nya dipastikan ada (bukan hanya diambil) supaya migrasi ini utuh
        // dengan sendirinya: kalau role kurir belum ada — mis. di basis data yang
        // belum pernah di-seed — migrasi tetap berjalan dan tidak diam-diam
        // melewati langkah yang justru diminta.
        $kurir = Role::firstOrCreate(
            ['name' => RoleEnum::COURIER->value],
            ['display_name' => 'Kurir', 'guard_name' => 'web']
        );

        // Dicabut satu per satu HANYA bila izinnya ada. Mencabut izin yang tidak
        // ada seharusnya tidak apa-apa, tetapi Spatie melempar
        // PermissionDoesNotExist — dan di basis data yang belum pernah di-seed,
        // izin donatur.* memang belum ada. Tanpa penjagaan ini, migrasi gagal di
        // lingkungan uji dan melewati seluruh langkah setelahnya.
        foreach (['donatur.read', 'donatur.create', 'donatur.update', 'donatur.delete'] as $nama) {
            if (Permission::where('name', $nama)->where('guard_name', 'web')->exists()) {
                $kurir->revokePermissionTo($nama);
            }
        }

        $kurir->givePermissionTo([
            PermissionEnum::MUATAN_READ->value,
            PermissionEnum::MUATAN_SCAN->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
            // Untuk halaman "Data calon permintaan mushaf yang sudah disetujui"
            PermissionEnum::MUSHAF_REQUESTS_READ->value,
        ]);

        // --- 4. Super-admin boleh menyelesaikan distribusi ---
        $superAdmin = Role::firstOrCreate(
            ['name' => RoleEnum::SUPER_ADMIN->value],
            ['display_name' => 'Super Admin', 'guard_name' => 'web']
        );

        $superAdmin->givePermissionTo([
            PermissionEnum::MUATAN_COMPLETE->value,
            PermissionEnum::SHIPMENTS_UPDATE_STATUS->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $kurir = Role::where('name', RoleEnum::COURIER->value)->first();

        if ($kurir) {
            if (Permission::where('name', 'donatur.read')->where('guard_name', 'web')->exists()) {
                $kurir->givePermissionTo(['donatur.read']);
            }

            $kurir->revokePermissionTo([
                PermissionEnum::MUATAN_READ->value,
                PermissionEnum::MUATAN_SCAN->value,
                PermissionEnum::MUSHAF_REQUESTS_READ->value,
            ]);
        }

        $distribusi = Role::where('name', RoleEnum::DISTRIBUSI->value)->first();

        if ($distribusi) {
            $distribusi->delete();
        }

        foreach ([
            PermissionEnum::MUATAN_READ->value,
            PermissionEnum::MUATAN_CREATE->value,
            PermissionEnum::MUATAN_UPDATE->value,
            PermissionEnum::MUATAN_DELETE->value,
            PermissionEnum::MUATAN_SCAN->value,
            PermissionEnum::MUATAN_COMPLETE->value,
        ] as $nama) {
            Permission::where('name', $nama)->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
