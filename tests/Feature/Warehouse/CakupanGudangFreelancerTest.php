<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Support\CakupanGudang;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan gudang untuk freelancer (07/11/2026). Dulu hanya SPV yang dibatasi, sehingga
 * freelancer Semarang dan Boyolali saling melihat kerdus dan resi gudang lain. Sekarang
 * freelancer murni memakai aturan yang sama dengan SPV: gudang sendiri + yang belum
 * berlabel. Manager Gudang (warehouse + manager-repacking) dan super-admin tetap melihat
 * semua gudang.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->jenisA5 = (JenisQuran::where('kode_jenis', 'A5')->first()
        ?? JenisQuran::factory()->a5()->create())->id;

    $this->akun = function (array $peran, ?Gudang $gudang = null): User {
        $akun = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $akun->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $akun->fresh();
    };

    $this->kerdus = function (?Gudang $gudang): PackingBox {
        $tugas = DailyPackingTask::factory()->create([
            'user_id' => ($this->akun)([RoleEnum::WAREHOUSE->value], $gudang)->id,
            'gudang_id' => $gudang?->id,
            'tanggal_tugas' => today(),
        ]);

        return PackingBox::factory()->filling()->create([
            'daily_packing_task_id' => $tugas->id,
            'gudang_id' => $gudang?->id,
            'jenis_quran_id' => $this->jenisA5,
        ]);
    };

    $this->kerdusSmg = ($this->kerdus)($this->smg);
    $this->kerdusBtl = ($this->kerdus)($this->btl);
    $this->kerdusTanpaGudang = ($this->kerdus)(null);

    $packing = StatusPengiriman::where('slug', 'packing')->value('id');
    $this->resiSmg = Pengiriman::factory()->create(['status_id' => $packing, 'gudang_id' => $this->smg->id]);
    $this->resiBtl = Pengiriman::factory()->create(['status_id' => $packing, 'gudang_id' => $this->btl->id]);
    $this->resiTanpaGudang = Pengiriman::factory()->create(['status_id' => $packing, 'gudang_id' => null]);
});

/** @return array<int, string> */
function kerdusTerlihatFreelancer(User $akun): array
{
    return collect(test()->actingAs($akun)->get('/admin/box-tracking')->assertOk()->viewData('page')['props']['boxes']['data'])
        ->pluck('kode_kerdus')
        ->sort()
        ->values()
        ->all();
}

/** @return array<int, string> */
function resiTerlihatFreelancer(User $akun): array
{
    return collect(test()->actingAs($akun)->get(route('admin.pengiriman.index', ['tahap' => 'semua']))->assertOk()->viewData('page')['props']['pengiriman']['data'])
        ->pluck('no_resi')
        ->sort()
        ->values()
        ->all();
}

/** @param  array<int, Model>  $baris */
function urutKode(array $baris, string $kolom): array
{
    return collect($baris)->pluck($kolom)->sort()->values()->all();
}

it('freelancer Boyolali hanya melihat kerdus Boyolali dan yang belum berlabel', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->btl);

    expect(kerdusTerlihatFreelancer($freelancer))->toBe(urutKode([$this->kerdusBtl, $this->kerdusTanpaGudang], 'kode_kerdus'))
        ->and(test()->actingAs($freelancer)->get('/admin/box-tracking')->viewData('page')['props']['cakupanGudang'])->toBe('Gudang Boyolali');
});

it('freelancer Semarang tidak melihat kerdus Boyolali', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg);

    expect(kerdusTerlihatFreelancer($freelancer))->not->toContain($this->kerdusBtl->kode_kerdus)
        ->toContain($this->kerdusSmg->kode_kerdus);
});

it('freelancer hanya melihat resi gudangnya dan yang belum berlabel di Data Resi', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg);

    expect(resiTerlihatFreelancer($freelancer))->toBe(urutKode([$this->resiSmg, $this->resiTanpaGudang], 'no_resi'));
});

it('freelancer yang belum ditempatkan hanya melihat data yang belum berlabel', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value]);

    expect(kerdusTerlihatFreelancer($freelancer))->toBe([$this->kerdusTanpaGudang->kode_kerdus])
        ->and(resiTerlihatFreelancer($freelancer))->toBe([$this->resiTanpaGudang->no_resi])
        ->and(CakupanGudang::belumDitempatkan($freelancer))->toBeTrue();
});

it('freelancer tidak bisa memindai kerdus gudang lain di Box Scanner', function () {
    $this->actingAs(($this->akun)([RoleEnum::WAREHOUSE->value], $this->smg))
        ->postJson('/admin/warehouse/box-scanner/scan', ['qr_code' => $this->kerdusBtl->kode_kerdus])
        ->assertForbidden();
});

it('Manager Gudang dan super-admin tetap melihat kerdus semua gudang', function (array $peran) {
    $akun = ($this->akun)($peran);

    expect(CakupanGudang::berlaku($akun))->toBeFalse()
        ->and(kerdusTerlihatFreelancer($akun))->toBe(urutKode([$this->kerdusSmg, $this->kerdusBtl, $this->kerdusTanpaGudang], 'kode_kerdus'));
})->with([
    'Manager Gudang' => [[RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]],
    'super-admin' => [[RoleEnum::SUPER_ADMIN->value]],
]);

it('cakupan berlaku untuk SPV dan freelancer murni saja', function (array $peran, bool $dibatasi) {
    expect(CakupanGudang::berlaku(($this->akun)($peran, $this->smg)))->toBe($dibatasi);
})->with([
    'freelancer' => [[RoleEnum::WAREHOUSE->value], true],
    'SPV' => [[RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], true],
    'Manager Gudang' => [[RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value], false],
    'customer service' => [[RoleEnum::CUSTOMER_SERVICE->value], false],
]);

it('angka statistik Daftar Kerdus hanya menghitung kerdus dalam cakupan', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->btl);
    $this->kerdusSmg->update(['jumlah_terisi' => 7]);
    $this->kerdusBtl->update(['jumlah_terisi' => 3]);
    $this->kerdusTanpaGudang->update(['jumlah_terisi' => 0]);

    $stats = $this->actingAs($freelancer)->get('/admin/box-tracking')->viewData('page')['props']['stats'];

    expect($stats['total_boxes'])->toBe(2)
        ->and($stats['filling_boxes'])->toBe(2)
        ->and((int) $stats['total_items_packed'])->toBe(3);
});

it('freelancer tidak bisa membuka detail kerdus gudang lain lewat URL', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->btl);

    $this->actingAs($freelancer)->get(route('admin.box-tracking.show', $this->kerdusSmg))->assertForbidden();
    $this->actingAs($freelancer)->get(route('admin.box-tracking.show', $this->kerdusBtl))->assertOk();
});

it('pencarian kode kerdus tidak menemukan kerdus gudang lain', function () {
    $freelancer = ($this->akun)([RoleEnum::WAREHOUSE->value], $this->btl);

    $this->actingAs($freelancer)
        ->postJson(route('admin.box-tracking.search'), ['kode_kerdus' => $this->kerdusSmg->kode_kerdus])
        ->assertNotFound();
    $this->actingAs($freelancer)
        ->postJson(route('admin.box-tracking.search'), ['kode_kerdus' => $this->kerdusBtl->kode_kerdus])
        ->assertOk();
});
