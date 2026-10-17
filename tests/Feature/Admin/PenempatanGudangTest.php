<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * Penempatan Gudang (16/10/2026): super-admin & manager gudang memindahkan akun SPV/staf
 * ke Semarang/Boyolali; SPV tidak boleh (walau ikut memegang role manager-repacking).
 * Tugas harian otomatis membawa gudang staf yang ditugasi.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => PermissionEnum::GUDANG_PENEMPATAN->value, 'guard_name' => 'web']);

    foreach ([RoleEnum::SUPER_ADMIN, RoleEnum::MANAGER_REPACKING, RoleEnum::SPV_GUDANG, RoleEnum::WAREHOUSE, RoleEnum::MANAGER, RoleEnum::CUSTOMER_SERVICE] as $peran) {
        Role::firstOrCreate(['name' => $peran->value, 'guard_name' => 'web']);
    }

    // Sama seperti migrasi 2026_10_16_230000.
    foreach ([RoleEnum::SUPER_ADMIN, RoleEnum::MANAGER_REPACKING] as $peran) {
        Role::findByName($peran->value)->givePermissionTo(PermissionEnum::GUDANG_PENEMPATAN->value);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);
});

function pgAkun(array $peran, array $atribut = []): User
{
    $user = User::factory()->create($atribut + ['is_active' => true]);
    $user->syncRoles($peran);

    return $user;
}

it('manager gudang memindahkan SPV dan staf ke gudang lain', function () {
    $pj = pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value]);
    $spv = pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], ['gudang_id' => $this->smg->id]);
    $staf = pgAkun([RoleEnum::WAREHOUSE->value]);

    $this->actingAs($pj)->patch(route('admin.penempatan-gudang.update', $spv), ['gudang_id' => $this->btl->id])
        ->assertRedirect()->assertSessionHas('success', fn ($p) => str_contains($p, 'Gudang Semarang ke Gudang Boyolali'));
    $this->actingAs($pj)->patch(route('admin.penempatan-gudang.update', $staf), ['gudang_id' => $this->smg->id])->assertRedirect();

    expect($spv->fresh()->gudang_id)->toBe($this->btl->id)
        ->and($staf->fresh()->gudang_id)->toBe($this->smg->id);
});

it('super-admin boleh memindahkan, termasuk melepas dari gudang', function () {
    $admin = pgAkun([RoleEnum::SUPER_ADMIN->value]);
    $staf = pgAkun([RoleEnum::WAREHOUSE->value], ['gudang_id' => $this->smg->id]);

    $this->actingAs($admin)->get(route('admin.penempatan-gudang.index'))->assertOk();
    $this->actingAs($admin)->patch(route('admin.penempatan-gudang.update', $staf), ['gudang_id' => null])->assertRedirect();

    expect($staf->fresh()->gudang_id)->toBeNull();
});

it('SPV tidak boleh membuka maupun memindahkan, walau memegang role manager-repacking', function () {
    $spv = pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], ['gudang_id' => $this->smg->id]);
    $spvLain = pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], ['gudang_id' => $this->btl->id]);

    $this->actingAs($spv)->get(route('admin.penempatan-gudang.index'))->assertForbidden();
    $this->actingAs($spv)->patch(route('admin.penempatan-gudang.update', $spv), ['gudang_id' => $this->btl->id])->assertForbidden();
    $this->actingAs($spv)->patch(route('admin.penempatan-gudang.update', $spvLain), ['gudang_id' => $this->smg->id])->assertForbidden();

    expect($spv->fresh()->gudang_id)->toBe($this->smg->id)
        ->and($spvLain->fresh()->gudang_id)->toBe($this->btl->id);
});

it('peran lain tanpa izin ditolak', function (string $peran) {
    $orang = pgAkun([$peran]);
    $staf = pgAkun([RoleEnum::WAREHOUSE->value]);

    $this->actingAs($orang)->get(route('admin.penempatan-gudang.index'))->assertForbidden();
    $this->actingAs($orang)->patch(route('admin.penempatan-gudang.update', $staf), ['gudang_id' => $this->smg->id])->assertForbidden();

    expect($staf->fresh()->gudang_id)->toBeNull();
})->with([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER->value, RoleEnum::CUSTOMER_SERVICE->value]);

