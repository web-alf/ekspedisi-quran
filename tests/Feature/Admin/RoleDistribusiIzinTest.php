<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Perubahan role & izin untuk Kurir dan role Distribusi.
 *
 * Diuji di atas MIGRASI SUNGGUHAN, bukan meniru isinya di tes: itulah satu-satunya
 * jalur yang benar-benar berjalan saat deploy. Story `deploy` di Envoy.blade.php
 * tidak menjalankan seeder, jadi perubahan di RolePermissionSeeder saja tidak akan
 * sampai ke produksi — perubahannya harus ada di migrasi.
 */
beforeEach(function () {
    // Izin donatur.* sengaja dibuat di sini: di produksi izin itu SUDAH ada
    // (dibuat seeder). Tanpa dibuat lebih dulu, tesnya hanya membuktikan izin
    // yang memang tidak ada.
    foreach (['donatur.read', 'donatur.create', 'donatur.update', 'donatur.delete'] as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    // Role dasar yang sudah ada sebelum migrasi ini.
    foreach ([
        RoleEnum::SUPER_ADMIN->value => 'Super Admin',
        RoleEnum::MANAGER->value => 'Manager Distribusi',
        RoleEnum::CUSTOMER_SERVICE->value => 'Customer Service',
        RoleEnum::WAREHOUSE->value => 'Gudang',
        RoleEnum::SUPERVISOR->value => 'Supervisor',
        RoleEnum::COURIER->value => 'Kurir',
    ] as $name => $display) {
        Role::firstOrCreate(['name' => $name], ['display_name' => $display, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('mencabut izin donatur dari kurir sehingga menu Kelola Donatur hilang', function () {
    // Keadaan khas kurir di produksi SEBELUM migrasi: ia punya donatur.read —
    // inilah yang membuat menu "Kelola Donatur" muncul untuknya. Keadaan itu
    // disiapkan di sini, lalu migrasinya dijalankan, karena RefreshDatabase
    // menjalankan migrasi sebelum beforeEach — jadi memberi izinnya lebih dulu
    // tidak akan membuktikan apa pun.
    $kurir = Role::where('name', RoleEnum::COURIER->value)->first();
    $kurir->givePermissionTo('donatur.read');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($kurir->permissions->pluck('name'))->toContain('donatur.read');

    $migrasi = require database_path('migrations/2026_10_04_010000_add_distribusi_role_and_muatan_permissions.php');
    $migrasi->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $kurir = $kurir->fresh();

    expect($kurir->permissions->pluck('name'))->not->toContain('donatur.read')
        ->and($kurir->permissions->pluck('name'))->not->toContain('donatur.create')
        ->and($kurir->permissions->pluck('name'))->not->toContain('donatur.update')
        ->and($kurir->permissions->pluck('name'))->not->toContain('donatur.delete');
});

it('mendaftarkan role distribusi sebagai role tersendiri', function () {
    expect(Role::where('name', RoleEnum::DISTRIBUSI->value)->exists())->toBeTrue()
        ->and(RoleEnum::DISTRIBUSI->value)->toBe('distribusi');
});

it('memberi role distribusi izin menyelesaikan distribusi', function () {
    $distribusi = Role::where('name', RoleEnum::DISTRIBUSI->value)->first();

    foreach ([
        PermissionEnum::MUATAN_COMPLETE,
        PermissionEnum::MUATAN_READ,
        PermissionEnum::MUATAN_CREATE,
        PermissionEnum::MUATAN_UPDATE,
        PermissionEnum::MUATAN_DELETE,
        PermissionEnum::MUATAN_SCAN,
        PermissionEnum::SHIPMENTS_UPDATE_STATUS,
    ] as $izin) {
        expect($distribusi->permissions->pluck('name'))->toContain($izin->value);
    }
});

it('TIDAK memberi kurir izin menyelesaikan distribusi', function () {
    $kurir = Role::where('name', RoleEnum::COURIER->value)->first();

    expect($kurir->permissions->pluck('name'))->not->toContain(PermissionEnum::MUATAN_COMPLETE->value);
});

it('memberi kurir izin yang dibutuhkan halaman barunya', function () {
    $kurir = Role::where('name', RoleEnum::COURIER->value)->first();

    expect($kurir->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_READ->value)
        ->and($kurir->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_SCAN->value)
        ->and($kurir->permissions->pluck('name'))->toContain(PermissionEnum::MUSHAF_REQUESTS_READ->value);
});

it('memberi super-admin izin menyelesaikan distribusi', function () {
    $superAdmin = Role::where('name', RoleEnum::SUPER_ADMIN->value)->first();

    expect($superAdmin->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_COMPLETE->value);
});

it('tidak menyentuh izin donatur role lain', function () {
    // Yang diminta adalah menghapusnya DARI kurir, bukan dari aplikasi.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();
    $manager->givePermissionTo(['donatur.read']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::where('name', 'donatur.read')->exists())->toBeTrue();
});

it('membuat halaman menu baru terdaftar di sidebar dengan izinnya', function () {
    // Sidebar menyaring menu berdasarkan izin; menu dengan izin yang tidak cocok
    // akan diam-diam hilang. Diuji pada berkas sumbernya karena Vitest
    // mengompilasi Svelte ke SSR sehingga komponen tidak bisa dirender.
    $sumber = File::get(resource_path('js/Layouts/AdminLayout.svelte'));

    expect($sumber)->toContain("label: 'Muatan & Distribusi'")
        ->and($sumber)->toContain("requiredPermissions: ['muatan.read']")
        ->and($sumber)->toContain("label: 'Scan Barang'")
        ->and($sumber)->toContain("requiredPermissions: ['muatan.scan']")
        ->and($sumber)->toContain("label: 'Permintaan Disetujui'")
        ->and($sumber)->toContain("requiredPermissions: ['mushaf-requests.read']");
});

it('memakai ikon yang benar-benar ada untuk menu baru', function () {
    // getMenuIcon dan HeroIcon sama-sama punya fallback: salah nama ikon TIDAK
    // error, ikonnya cuma berubah diam-diam. Karena itu namanya dicocokkan ke
    // daftar ikon yang benar-benar terdaftar.
    $layout = File::get(resource_path('js/Layouts/AdminLayout.svelte'));
    $ikon = File::get(resource_path('js/Components/UI/HeroIcon.svelte'));

    foreach (['truck', 'id-card', 'badge-check'] as $nama) {
        expect($layout)->toContain("'{$nama}'")
            ->and($ikon)->toContain("'{$nama}':");
    }
});

it('mendaftarkan role distribusi di konstanta peran frontend', function () {
    $roles = File::get(resource_path('js/constants/roles.js'));

    expect($roles)->toContain("DISTRIBUSI: 'distribusi'")
        ->and($roles)->toContain("MANAGER: 'manager'");
});

it('memberi manager distribusi izin menyelesaikan distribusi lewat migrasi', function () {
    // Distribusi butuh verifikasi manual; manager distribusi yang memverifikasi.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

    expect($manager->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_COMPLETE->value)
        ->and($manager->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_READ->value);
});

it('TIDAK memberi manager wewenang operasional muatan', function () {
    // Manager memantau dan memverifikasi; menyiapkan muatan dan memindai barang
    // adalah pekerjaan operasional gudang dan kurir.
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();
    $izin = $manager->permissions->pluck('name');

    expect($izin)->not->toContain(PermissionEnum::MUATAN_CREATE->value)
        ->and($izin)->not->toContain(PermissionEnum::MUATAN_UPDATE->value)
        ->and($izin)->not->toContain(PermissionEnum::MUATAN_DELETE->value)
        ->and($izin)->not->toContain(PermissionEnum::MUATAN_SCAN->value);
});

it('TIDAK memberi kurir izin menyelesaikan distribusi walau manager dapat', function () {
    // Perubahan untuk manager tidak boleh merembet ke kurir.
    $kurir = Role::where('name', RoleEnum::COURIER->value)->first();
    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

    expect($manager->permissions->pluck('name'))->toContain(PermissionEnum::MUATAN_COMPLETE->value)
        ->and($kurir->permissions->pluck('name'))->not->toContain(PermissionEnum::MUATAN_COMPLETE->value);
});

it('menyembunyikan menu Tugas Kurir dari manager', function () {
    // Menu "Tugas Kurir" adalah menu khusus kurir. AdminLayout memunculkan
    // dropdown bila induk ATAU salah satu anaknya cocok, jadi bila induknya ikut
    // memuat izin yang dipegang manager (mushaf-requests.read), menu itu muncul
    // untuk manager dan role lain padahal isinya bukan untuk mereka.
    $sumber = File::get(resource_path('js/Layouts/AdminLayout.svelte'));

    // Ambil blok menu Tugas Kurir saja.
    $mulai = strpos($sumber, "label: 'Tugas Kurir'");
    expect($mulai)->not->toBeFalse();

    $blok = substr($sumber, $mulai, 700);
    $induk = substr($blok, 0, strpos($blok, 'children:'));

    expect($induk)->toContain("requiredPermissions: ['muatan.scan']")
        ->and($induk)->not->toContain('mushaf-requests.read');
});

it('mengembalikan izin manager lewat down()', function () {
    $migrasi = require database_path('migrations/2026_10_06_000500_grant_muatan_complete_to_manager.php');

    $migrasi->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $manager = Role::where('name', RoleEnum::MANAGER->value)->first();

    expect($manager->permissions->pluck('name'))->not->toContain(PermissionEnum::MUATAN_COMPLETE->value)
        ->and($manager->permissions->pluck('name'))->not->toContain(PermissionEnum::MUATAN_READ->value);
});

it('menjaga menu Kelola Donatur tetap terdaftar untuk role lain', function () {
    $sumber = File::get(resource_path('js/Layouts/AdminLayout.svelte'));

    expect($sumber)->toContain("label: 'Kelola Donatur'")
        ->and($sumber)->toContain("requiredPermissions: ['donatur.read']");
});

it('mengembalikan keadaan lewat down(): role distribusi dihapus dan kurir dapat donatur lagi', function () {
    $migrasi = require database_path('migrations/2026_10_04_010000_add_distribusi_role_and_muatan_permissions.php');

    $migrasi->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $kurir = Role::where('name', RoleEnum::COURIER->value)->first();

    expect(Role::where('name', RoleEnum::DISTRIBUSI->value)->exists())->toBeFalse()
        ->and(Permission::where('name', PermissionEnum::MUATAN_COMPLETE->value)->exists())->toBeFalse()
        ->and($kurir->permissions->pluck('name'))->toContain('donatur.read')
        ->and($kurir->permissions->pluck('name'))->not->toContain(PermissionEnum::MUATAN_SCAN->value);
});
