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
 * Cakupan: hierarki penugasan gudang (27/10/2026).
 *
 *  - Tugas packing OTOMATIS (cron) hanya untuk freelancer murni, supaya target gudang
 *    tidak menggelembung oleh tugas yang tak dikerjakan sebagai pekerja packing;
 *  - PJ (manager gudang, mis. gudang@) membagi beban kerja ke SPV Semarang/Boyolali,
 *    lalu SPV menugasi freelancer-nya;
 *  - freelancer murni = role `warehouse` tanpa spv-gudang/manager-repacking, jadi akun PJ
 *    tidak pernah muncul sebagai pekerja packing.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->hariKerja = today()->isWeekend() ? today()->next(Carbon::MONDAY) : today();
});

function fmAkun(array $peran, ?Gudang $gudang = null, ?string $nama = null): User
{
    $atribut = ['is_active' => true, 'gudang_id' => $gudang?->id];

    if ($nama !== null) {
        $atribut['name'] = $nama;
    }

    $user = User::factory()->create($atribut);
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

it('PJ bisa menugasi SPV gudang, dan tugasnya bertagih gudang SPV itu', function () {
    $pj = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value], $this->smg, 'Manager Gudang');
    $spvBtl = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->btl, 'SPV Boyolali');

    $this->actingAs($pj)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $spvBtl->id, 'target' => 90])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $tugas = DailyPackingTask::where('user_id', $spvBtl->id)->first();

    expect($tugas)->not->toBeNull()
        ->and($tugas->total_target)->toBe(90)
        // Tanpa tagihan gudang, SPV tidak akan melihat tugasnya di Monitor yang ter-scope.
        ->and($tugas->gudang_id)->toBe($this->btl->id);
});

it('PJ tetap bisa menugasi freelancer, tetapi SPV tidak bisa menugasi sesama SPV', function () {
    $pj = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value], $this->smg);
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $spvSmg = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->smg);
    $spvBtl = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->btl);

    $this->actingAs($pj)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $freelancer->id, 'target' => 40])
        ->assertSuccessful();

    // Beban kerja SPV datang dari PJ, bukan dari SPV lain.
    $this->actingAs($spvSmg)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $spvBtl->id, 'target' => 40])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    expect(DailyPackingTask::where('user_id', $spvBtl->id)->count())->toBe(0);
});

it('menolak penugasan ke akun PJ sendiri', function () {
    $pj = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value], $this->smg);

    $this->actingAs($pj)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $pj->id, 'target' => 40])
        ->assertStatus(422);

    expect(DailyPackingTask::where('user_id', $pj->id)->count())->toBe(0);
});

it('modal Assign Target PJ memuat SPV + freelancer, modal SPV hanya freelancer', function () {
    $pj = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value], $this->smg);
    $spvSmg = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->smg);
    $spvBtl = fmAkun([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value], $this->btl);
    $freelancer = fmAkun([RoleEnum::WAREHOUSE->value], $this->smg);

    $propsPj = $this->actingAs($pj)->get('/admin/supervisor/warehouse-monitor')
        ->assertSuccessful()->viewData('page')['props'];
    $kandidatPj = collect($propsPj['warehouseUsers']);

    expect($kandidatPj->pluck('id')->all())->toContain($spvSmg->id)
        ->and($kandidatPj->pluck('id')->all())->toContain($spvBtl->id)
        ->and($kandidatPj->pluck('id')->all())->toContain($freelancer->id)
        ->and($kandidatPj->pluck('id')->all())->not->toContain($pj->id)
        // SPV tampil lebih dulu: PJ melihat beban kerja per gudang di atas.
        ->and($kandidatPj->first()['adalah_spv'])->toBeTrue();

    $propsSpv = $this->actingAs($spvSmg)->get('/admin/supervisor/warehouse-monitor')
        ->assertSuccessful()->viewData('page')['props'];
    $kandidatSpv = collect($propsSpv['warehouseUsers'])->pluck('id')->all();

    expect($kandidatSpv)->toContain($freelancer->id)
        ->and($kandidatSpv)->not->toContain($spvBtl->id)
        ->and($kandidatSpv)->not->toContain($spvSmg->id);
});

it('menampilkan akun PJ sebagai Manager Repacking, bukan freelancer', function () {
    $pj = fmAkun([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], $this->smg, 'Manager Gudang');

    expect($pj->role)->toBe(RoleEnum::MANAGER_REPACKING->value)
        ->and($pj->role_display)->toBe('Manager Repacking (PJ Gudang)')
        ->and($pj->isFreelancerMurni())->toBeFalse();
});
