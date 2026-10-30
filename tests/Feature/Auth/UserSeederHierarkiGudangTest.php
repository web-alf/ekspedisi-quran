<?php

use App\Enums\RoleEnum;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Akun bawaan UserSeeder mengikuti hierarki gudang (30/10/2026): Manager Gudang (gudang@,
 * tanpa gudang) → SPV per gudang → freelancer. Gudang tidak punya Supervisor: akun
 * supervisor@ lama dinonaktifkan dan dicabut perannya.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function akunSeeder(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('membuat akun gudang dengan peran dan gudang sesuai hierarki', function (string $email, array $peran, ?string $kodeGudang) {
    $this->seed(UserSeeder::class);

    $akun = akunSeeder($email);

    expect($akun->getRoleNames()->sort()->values()->all())->toBe(collect($peran)->sort()->values()->all())
        ->and($akun->gudang_id)->toBe($kodeGudang ? Gudang::byKode($kodeGudang)->id : null)
        ->and($akun->is_active)->toBeTrue();
})->with([
    'Manager Gudang' => ['gudang@ekspedisiquran.com', [RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], null],
    'SPV Semarang' => ['spv.semarang@ekspedisiquran.com', [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], 'SMG'],
    'SPV Boyolali' => ['spv.boyolali@ekspedisiquran.com', [RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], 'BTL'],
    'Freelancer Semarang' => ['freelancer.semarang@ekspedisiquran.com', [RoleEnum::WAREHOUSE->value], 'SMG'],
    'Freelancer Boyolali' => ['freelancer.boyolali@ekspedisiquran.com', [RoleEnum::WAREHOUSE->value], 'BTL'],
]);

it('Manager Gudang melihat semua gudang, SPV dan freelancer hanya gudangnya', function () {
    $this->seed(UserSeeder::class);

    expect(akunSeeder('gudang@ekspedisiquran.com')->cakupanGudangStok())->toBeNull()
        ->and(akunSeeder('spv.semarang@ekspedisiquran.com')->cakupanGudangStok())->toBe(Gudang::byKode('SMG')->id)
        ->and(akunSeeder('spv.boyolali@ekspedisiquran.com')->cakupanGudangStok())->toBe(Gudang::byKode('BTL')->id)
        ->and(akunSeeder('freelancer.semarang@ekspedisiquran.com')->isFreelancerMurni())->toBeTrue()
        ->and(akunSeeder('gudang@ekspedisiquran.com')->isFreelancerMurni())->toBeFalse();
});

it('membetulkan peran yang melenceng dan menonaktifkan supervisor@ lama', function () {
    // Keadaan DB lokal sebelum 30/10/2026: gudang@ hanya freelancer bergudang Semarang,
    // supervisor@ memegang Supervisor + SPV Gudang.
    $semarang = Gudang::byKode('SMG');
    $gudangLama = User::factory()->create(['email' => 'gudang@ekspedisiquran.com', 'gudang_id' => $semarang->id]);
    $gudangLama->syncRoles([RoleEnum::WAREHOUSE->value]);
    $supervisorLama = User::factory()->create(['email' => 'supervisor@ekspedisiquran.com', 'gudang_id' => $semarang->id, 'is_active' => true]);
    $supervisorLama->syncRoles([RoleEnum::SUPERVISOR->value, RoleEnum::SPV_GUDANG->value]);

    $this->seed(UserSeeder::class);

    $supervisor = akunSeeder('supervisor@ekspedisiquran.com');

    expect(akunSeeder('gudang@ekspedisiquran.com')->getRoleNames()->sort()->values()->all())
        ->toBe([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value])
        ->and(akunSeeder('gudang@ekspedisiquran.com')->gudang_id)->toBeNull()
        ->and($supervisor->getRoleNames()->all())->toBe([])
        ->and($supervisor->gudang_id)->toBeNull()
        ->and($supervisor->is_active)->toBeFalse();
});

it('tidak lagi membuat akun supervisor@ di database baru dan aman dijalankan dua kali', function () {
    $this->seed(UserSeeder::class);
    $jumlah = User::count();
    $this->seed(UserSeeder::class);

    expect(User::where('email', 'supervisor@ekspedisiquran.com')->exists())->toBeFalse()
        ->and(User::count())->toBe($jumlah)
        ->and(akunSeeder('spv.semarang@ekspedisiquran.com')->getRoleNames())->toHaveCount(2);
});
