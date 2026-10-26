<?php

use App\Enums\RoleEnum;
use App\Models\User;
use App\Support\PilihanRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: dropdown role di form Tambah/Ubah Pengguna.
 *
 * Dulu form menampilkan `Role::all()` mentah — urut id basis data, `warehouse`
 * berlabel "Staff Gudang" (padahal itu freelancer), dan role `distribusi` yang
 * tak punya akun ikut ditawarkan. Pemilik sistem mencari opsi "Freelancer" yang
 * tidak pernah ada. Sekarang daftarnya berkelompok dan labelnya satu sumber
 * (`roles.display_name`).
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function adminPengguna(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::SUPER_ADMIN->value);

    return $user;
}

/** @return array<int, string> daftar value role yang tampil, urut tampil */
function nilaiRoleTampil(array $kelompok): array
{
    return collect($kelompok)
        ->flatMap(fn (array $grup) => collect($grup['roles'])->pluck('value'))
        ->all();
}

it('menampilkan role berkelompok dengan gudang lebih dulu', function () {
    $this->actingAs(adminPengguna())
        ->get('/admin/users/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->where('roleGroups.0.label', 'Gudang')
            ->where('roleGroups.0.roles.0.value', 'warehouse')
            ->where('roleGroups.0.roles.1.value', 'spv-gudang')
            ->where('roleGroups.0.roles.2.value', 'manager-repacking')
        );
});

it('memberi label Freelancer Gudang pada role warehouse', function () {
    $label = collect(PilihanRole::kelompok())
        ->flatMap(fn (array $grup) => $grup['roles'])
        ->pluck('label', 'value');

    expect($label['warehouse'])->toBe('Freelancer Gudang')
        ->and($label['spv-gudang'])->toBe('SPV Gudang')
        ->and($label['manager-repacking'])->toBe('Manager Repacking (PJ Gudang)');
});

it('tidak menawarkan role distribusi yang tidak dipakai lagi', function () {
    expect(nilaiRoleTampil(PilihanRole::kelompok()))
        ->not->toContain(RoleEnum::DISTRIBUSI->value);

    $this->actingAs(adminPengguna())
        ->get('/admin/users/create')
        ->assertOk()
        ->assertDontSee('Pilih Distribusi', false);
});

it('menampilkan seluruh role yang masih dipakai tepat satu kali', function () {
    $nilai = nilaiRoleTampil(PilihanRole::kelompok());

    expect($nilai)->toBe([
        'warehouse',
        'spv-gudang',
        'manager-repacking',
        'manager',
        'supervisor',
        'courier',
        'customer-service',
        'super-admin',
    ])->and(array_unique($nilai))->toHaveCount(count($nilai));
});

it('akun lama ber-role distribusi tetap melihat role-nya saat diubah', function () {
    $admin = adminPengguna();
    $akun = User::factory()->create(['is_active' => true, 'name' => 'Akun Distribusi Lama']);
    $akun->assignRole(RoleEnum::DISTRIBUSI->value);

    $this->actingAs($admin)
        ->get('/admin/users/'.$akun->id.'/edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->where('roleGroups.3.label', 'Lainnya')
            ->where('roleGroups.3.roles.0.value', 'distribusi')
        );
});
