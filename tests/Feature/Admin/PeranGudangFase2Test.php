<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Fase 2 rombakan gudang (2026-10-13): dua gudang fisik — Semarang & Boyolali
 * — dengan jenjang kepemimpinan baru.
 *
 * Struktur yang dikeputuskan pemilik sistem:
 *   Manager Gudang (PJ Repacking)  → melihat informasi LENGKAP kedua gudang,
 *                                    boleh assign kerjaan packing ke freelancer
 *   SPV Gudang (Semarang/Boyolali) → eksekutor per gudang; yang assign kerjaan
 *                                    ke freelancer warehouse
 *   Freelancer (role warehouse)    → pekerja penerima tugas, tanpa hak assign
 *
 * Yang dikunci di sini: peran baru punya izin penugasan, freelancer TIDAK, dan
 * tugas yang di-assign SPV mewarisi gudang penugasanya — pondasi Fase 3 (stok
 * mushaf per gudang + transfer antar gudang).
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function akunGudang(string $role, ?Gudang $gudang = null): User
{
    $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('memiliki dua gudang: Semarang dan Boyolali', function () {
    expect(Gudang::count())->toBe(2)
        ->and(Gudang::pluck('kode')->sort()->values()->all())->toBe(['BTL', 'SMG'])
        ->and(Gudang::byKode('SMG')->nama)->toBe('Gudang Semarang')
        ->and(Gudang::byKode('BTL')->nama)->toBe('Gudang Boyolali');
});

it('memberi izin penugasan kepada manager repacking dan SPV gudang, bukan freelancer', function () {
    foreach ([RoleEnum::MANAGER_REPACKING, RoleEnum::SPV_GUDANG] as $role) {
        $akun = akunGudang($role->value);

        expect($akun->hasPermissionTo(PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value))->toBeTrue()
            ->and($akun->hasPermissionTo(PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value))->toBeTrue()
            // Operasional harian tetap harus bisa mereka kerjakan sendiri.
            ->and($akun->hasPermissionTo(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))->toBeTrue()
            ->and($akun->hasPermissionTo(PermissionEnum::DONATUR_READ->value))->toBeFalse();
    }

    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);

    expect($freelancer->hasPermissionTo(PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value))->toBeFalse()
        ->and($freelancer->hasPermissionTo(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))->toBeTrue();
});

it('menandai tugas yang di-assign SPV dengan gudang SPV itu', function () {
    $semarang = Gudang::byKode('SMG');
    $spv = akunGudang(RoleEnum::SPV_GUDANG->value, $semarang);
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);

    $this->actingAs($spv)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 50,
        ])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $tugas = DailyPackingTask::where('user_id', $freelancer->id)->whereDate('tanggal_tugas', today())->first();

    expect($tugas)->not->toBeNull()
        ->and($tugas->total_target)->toBe(50)
        ->and($tugas->gudang_id)->toBe($semarang->id)
        ->and($tugas->gudang->nama)->toBe('Gudang Semarang');
});

it('menyebut gudang di pesan penugasan SPV', function () {
    $boyolali = Gudang::byKode('BTL');
    $spv = akunGudang(RoleEnum::SPV_GUDANG->value, $boyolali);
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);

    $respons = $this->actingAs($spv)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 30,
        ])
        ->assertSuccessful();

    expect($respons->json('message'))->toContain('Gudang Boyolali');
});

