<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\TargetPeriodeGudang;
use App\Models\User;
use App\Services\TargetPeriodeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Target periode per gudang (30/10/2026): Manager Gudang memberi target mingguan/bulanan,
 * progresnya dijumlah dari tugas harian freelancer gudang itu (jatah SPV tidak ikut).
 */
beforeEach(function () {
    // Kamis 12 November 2026: minggu 9–15 Nov, bulan 1–30 Nov.
    Carbon::setTestNow('2026-11-12 10:00:00');

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

    $this->pj = ($this->buatAkun)([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value]);
    $this->spvSmg = ($this->buatAkun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);
    $this->freelancerSmg = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);

    $this->tugas = function (User $user, Gudang $gudang, string $tanggal, int $selesai, ?bool $terjemahan = true): DailyPackingTask {
        return DailyPackingTask::factory()->create([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'tanggal_tugas' => $tanggal,
            'total_target' => 80,
            'total_selesai' => $selesai,
            'jenis_quran_id' => $this->a5->id,
            'terjemahan' => $terjemahan,
        ]);
    };
});

afterEach(fn () => Carbon::setTestNow());

it('Manager Gudang membuat target bulanan; rentangnya dihitung server', function () {
    $this->actingAs($this->pj)
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id,
            'tipe' => 'bulanan',
            'tanggal_acuan' => '2026-11-20',
            'target' => 1200,
            'catatan' => 'Program akhir tahun',
        ])
        ->assertCreated()
        ->assertJsonPath('data.periode', 'November 2026')
        ->assertJsonPath('data.jumlah_hari', 30);

    expect(TargetPeriodeGudang::sole())->toMatchArray([
        'gudang_id' => $this->smg->id,
        'tipe' => 'bulanan',
        'target' => 1200,
        'kunci_jenis' => 'semua',
        'created_by' => $this->pj->id,
    ])->and(TargetPeriodeGudang::sole()->tanggal_mulai->toDateString())->toBe('2026-11-01')
        ->and(TargetPeriodeGudang::sole()->tanggal_selesai->toDateString())->toBe('2026-11-30');
});

it('target mingguan memakai Senin–Minggu dan menyimpan jenis yang dipilih', function () {
    $this->actingAs($this->pj)
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id,
            'tipe' => 'mingguan',
            'tanggal_acuan' => '2026-11-12',
            'jenis' => $this->a5->id.':1',
            'target' => 300,
        ])
        ->assertCreated()
        ->assertJsonPath('data.jenis', 'A5 Terjemahan');

    $target = TargetPeriodeGudang::sole();

    expect($target->tanggal_mulai->toDateString())->toBe('2026-11-09')
        ->and($target->tanggal_selesai->toDateString())->toBe('2026-11-15')
        ->and($target->jenis_quran_id)->toBe($this->a5->id)
        ->and($target->terjemahan)->toBeTrue()
        ->and($target->kunci_jenis)->toBe($this->a5->id.':1');
});

it('menolak SPV dan freelancer menetapkan atau menghapus target', function (string $akun) {
    $target = TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->{$akun})
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id, 'tipe' => 'bulanan', 'tanggal_acuan' => '2026-11-12', 'target' => 100,
        ])
        ->assertForbidden();

    $this->actingAs($this->{$akun})
        ->deleteJson(route('admin.supervisor.target-periode.destroy', $target))
        ->assertForbidden();

    expect(TargetPeriodeGudang::count())->toBe(1);
})->with(['SPV' => ['spvSmg'], 'freelancer' => ['freelancerSmg']]);

it('menolak periode yang sudah lewat dan target ganda untuk periode dan jenis yang sama', function () {
    $this->actingAs($this->pj)
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id, 'tipe' => 'bulanan', 'tanggal_acuan' => '2026-10-15', 'target' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_acuan' => 'sudah lewat']);

    TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id]);

    $this->actingAs($this->pj)
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id, 'tipe' => 'bulanan', 'tanggal_acuan' => '2026-11-01', 'target' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_acuan' => 'sudah punya target']);

    // Jenis berbeda di periode yang sama tetap boleh.
    $this->actingAs($this->pj)
        ->postJson(route('admin.supervisor.target-periode.store'), [
            'gudang_id' => $this->smg->id, 'tipe' => 'bulanan', 'tanggal_acuan' => '2026-11-01', 'target' => 100, 'jenis' => $this->a5->id.':0',
        ])
        ->assertCreated();
});

it('Manager Gudang mengubah dan menghapus target', function () {
    $target = TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id, 'target' => 500]);

    $this->actingAs($this->pj)
        ->putJson(route('admin.supervisor.target-periode.update', $target), [
            'gudang_id' => $this->smg->id, 'tipe' => 'bulanan', 'tanggal_acuan' => '2026-11-01', 'target' => 900,
        ])
        ->assertOk()
        ->assertJsonPath('data.target', 900);

    $this->actingAs($this->pj)
        ->deleteJson(route('admin.supervisor.target-periode.destroy', $target))
        ->assertOk();

    expect(TargetPeriodeGudang::count())->toBe(0);
});

