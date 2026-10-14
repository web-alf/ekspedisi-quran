<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * super-admin memegang SELURUH izin — dipulihkan lewat migrasi, bukan lewat seeder.
 *
 * Kejadian di produksi (12 Okt 2026): peran super-admin hanya memegang 90 dari 95
 * izin. Yang hilang persis lima izin `muatan.*` (read, create, update, delete,
 * scan) — `muatan.complete` sudah ada. Penyebabnya migrasi
 * 2026_10_04_010000_add_distribusi_role_and_muatan_permissions: ia membuat keenam
 * izin muatan, tetapi kepada super-admin hanya memberikan `muatan.complete` dan
 * `shipments.update-status`.
 *
 * Akibatnya BUKAN 403. Tiga lapisan tetap meloloskan super-admin tanpa melihat isi
 * tabel izin — Gate::before (AppServiceProvider), cabang hasRole di
 * PermissionMiddleware, dan policy. Yang tidak punya cabang itu adalah daftar menu:
 * resources/js/Layouts/AdminLayout.svelte menyaring dengan `permissions.includes(...)`,
 * dan daftar izin yang dikirim ke browser berasal dari getAllPermissions() — yaitu
 * isi tabel. Jadi menu "Muatan & Distribusi" (butuh muatan.read) dan "Tugas Kurir"
 * (butuh muatan.scan) HILANG dari sidebar super-admin, walau URL-nya dibuka tetap
 * menjawab 200.
 *
 * Seeder sudah menyelesaikannya lewat `syncPermissions(Permission::all())`, tapi
 * jalur deploy produksi hanya menjalankan migrasi — seeder tidak pernah jalan di
 * sana. Karena itu setiap izin baru yang lahir setelah seeding terakhir akan bolong
 * lagi di produksi bila tidak diberikan lewat migrasi.
 *
 * Pemberiannya ADITIF (`givePermissionTo`), bukan `syncPermissions`: sync MENCABUT
 * izin yang tidak ada di daftarnya, dan di basis data tanpa seeder (RefreshDatabase
 * pada uji) `Permission::all()` bisa kosong — super-admin justru akan kehilangan
 * seluruh izinnya.
 */
return new class extends Migration
{
    /**
     * Izin muatan yang lahir dari migrasi 2026_10_04_010000. Dipastikan ada lagi di
     * sini supaya migrasi ini utuh sendiri: lingkungan uji dengan RefreshDatabase
     * hanya menjalankan migrasi, dan tanpa ini pemberian izin gagal dengan
     * PermissionDoesNotExist.
     *
     * @var list<string>
     */
    private array $izinMuatan = [
        PermissionEnum::MUATAN_READ->value,
        PermissionEnum::MUATAN_CREATE->value,
        PermissionEnum::MUATAN_UPDATE->value,
        PermissionEnum::MUATAN_DELETE->value,
        PermissionEnum::MUATAN_SCAN->value,
        PermissionEnum::MUATAN_COMPLETE->value,
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->izinMuatan as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        $superAdmin = Role::findOrCreate(RoleEnum::SUPER_ADMIN->value, 'web');

        $superAdmin->givePermissionTo(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja tidak melakukan apa pun. Mengembalikannya berarti mencabut izin
        // yang memang hak super-admin, yaitu menghidupkan lagi gejalanya (menu
        // muatan hilang dari sidebar) tanpa manfaat. Pencabutan yang memang
        // diinginkan tetap bisa dilakukan migrasi asalnya, 2026_10_04_010000.
    }
};