it('hanya akun SPV/staf gudang yang bisa ditempatkan', function () {
    $pj = pgAkun([RoleEnum::MANAGER_REPACKING->value]);
    $cs = pgAkun([RoleEnum::CUSTOMER_SERVICE->value]);

    $this->actingAs($pj)->patch(route('admin.penempatan-gudang.update', $cs), ['gudang_id' => $this->smg->id])->assertNotFound();

    expect($cs->fresh()->gudang_id)->toBeNull();
});

it('menolak gudang yang tidak ada atau tidak aktif', function () {
    $pj = pgAkun([RoleEnum::MANAGER_REPACKING->value]);
    $staf = pgAkun([RoleEnum::WAREHOUSE->value]);
    $mati = Gudang::create(['kode' => 'XXX', 'nama' => 'Gudang Tutup', 'is_active' => false]);

    foreach ([999999, $mati->id] as $id) {
        $this->actingAs($pj)->patch(route('admin.penempatan-gudang.update', $staf), ['gudang_id' => $id])
            ->assertSessionHasErrors('gudang_id');
    }

    expect($staf->fresh()->gudang_id)->toBeNull();
});

it('daftar memuat SPV dan staf saja, SPV di atas', function () {
    $pj = pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value], ['name' => 'Manager PJ']);
    pgAkun([RoleEnum::WAREHOUSE->value], ['name' => 'Aisyah Staf']);
    pgAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], ['name' => 'Zaki SPV', 'gudang_id' => $this->smg->id]);
    pgAkun([RoleEnum::CUSTOMER_SERVICE->value], ['name' => 'Bukan Gudang']);

    $props = $this->actingAs($pj)->get(route('admin.penempatan-gudang.index'))->assertOk()->viewData('page')['props'];

    expect(collect($props['akun'])->pluck('nama')->all())->toBe(['Zaki SPV', 'Manager PJ', 'Aisyah Staf'])
        ->and(collect($props['akun'])->pluck('peran')->all())->toBe(['SPV Gudang', 'Manager Gudang', 'Staf Gudang'])
        ->and($props['akun'][0])->toMatchArray(['gudang' => 'Gudang Semarang'])
        ->and($props['gudangList'])->toHaveCount(2);
});

it('tugas harian otomatis membawa gudang staf yang ditugasi', function () {
    $staf = pgAkun([RoleEnum::WAREHOUSE->value], ['gudang_id' => $this->btl->id]);
    $tanpaGudang = pgAkun([RoleEnum::WAREHOUSE->value]);

    $tugas = DailyPackingTask::factory()->create(['user_id' => $staf->id, 'gudang_id' => null]);
    $tugasLain = DailyPackingTask::factory()->create(['user_id' => $tanpaGudang->id, 'gudang_id' => null]);
    // Gudang yang diisi eksplisit oleh SPV/PJ tetap dihormati.
    $tugasEksplisit = DailyPackingTask::factory()->create(['user_id' => $staf->id, 'gudang_id' => $this->smg->id]);

    expect($tugas->fresh()->gudang_id)->toBe($this->btl->id)
        ->and($tugasLain->fresh()->gudang_id)->toBeNull()
        ->and($tugasEksplisit->fresh()->gudang_id)->toBe($this->smg->id)
        ->and($tugas->fresh()->gudangKerdus())->toBe($this->btl->id);
});

it('pindah gudang tidak mengubah gudang tugas yang sudah ada', function () {
    $pj = pgAkun([RoleEnum::MANAGER_REPACKING->value]);
    $staf = pgAkun([RoleEnum::WAREHOUSE->value], ['gudang_id' => $this->smg->id]);
    $tugas = DailyPackingTask::factory()->create(['user_id' => $staf->id, 'gudang_id' => null]);

    $this->actingAs($pj)->patch(route('admin.penempatan-gudang.update', $staf), ['gudang_id' => $this->btl->id]);

    expect($tugas->fresh()->gudang_id)->toBe($this->smg->id);
});