it('menjumlah progres dari tugas freelancer gudang itu di dalam periode saja', function () {
    $target = TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id, 'target' => 1200]);
    $rekan = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);

    ($this->tugas)($this->freelancerSmg, $this->smg, '2026-11-02', 200);
    ($this->tugas)($rekan, $this->smg, '2026-11-11', 230);
    // Tidak dihitung: jatah SPV, gudang lain, dan di luar periode.
    ($this->tugas)($this->spvSmg, $this->smg, '2026-11-11', 999);
    ($this->tugas)(($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->btl), $this->btl, '2026-11-11', 777);
    ($this->tugas)($this->freelancerSmg, $this->smg, '2026-10-31', 555);

    $ringkas = app(TargetPeriodeService::class)->ringkas($target);

    expect($ringkas)->toMatchArray([
        'status' => 'berjalan',
        'dikemas' => 430,
        'persen' => 36,
        'hari_ke' => 12,
        'jumlah_hari' => 30,
        'laju' => 480,
        'selisih_laju' => -50,
        'sisa' => 770,
        'sisa_hari' => 18,
        'perlu_per_hari' => 43,
    ]);
});

it('target berjenis hanya menghitung tugas berjenis dan bervarian sama', function () {
    $target = TargetPeriodeGudang::factory()->mingguan()->create([
        'gudang_id' => $this->smg->id,
        'jenis_quran_id' => $this->a5->id,
        'terjemahan' => true,
        'kunci_jenis' => $this->a5->id.':1',
        'target' => 300,
    ]);

    ($this->tugas)($this->freelancerSmg, $this->smg, '2026-11-10', 190, terjemahan: true);
    ($this->tugas)(($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg), $this->smg, '2026-11-10', 60, terjemahan: false);

    expect(app(TargetPeriodeService::class)->progres($target))->toBe(190);
});

it('SPV melihat target gudangnya di dashboard dan Monitor; Manager Gudang semua gudang', function () {
    // Mingguan dibuat lebih dulu: dashboard tetap menampilkan bulanan di urutan pertama.
    TargetPeriodeGudang::factory()->mingguan()->create(['gudang_id' => $this->smg->id, 'target' => 300]);
    TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id, 'target' => 1200]);
    TargetPeriodeGudang::factory()->create(['gudang_id' => $this->btl->id, 'target' => 1000]);
    // Bulan depan: tampil di Monitor (akan datang), belum di dashboard.
    TargetPeriodeGudang::factory()->create([
        'gudang_id' => $this->smg->id, 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-31', 'target' => 1100,
    ]);

    $dashboardSpv = $this->actingAs($this->spvSmg)->get('/admin/warehouse')->viewData('page')['props']['ringkasanGudang'];
    $monitorSpv = $this->actingAs($this->spvSmg)->get(route('admin.supervisor.warehouse-monitor'))->viewData('page')['props'];

    expect(collect($dashboardSpv['target_periode'])->pluck('target')->all())->toBe([1200, 300])
        ->and(collect($monitorSpv['targetPeriode'])->pluck('target')->all())->toBe([1200, 300, 1100])
        ->and($monitorSpv['bolehAturTargetPeriode'])->toBeFalse();

    $monitorPj = $this->actingAs($this->pj)->get(route('admin.supervisor.warehouse-monitor'))->viewData('page')['props'];
    $dashboardPj = $this->actingAs($this->pj)->get('/admin/warehouse')->viewData('page')['props']['ringkasanManager'];

    expect(collect($monitorPj['targetPeriode'])->pluck('target')->sort()->values()->all())->toBe([300, 1000, 1100, 1200])
        ->and($monitorPj['bolehAturTargetPeriode'])->toBeTrue()
        ->and(collect($dashboardPj['per_gudang'])->mapWithKeys(fn ($g) => [$g['kode'] => collect($g['target_periode'])->pluck('target')->all()])->all())
        ->toMatchArray(['SMG' => [1200, 300], 'BTL' => [1000]]);
});

it('target yang akan datang belum dihitung progres atau lajunya', function () {
    $target = TargetPeriodeGudang::factory()->create([
        'gudang_id' => $this->smg->id, 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-31', 'target' => 620,
    ]);

    expect(app(TargetPeriodeService::class)->ringkas($target))->toMatchArray([
        'status' => 'akan_datang', 'dikemas' => 0, 'hari_ke' => 0, 'laju' => 0, 'sisa' => 620, 'sisa_hari' => 31, 'perlu_per_hari' => 20,
    ]);
});
