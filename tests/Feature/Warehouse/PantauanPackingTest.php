<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\PackingBox;
use App\Models\PackingScanLog;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Pantauan packing hari ini di halaman Proses Packing (06/11/2026).
 *
 * Manager Gudang yang membuka halaman ini tanpa tugas dulu hanya melihat "Tidak ada tugas
 * packing — hubungi supervisor" plus riwayat. Kini pemantau (SPV, Manager Gudang,
 * super-admin) melihat progres packing, resi siap dikemas, dan scan gagal hari ini dalam
 * cakupannya; freelancer tetap melihat kartu tanpa tugas.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::byKode('SMG');
    $this->btl = Gudang::byKode('BTL');

    $this->buatAkun = function (array $peran, ?Gudang $gudang = null): User {
        $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
        $user->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    $this->manager = ($this->buatAkun)([RoleEnum::MANAGER_REPACKING->value, RoleEnum::WAREHOUSE->value]);
    $this->freelancerSmg = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);
    $this->freelancerBtl = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->btl);

    $this->tugasSmg = DailyPackingTask::factory()->create([
        'user_id' => $this->freelancerSmg->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today(),
        'total_target' => 40,
        'total_selesai' => 10,
        'status' => DailyPackingTask::STATUS_IN_PROGRESS,
        'started_at' => now()->subHour(),
    ]);
    DailyPackingTask::factory()->create([
        'user_id' => $this->freelancerBtl->id,
        'gudang_id' => $this->btl->id,
        'tanggal_tugas' => today(),
        'total_target' => 20,
        'total_selesai' => 0,
    ]);
    PackingBox::factory()->sealed()->create([
        'daily_packing_task_id' => $this->tugasSmg->id,
        'gudang_id' => $this->smg->id,
        'sealed_at' => now(),
    ]);

    $statusPacking = StatusPengiriman::where('slug', 'packing')->value('id');
    foreach ([$this->smg->id, $this->smg->id, $this->btl->id, null] as $gudangId) {
        Pengiriman::factory()->create(['status_id' => $statusPacking, 'gudang_id' => $gudangId]);
    }

    PackingScanLog::recordFailed($this->freelancerSmg->id, 'QR-SMG', 'EQ-2026-00001', 'wrong_jenis', 'Jenis berbeda dari tugas', $this->tugasSmg->id);
    PackingScanLog::recordFailed($this->freelancerBtl->id, 'QR-BTL', 'EQ-2026-00002', 'not_found', 'Resi tidak ditemukan');
});

/** @return array<string, mixed> props halaman Proses Packing */
function propsProsesPacking(User $akun): array
{
    return test()->actingAs($akun)->get('/admin/warehouse/packing')->assertOk()->viewData('page')['props'];
}

it('menampilkan pantauan semua gudang untuk Manager Gudang tanpa tugas', function () {
    $pantauan = propsProsesPacking($this->manager)['pantauan'];
    $perGudang = collect($pantauan['gudang'])->keyBy('kode');

    expect($perGudang->keys()->all())->toBe(['SMG', 'BTL'])
        ->and($perGudang['SMG'])->toMatchArray([
            'target' => 40,
            'selesai' => 10,
            'persen' => 25,
            'jumlah_staf' => 1,
            'staf_belum_mulai' => 0,
            'kerdus_tersegel' => 1,
            'siap_dikemas' => 2,
        ])
        ->and($perGudang['SMG']['per_staf'][0]['nama'])->toBe($this->freelancerSmg->name)
        ->and($perGudang['BTL'])->toMatchArray(['staf_belum_mulai' => 1, 'siap_dikemas' => 1])
        ->and($pantauan['siap_tanpa_gudang'])->toBe(1)
        ->and(collect($pantauan['scan_gagal'])->pluck('no_resi')->all())->toBe(['EQ-2026-00002', 'EQ-2026-00001'])
        ->and(collect($pantauan['scan_gagal'])->pluck('gudang_id')->all())->toBe([$this->btl->id, $this->smg->id]);
});

it('membatasi pantauan SPV ke gudangnya sendiri', function () {
    $spvSmg = ($this->buatAkun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value], $this->smg);

    $pantauan = propsProsesPacking($spvSmg)['pantauan'];

    expect(collect($pantauan['gudang'])->pluck('kode')->all())->toBe(['SMG'])
        ->and(collect($pantauan['scan_gagal'])->pluck('no_resi')->all())->toBe(['EQ-2026-00001']);
});

it('SPV yang belum ditempatkan tidak memantau gudang mana pun', function () {
    $spv = ($this->buatAkun)([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value]);

    expect(propsProsesPacking($spv)['pantauan']['gudang'])->toBe([]);
});

it('tidak mengirim pantauan ke freelancer tanpa tugas', function () {
    $freelancer = ($this->buatAkun)([RoleEnum::WAREHOUSE->value], $this->smg);

    $props = propsProsesPacking($freelancer);

    expect($props['pantauan'])->toBeNull()
        ->and($props['noTaskMessage'])->toContain('Tidak ada tugas packing');
});

it('tidak mengirim pantauan ke pemantau yang sedang memegang tugas', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->manager->id,
        'tanggal_tugas' => today(),
        'total_target' => 10,
    ]);

    $props = propsProsesPacking($this->manager);

    expect($props)->not->toHaveKey('pantauan')
        ->and($props['task'])->not->toBeNull();
});

it('menyertakan gudang di setiap baris riwayat untuk penyaringan per gudang', function () {
    $riwayat = collect(propsProsesPacking($this->manager)['riwayatPacking'])->keyBy('id');

    expect($riwayat[$this->tugasSmg->id]['gudang_id'])->toBe($this->smg->id);
});

it('riwayat lengkap menampilkan tugas semua petugas untuk Manager Gudang', function () {
    $props = $this->actingAs($this->manager)->get('/admin/warehouse/packing/history')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['semuaStaf'])->toBeTrue()
        ->and($props['history']['total'])->toBe(2)
        ->and(collect($props['history']['data'])->pluck('user.name')->sort()->values()->all())
        ->toBe(collect([$this->freelancerSmg->name, $this->freelancerBtl->name])->sort()->values()->all());
});

it('riwayat lengkap tetap hanya tugas sendiri untuk freelancer', function () {
    $props = $this->actingAs($this->freelancerSmg)->get('/admin/warehouse/packing/history')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['semuaStaf'])->toBeFalse()
        ->and(collect($props['history']['data'])->pluck('id')->all())->toBe([$this->tugasSmg->id]);
});
