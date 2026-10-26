<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: penugasan packing hanya untuk FREELANCER MURNI (26/10/2026).
 *
 * Akun SPV dan PJ memegang izin `warehouse.packing.scan` ("mereka harus bisa
 * mengerjakan"), sehingga penyaring berbasis izin menugasi mereka setiap pagi. Di
 * produksi itu membuat target gudang Semarang tercatat 800 padahal 8 freelancer × 80 =
 * 640. Keputusan pemilik: tugas otomatis untuk freelancer; PJ/SPV menugasi manual.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->hariKerja = today()->isWeekend() ? today()->next(Carbon::MONDAY) : today();
});

function fmAkun(array $peran, ?Gudang $gudang = null): User
{
    $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $user->syncRoles($peran);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

it('menugasi freelancer murni dan melewati akun yang memegang role pengawas', function () {
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $pj = fmAkun([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $spv = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->smg);

    app(PackingAssignmentService::class)->runDailyAssignment($this->hariKerja);

    $penerima = DailyPackingTask::whereDate('tanggal_tugas', $this->hariKerja)->pluck('user_id');

    expect($penerima)->toContain($freelancer->id)
        ->and($penerima)->not->toContain($pj->id)
        ->and($penerima)->not->toContain($spv->id);
});

it('mengenali freelancer murni dan menolak akun pengawas', function () {
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $pj = fmAkun([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $tanpaGudang = fmAkun([RoleEnum::CUSTOMER_SERVICE->value]);

    expect($freelancer->isFreelancerMurni())->toBeTrue()
        ->and($pj->isFreelancerMurni())->toBeFalse()
        ->and($tanpaGudang->isFreelancerMurni())->toBeFalse()
        ->and(User::query()->freelancerMurni()->pluck('id')->all())->toBe([$freelancer->id]);
});

it('menolak penugasan manual ke akun SPV atau PJ', function () {
    $pj = fmAkun([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]);
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);

    $this->actingAs($pj)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $pj->id, 'target' => 40])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(DailyPackingTask::where('user_id', $pj->id)->count())->toBe(0);

    // Freelancer tetap bisa ditugasi seperti biasa.
    $this->actingAs($pj)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $freelancer->id, 'target' => 40])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect(DailyPackingTask::where('user_id', $freelancer->id)->value('total_target'))->toBe(40);
});

it('modal Assign Target tidak lagi menawarkan akun PJ/SPV', function () {
    $spv = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->smg);
    $pj = fmAkun([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);

    $props = $this->actingAs($spv)->get('/admin/supervisor/warehouse-monitor')
        ->assertSuccessful()
        ->viewData('page')['props'];

    $kandidat = collect($props['warehouseUsers'] ?? $props['staf'] ?? [])->pluck('id')->all();

    expect($kandidat)->toContain($freelancer->id)
        ->and($kandidat)->not->toContain($pj->id)
        ->and($kandidat)->not->toContain($spv->id);
});
