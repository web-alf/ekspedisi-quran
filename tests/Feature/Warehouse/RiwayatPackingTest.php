<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Riwayat Packing di halaman Proses Packing (29/10/2026).
 *
 * Halaman ini dulu hanya memuat tugas HARI INI milik akun yang login: tanpa tugas hari ini
 * isinya pesan kosong, dan tugas yang selesai dialihkan ke dashboard — riwayat hari-hari
 * sebelumnya tidak pernah tampil. Kini selalu ada seksi riwayat, dicakup di server:
 * freelancer tugasnya sendiri, SPV gudangnya (+ tugas belum berlabel), PJ & super-admin semua.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');
    $this->a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();

    $this->buatAkun = function (array $peran, ?Gudang $gudang = null): User {
        $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $user->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    $this->freelancerSmg = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);
    $this->freelancerBtl = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->btl);

    $this->tugasSmg = DailyPackingTask::factory()->create([
        'user_id' => $this->freelancerSmg->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today()->subDay(),
        'total_target' => 40,
        'total_selesai' => 40,
        'status' => DailyPackingTask::STATUS_COMPLETED,
        'jenis_quran_id' => $this->a5->id,
        'terjemahan' => true,
    ]);
    PackingBox::factory()->sealed()->count(2)->create([
        'daily_packing_task_id' => $this->tugasSmg->id,
        'gudang_id' => $this->smg->id,
        'jenis_quran_id' => $this->a5->id,
    ]);

    $this->tugasBtl = DailyPackingTask::factory()->create([
        'user_id' => $this->freelancerBtl->id,
        'gudang_id' => $this->btl->id,
        'tanggal_tugas' => today()->subDays(2),
    ]);
});

/**
 * @return array<int, int> id tugas di seksi riwayat
 */
function riwayatPackingDilihat(User $akun): array
{
    return collect(test()->actingAs($akun)->get('/admin/warehouse/packing')
        ->assertOk()
        ->viewData('page')['props']['riwayatPacking'])
        ->pluck('id')
        ->all();
}

it('menampilkan riwayat walau freelancer tidak punya tugas hari ini', function () {
    $props = $this->actingAs($this->freelancerSmg)->get('/admin/warehouse/packing')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['noTaskMessage'])->toContain('Tidak ada tugas packing')
        ->and($props['riwayatSemuaStaf'])->toBeFalse()
        ->and($props['riwayatPacking'])->toHaveCount(1)
        ->and($props['riwayatPacking'][0])->toMatchArray([
            'id' => $this->tugasSmg->id,
            'gudang' => $this->smg->nama,
            'jenis' => 'A5 Terjemahan',
            'target' => 40,
            'selesai' => 40,
            'kerdus' => 2,
            'status' => 'completed',
            'hari_ini' => false,
        ]);
});

it('freelancer hanya melihat tugasnya sendiri, tidak tugas freelancer lain', function () {
    expect(riwayatPackingDilihat($this->freelancerSmg))->toBe([$this->tugasSmg->id])
        ->and(riwayatPackingDilihat($this->freelancerBtl))->toBe([$this->tugasBtl->id]);
});

it('freelancer satu gudang pun tidak melihat tugas rekan segudangnya', function () {
    $rekan = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);
    $tugasRekan = DailyPackingTask::factory()->create([
        'user_id' => $rekan->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today()->subDay(),
    ]);

    expect(riwayatPackingDilihat($this->freelancerSmg))->not->toContain($tugasRekan->id)
        ->and(riwayatPackingDilihat($rekan))->toBe([$tugasRekan->id]);
});

it('SPV melihat tugas semua petugas di gudangnya dan yang belum berlabel, bukan gudang lain', function () {
    $spvSmg = ($this->buatAkun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $tanpaGudang = DailyPackingTask::factory()->create([
        'user_id' => ($this->buatAkun)([RoleEnum::WAREHOUSE->value])->id,
        'gudang_id' => null,
        'tanggal_tugas' => today()->subDays(3),
    ]);

    $props = $this->actingAs($spvSmg)->get('/admin/warehouse/packing')
        ->viewData('page')['props'];

    expect(collect($props['riwayatPacking'])->pluck('id')->all())->toBe([$this->tugasSmg->id, $tanpaGudang->id])
        ->and($props['riwayatSemuaStaf'])->toBeTrue()
        ->and($props['riwayatPacking'][0]['petugas'])->toBe($this->freelancerSmg->name);
});

it('PJ dan super-admin melihat riwayat semua gudang', function (array $peran) {
    $akun = ($this->buatAkun)($peran);

    expect(riwayatPackingDilihat($akun))->toBe([$this->tugasSmg->id, $this->tugasBtl->id]);
})->with([
    'PJ (manager-repacking + warehouse)' => [[RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value]],
    'super-admin' => [[RoleEnum::SUPER_ADMIN->value]],
]);

it('tugas hari ini yang sudah selesai menampilkan ringkasan, bukan dialihkan ke dashboard', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->freelancerSmg->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today(),
        'total_target' => 20,
        'total_selesai' => 20,
        'status' => DailyPackingTask::STATUS_COMPLETED,
    ]);

    $props = $this->actingAs($this->freelancerSmg)->get('/admin/warehouse/packing')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['tugasSelesai'])->toBeTrue()
        ->and($props['task'])->toBeNull()
        ->and($props['noTaskMessage'])->toContain('20 dari 20 mushaf')
        ->and($props['riwayatPacking'][0]['hari_ini'])->toBeTrue()
        ->and($props['riwayatPacking'])->toHaveCount(2);
});

it('membatasi riwayat ke sepuluh tugas terbaru', function () {
    foreach (range(2, 13) as $hariLalu) {
        DailyPackingTask::factory()->create([
            'user_id' => $this->freelancerSmg->id,
            'gudang_id' => $this->smg->id,
            'tanggal_tugas' => today()->subDays($hariLalu),
        ]);
    }

    $riwayat = riwayatPackingDilihat($this->freelancerSmg);

    expect($riwayat)->toHaveCount(10)
        ->and($riwayat[0])->toBe($this->tugasSmg->id);
});
