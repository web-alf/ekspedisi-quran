<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\TargetPeriodeGudang;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use App\Services\TargetPeriodeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Progres tugas packing dihitung per EKS saat memindai resi (30/10/2026).
 *
 * Kerdus dan mode Kardus sudah menghitung jumlah_quran resi, tetapi jalur pindai resi
 * menambah total_selesai +1 per resi. Resi permintaan lembaga yang berisi banyak mushaf
 * jadi terhitung kurang — di tugas harian, Packing Hari Ini, dan Target Periode.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    StatusPengirimanCache::clear();

    $this->statusPacking = StatusPengiriman::firstOrCreate(['slug' => 'packing'], ['nama' => 'Proses Packing', 'urutan' => 4]);
    $this->smg = Gudang::byKode('SMG');
    $this->a5 = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $this->a5->update(['default_capacity' => 20]);

    $this->petugas = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->petugas->syncRoles([RoleEnum::WAREHOUSE->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->petugas->id,
        'gudang_id' => $this->smg->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
    ]);

    $this->resi = fn (int $eks, ?JenisQuran $jenis = null): Pengiriman => Pengiriman::factory()->create([
        'jenis_quran_id' => ($jenis ?? $this->a5)->id,
        'jumlah_quran' => $eks,
        'status_id' => $this->statusPacking->id,
        'gudang_id' => $this->smg->id,
    ]);
});

it('menambah progres tugas sebanyak jumlah mushaf di resi yang dipindai', function (int $eks) {
    $resi = ($this->resi)($eks);

    $this->actingAs($this->petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $resi->no_resi])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($this->tugas->fresh()->total_selesai)->toBe($eks)
        ->and((int) PackingBox::where('daily_packing_task_id', $this->tugas->id)->sum('jumlah_terisi'))->toBe($eks);
})->with([
    'resi donatur, 1 mushaf' => [1],
    'resi lembaga, 7 mushaf' => [7],
]);

it('jalur konfirmasi jenis berbeda juga menghitung per eks', function () {
    $a6 = JenisQuran::where('kode_jenis', 'A6')->first() ?? JenisQuran::factory()->a6()->create();
    $this->tugas->getOrActivateBoxForJenis($a6->id);
    $resi = ($this->resi)(6);

    $this->actingAs($this->petugas)
        ->postJson('/admin/warehouse/packing/scan-confirm', [
            'pengiriman_id' => $resi->id,
            'confirm_seal_others' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($this->tugas->fresh()->total_selesai)->toBe(6);
});

it('menyelesaikan tugas begitu jumlah eks mencapai target', function () {
    $this->tugas->update(['total_target' => 8]);

    foreach ([5, 3] as $eks) {
        $this->actingAs($this->petugas)
            ->postJson('/admin/warehouse/packing/scan', ['qr_data' => ($this->resi)($eks)->no_resi])
            ->assertOk();
    }

    expect($this->tugas->fresh())
        ->total_selesai->toBe(8)
        ->status->toBe(DailyPackingTask::STATUS_COMPLETED);
});

it('Target Periode gudang ikut berkurang sesuai jumlah eks', function () {
    $target = TargetPeriodeGudang::factory()->create(['gudang_id' => $this->smg->id, 'target' => 4000]);

    $this->actingAs($this->petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => ($this->resi)(12)->no_resi])
        ->assertOk();

    expect(app(TargetPeriodeService::class)->ringkas($target))->toMatchArray(['dikemas' => 12, 'sisa' => 3988]);
});