it('tidak menghapus tagihan gudang saat penugasan datang dari akun tanpa gudang', function () {
    // Tugas yang sudah ditandai SPV Semarang tidak boleh "kehilangan" gudangnya
    // hanya karena admin menambah target dari akun yang tidak terpasang gudang.
    $semarang = Gudang::byKode('SMG');
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);
    // Akun penugasan tanpa gudang (mis. admin): kelompok rute supervisor juga
    // menuntut izin monitor, jadi dua-duanya diberikan.
    $admin = User::factory()->create(['is_active' => true]);
    $admin->givePermissionTo([
        PermissionEnum::SUPERVISOR_WAREHOUSE_ASSIGN->value,
        PermissionEnum::SUPERVISOR_WAREHOUSE_MONITOR->value,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $spv = akunGudang(RoleEnum::SPV_GUDANG->value, $semarang);
    $this->actingAs($spv)->postJson(route('admin.supervisor.assign-target'), [
        'user_id' => $freelancer->id,
        'target' => 20,
    ]);

    $this->actingAs($admin)->postJson(route('admin.supervisor.assign-target'), [
        'user_id' => $freelancer->id,
        'target' => 25,
    ]);

    $tugas = DailyPackingTask::where('user_id', $freelancer->id)->whereDate('tanggal_tugas', today())->first();

    expect($tugas->total_target)->toBe(25)
        ->and($tugas->gudang_id)->toBe($semarang->id);
});

it('memberi tugas tanpa tagihan gudang bila penugasa tidak terpasang gudang', function () {
    $managerRepacking = akunGudang(RoleEnum::MANAGER_REPACKING->value);
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);

    $this->actingAs($managerRepacking)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 40,
        ])
        ->assertSuccessful();

    // Manager Repacking tanpa gudang tetap bisa menugaskan; tagihan gudangnya
    // saja yang kosong (dari akun yang terpasang gudang, tugas mewarisi).
    $tugas = DailyPackingTask::where('user_id', $freelancer->id)->whereDate('tanggal_tugas', today())->first();

    expect($tugas->total_target)->toBe(40)
        ->and($tugas->gudang_id)->toBeNull();
});

it('PJ memilih gudang saat assign dan tugas ikut bertagih gudang itu', function () {
    $boyolali = Gudang::byKode('BTL');
    // PJ sebenarnya = manager-repacking TANPA role spv-gudang.
    $pj = User::factory()->create(['is_active' => true]);
    $pj->assignRole(RoleEnum::MANAGER_REPACKING->value);
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($pj->fresh())
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 60,
            'gudang_id' => $boyolali->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.gudang', 'Gudang Boyolali');

    expect(DailyPackingTask::where('user_id', $freelancer->id)->value('gudang_id'))->toBe($boyolali->id);
});

it('daftar penugasan hanya pekerja packing, bukan super-admin/peran lain', function () {
    // Keputusan pemilik sistem: modal Assign Target Harian bukan tempat
    // menugaskan super-admin — hanya role `warehouse` (freelancer pekerja).
    // Dulu penyaringnya pakai izin, sehingga super-admin (pemegang semua izin)
    // ikut tampil dan bisa di-assign kerja packing.
    $pj = User::factory()->create(['is_active' => true]);
    $pj->assignRole(RoleEnum::MANAGER_REPACKING->value);
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);

    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(RoleEnum::SUPER_ADMIN->value);
    $cs = User::factory()->create(['is_active' => true]);
    $cs->assignRole(RoleEnum::CUSTOMER_SERVICE->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($pj->fresh())
        ->get(route('admin.supervisor.warehouse-monitor'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    $nama = collect($props['warehouseUsers'])->pluck('id')->all();

    expect($nama)->toContain($freelancer->id)
        ->and($nama)->not->toContain($superAdmin->id)
        ->and($nama)->not->toContain($cs->id);
});

it('menolak freelancer memakai endpoint penugasan', function () {
    // Freelancer menerima tugas, bukan memberikannya.
    $freelancer = akunGudang(RoleEnum::WAREHOUSE->value);
    $teman = akunGudang(RoleEnum::WAREHOUSE->value);

    $this->actingAs($freelancer)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $teman->id,
            'target' => 10,
        ])
        ->assertForbidden();

    expect(DailyPackingTask::where('user_id', $teman->id)->count())->toBe(0);
});

it('sisi gudang tetap tidak membuka halaman donatur', function () {
    foreach ([RoleEnum::MANAGER_REPACKING, RoleEnum::SPV_GUDANG] as $role) {
        $this->actingAs(akunGudang($role->value))
            ->get(route('admin.donatur.index'))
            ->assertForbidden();
    }
});
