<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ganti label role `warehouse` menjadi "Freelancer Gudang" (26/10/2026).
 *
 * Role ini adalah pekerja lepas yang ditugasi SPV, tetapi labelnya "Staff Gudang".
 * Akibatnya di form Tambah Pengguna tidak ada opsi bernama "Freelancer" dan pemilik
 * sistem mencari-cari role yang tidak pernah ada. Label dibaca dari
 * `roles.display_name` (dipakai form pengguna, filter daftar pengguna, dan halaman
 * role), jadi perubahannya cukup di satu baris tabel itu — bukan di kode.
 *
 * Jalur deploy produksi hanya menjalankan migrasi, bukan seeder; karena itu perubahan
 * ini WAJIB berbentuk migrasi. Seeder-nya ikut diselaraskan untuk pemasangan baru.
 */
return new class extends Migration
{
    /**
     * Label kanonis untuk seluruh role. Dipakai dua hal: mengganti label freelancer,
     * dan menambal `display_name` yang KOSONG — beberapa migrasi lama membuat role
     * hanya dengan `firstOrCreate(['name' => ...])` sehingga labelnya null dan form
     * menampilkan nama teknis hasil Str::headline ("Spv Gudang").
     *
     * @var array<string, string>
     */
    private const LABEL = [
        'super-admin' => 'Super Admin',
        'customer-service' => 'Customer Service',
        'warehouse' => 'Freelancer Gudang',
        'supervisor' => 'Supervisor Gudang',
        'courier' => 'Kurir',
        'manager' => 'Manager Distribusi',
        'distribusi' => 'Distribusi',
        'manager-repacking' => 'Manager Repacking (PJ Gudang)',
        'spv-gudang' => 'SPV Gudang',
    ];

    public function up(): void
    {
        foreach (self::LABEL as $nama => $label) {
            $role = DB::table('roles')->where('name', $nama)->where('guard_name', 'web');

            // Label freelancer diganti; label lain hanya ditambal bila masih kosong,
            // supaya label yang sudah disesuaikan manual tidak tertimpa.
            if ($nama === 'warehouse') {
                $role->update(['display_name' => $label]);

                continue;
            }

            $role->where(fn ($q) => $q->whereNull('display_name')->orWhere('display_name', ''))
                ->update(['display_name' => $label]);
        }
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'warehouse')
            ->where('guard_name', 'web')
            ->update(['display_name' => 'Staff Gudang']);
    }
};
