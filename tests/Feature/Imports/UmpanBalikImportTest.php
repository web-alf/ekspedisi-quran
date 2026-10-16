<?php

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: detail baris yang gagal saat import harus SAMPAI ke halaman.
 *
 * Controller sudah menghitung daftar baris gagal sejak awal, tetapi
 * HandleInertiaRequests hanya meneruskan flash success/error/info/warning —
 * 'import_errors' tidak ada di daftar itu, jadi datanya dibuang di middleware
 * dan halaman tidak pernah bisa menampilkannya. Pengguna hanya melihat
 * "Berhasil import N data" tanpa tahu ada baris yang hilang.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole(RoleEnum::SUPER_ADMIN->value);
});

it('meneruskan daftar baris gagal ke halaman lewat flash', function () {
    $gagal = [
        ['row' => 3, 'error' => 'Validation: Nomor HP wajib diisi', 'data' => []],
        ['row' => 7, 'error' => 'Validation: Alamat lengkap wajib diisi', 'data' => []],
    ];

    // Tirukan apa yang controller kirim lewat back()->with([...]).
    $this->actingAs($this->admin)
        ->withSession([
            'warning' => 'Import selesai: 5 data berhasil, 2 data GAGAL dan tidak ikut masuk',
            'import_errors' => $gagal,
        ])
        ->get('/admin/mushaf-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.warning', 'Import selesai: 5 data berhasil, 2 data GAGAL dan tidak ikut masuk')
            ->where('flash.import_errors.0.row', 3)
            ->where('flash.import_errors.0.error', 'Validation: Nomor HP wajib diisi')
            ->where('flash.import_errors.1.row', 7)
            ->where('flash.import_errors.1.error', 'Validation: Alamat lengkap wajib diisi')
        );
});

it('tidak mengirim daftar kosong saat import berhasil seluruhnya', function () {
    $this->actingAs($this->admin)
        ->withSession(['success' => 'Berhasil import 5 data mushaf request'])
        ->get('/admin/mushaf-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.success', 'Berhasil import 5 data mushaf request')
            ->where('flash.import_errors', null)
        );
});

it('memperingatkan saat hanya sebagian berkas yang masuk', function () {
    // Pesannya wajib menyebut berapa yang BERHASIL — tanpa itu pengguna tidak
    // bisa tahu berapa baris yang sebenarnya masuk.
    $this->actingAs($this->admin)
        ->withSession([
            'warning' => 'Import selesai: 2 data berhasil, 1 data GAGAL dan tidak ikut masuk',
            'import_errors' => [['row' => 4, 'error' => 'Validation: Alamat lengkap wajib diisi']],
        ])
        ->get('/admin/mushaf-requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.warning', fn ($pesan) => str_contains($pesan, '2 data berhasil')
                && str_contains($pesan, '1 data GAGAL'))
        );
});
