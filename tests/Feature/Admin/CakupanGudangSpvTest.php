<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\TransferMushaf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/*
 * Cakupan data per gudang untuk SPV (21/10/2026).
 *
 * Keluhan pemilik: SPV Semarang masih bisa melihat — dan mengubah — data Gudang Boyolali.
 * Aturannya: SPV hanya melihat gudangnya sendiri + yang BELUM BERLABEL (null), karena data
 * tanpa label bukan milik gudang lain dan hari ini justru hampir seluruhnya. PJ dan
 * super-admin tidak dibatasi.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    foreach (['pemesanan' => 1, 'produksi' => 2, 'packing' => 4, 'selesai-packing' => 5, 'pengiriman' => 6, 'tiba' => 7, 'diterima' => 8, 'batal' => 99] as $slug => $urutan) {
        StatusPengiriman::updateOrCreate(['slug' => $slug], ['nama' => ucfirst(str_replace('-', ' ', $slug)), 'urutan' => $urutan, 'is_active' => true]);
    }

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);
    $this->jenis = JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran A5', 'default_capacity' => 20, 'is_active' => true]);
});

function cgAkun(string $peran, ?Gudang $gudang = null): User
{
    $u = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $u->syncRoles([$peran]);

    return $u->refresh();
}

function cgResi(string $slug, ?Gudang $gudang, string $noResi): Pengiriman
{
    return Pengiriman::factory()->create([
        'no_resi' => $noResi,
        'status_id' => StatusPengiriman::where('slug', $slug)->value('id'),
        'gudang_id' => $gudang?->id,
        'jenis_quran_id' => JenisQuran::where('kode_jenis', 'A5')->value('id'),
        'jumlah_quran' => 1,
        'nama_lembaga' => 'Lembaga '.$noResi,
        'nama_penerima' => 'Penerima '.$noResi,
        'alamat_tujuan' => 'Jl. Uji '.$noResi,
        'qr_code_path' => 'qr/'.$noResi.'.png',
    ]);
}

it('SPV hanya melihat resi gudangnya + yang belum berlabel di Data Resi', function () {
    cgResi('pemesanan', $this->smg, 'EQ-2026-70001');
    cgResi('pemesanan', $this->btl, 'EQ-2026-70002');
    cgResi('pemesanan', null, 'EQ-2026-70003');

    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);

    $terlihat = collect($this->actingAs($spv)->get('/admin/pengiriman')->assertOk()->viewData('page')['props']['pengiriman']['data'])
        ->pluck('no_resi')->all();

    expect($terlihat)->toContain('EQ-2026-70001')
        ->toContain('EQ-2026-70003')
        ->not->toContain('EQ-2026-70002');

    // Pencarian langsung pun tidak boleh membocorkan resi gudang lain.
    $cari = collect($this->actingAs($spv)->get('/admin/pengiriman?search=EQ-2026-70002')->viewData('page')['props']['pengiriman']['data']);
    expect($cari)->toHaveCount(0);
});

it('halaman memberi penanda cakupan untuk SPV, dan tidak untuk PJ', function () {
    cgResi('pemesanan', $this->smg, 'EQ-2026-76001');

    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    expect($this->actingAs($spv)->get('/admin/pengiriman')->viewData('page')['props']['cakupanGudang'])->toBe('Gudang Semarang')
        ->and($this->actingAs($spv)->get('/admin/box-tracking')->viewData('page')['props']['cakupanGudang'])->toBe('Gudang Semarang');

    $pj = cgAkun(RoleEnum::MANAGER_REPACKING->value);
    expect($this->actingAs($pj)->get('/admin/pengiriman')->viewData('page')['props']['cakupanGudang'])->toBeNull();
});

it('PJ dan super-admin tetap melihat resi semua gudang', function (string $peran) {
    cgResi('pemesanan', $this->smg, 'EQ-2026-71001');
    cgResi('pemesanan', $this->btl, 'EQ-2026-71002');

    $terlihat = collect($this->actingAs(cgAkun($peran))->get('/admin/pengiriman')->viewData('page')['props']['pengiriman']['data'])
        ->pluck('no_resi')->all();

    expect($terlihat)->toContain('EQ-2026-71001')->toContain('EQ-2026-71002');
})->with([RoleEnum::MANAGER_REPACKING->value, RoleEnum::SUPER_ADMIN->value]);

it('SPV hanya melihat kerdus gudangnya + yang belum berlabel', function () {
    $smgBox = PackingBox::create(['kode_kerdus' => 'KB-29990201-001-A5-01', 'daily_packing_task_id' => DailyPackingTask::factory()->create()->id, 'kapasitas' => 20, 'jumlah_terisi' => 0, 'status' => PackingBox::STATUS_FILLING, 'gudang_id' => $this->smg->id]);
    PackingBox::create(['kode_kerdus' => 'KB-29990201-002-A5-01', 'daily_packing_task_id' => DailyPackingTask::factory()->create()->id, 'kapasitas' => 20, 'jumlah_terisi' => 0, 'status' => PackingBox::STATUS_FILLING, 'gudang_id' => $this->btl->id]);
    PackingBox::create(['kode_kerdus' => 'KB-29990201-003-A5-01', 'daily_packing_task_id' => DailyPackingTask::factory()->create()->id, 'kapasitas' => 20, 'jumlah_terisi' => 0, 'status' => PackingBox::STATUS_FILLING, 'gudang_id' => null]);

    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);

    $kode = collect($this->actingAs($spv)->get('/admin/box-tracking')->assertOk()->viewData('page')['props']['boxes']['data'])
        ->pluck('kode_kerdus')->all();

    expect($kode)->toEqualCanonicalizing(['KB-29990201-001-A5-01', 'KB-29990201-003-A5-01'])
        ->not->toContain('KB-29990201-002-A5-01');
});

it('resi gudang lain tidak ditawarkan untuk dikemas', function () {
    cgResi('packing', $this->smg, 'EQ-2026-72001');
    cgResi('packing', $this->btl, 'EQ-2026-72002');
    cgResi('packing', null, 'EQ-2026-72003');

    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    DailyPackingTask::factory()->create(['user_id' => $spv->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today()]);

    $props = $this->actingAs($spv)->get('/admin/warehouse/packing')->assertOk()->viewData('page')['props'];
    $tersedia = collect($props['availablePengiriman'])->pluck('no_resi')->all();

    expect($tersedia)->toContain('EQ-2026-72001')->toContain('EQ-2026-72003')
        ->not->toContain('EQ-2026-72002')
        ->and($props['availableCount'])->toBe(2);
});

it('SPV tidak bisa mengubah status resi gudang lain', function () {
    $resi = cgResi('pemesanan', $this->btl, 'EQ-2026-73002');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);

    $this->actingAs($spv)
        ->post("/admin/pengiriman/{$resi->id}/update-status", ['status_id' => StatusPengiriman::where('slug', 'produksi')->value('id')])
        ->assertForbidden();

    expect($resi->fresh()->status->slug)->toBe('pemesanan');
});

it('ubah status massal melewati resi gudang lain, tetap memproses milik sendiri', function () {
    $milikSaya = cgResi('pemesanan', $this->smg, 'EQ-2026-74001');
    $belumBerlabel = cgResi('pemesanan', null, 'EQ-2026-74002');
    $milikLain = cgResi('pemesanan', $this->btl, 'EQ-2026-74003');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);

    $this->actingAs($spv)
        ->post('/admin/pengiriman/batch-status', [
            'pengiriman_ids' => [$milikSaya->id, $belumBerlabel->id, $milikLain->id],
            'status_id' => StatusPengiriman::where('slug', 'produksi')->value('id'),
        ])
        ->assertRedirect();

    expect($milikSaya->fresh()->status->slug)->toBe('produksi')
        ->and($belumBerlabel->fresh()->status->slug)->toBe('produksi')
        ->and($milikLain->fresh()->status->slug)->toBe('pemesanan');
});

it('memindai resi gudang lain di halaman packing ditolak', function () {
    $resi = cgResi('packing', $this->btl, 'EQ-2026-75002');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    DailyPackingTask::factory()->create(['user_id' => $spv->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today()]);

    $res = $this->actingAs($spv)->postJson('/admin/warehouse/packing/scan', ['no_resi' => 'EQ-2026-75002']);

    expect($res->status())->toBeGreaterThanOrEqual(400)
        ->and($resi->fresh()->gudang_id)->toBe($this->btl->id);
});

it('Update Status Massal di Data Resi melewati resi gudang lain', function () {
    $milikSaya = cgResi('pemesanan', $this->smg, 'EQ-2026-76001');
    $belumBerlabel = cgResi('pemesanan', null, 'EQ-2026-76002');
    $milikLain = cgResi('pemesanan', $this->btl, 'EQ-2026-76003');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);

    $this->actingAs($spv)
        ->postJson('/admin/pengiriman/bulk-status', [
            'pengiriman_ids' => [$milikSaya->id, $belumBerlabel->id, $milikLain->id],
            'status_id' => StatusPengiriman::where('slug', 'produksi')->value('id'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.updated_count', 2)
        ->assertJsonPath('data.outside_gudang_count', 1);

    expect($milikSaya->fresh()->status->slug)->toBe('produksi')
        ->and($belumBerlabel->fresh()->status->slug)->toBe('produksi')
        ->and($milikLain->fresh()->status->slug)->toBe('pemesanan');
});

it('PJ tetap bisa Update Status Massal resi semua gudang', function () {
    $resiSmg = cgResi('pemesanan', $this->smg, 'EQ-2026-76011');
    $resiBtl = cgResi('pemesanan', $this->btl, 'EQ-2026-76012');
    $pj = cgAkun(RoleEnum::MANAGER_REPACKING->value);

    $this->actingAs($pj)
        ->postJson('/admin/pengiriman/bulk-status', [
            'pengiriman_ids' => [$resiSmg->id, $resiBtl->id],
            'status_id' => StatusPengiriman::where('slug', 'produksi')->value('id'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.updated_count', 2);
});

/** SPV di produksi ikut memegang role manager-repacking. */
function cgSpvGanda(Gudang $gudang): User
{
    $u = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang->id]);
    $u->syncRoles([RoleEnum::SPV_GUDANG->value, RoleEnum::MANAGER_REPACKING->value]);

    return $u->refresh();
}

function cgDokumenTransfer(User $pembuat, Gudang $tujuan, string $noResi): TransferMushaf
{
    $resi = cgResi('packing', $pembuat->gudang, $noResi);

    test()->actingAs($pembuat)
        ->postJson('/admin/transfer-mushaf', ['gudang_tujuan_id' => $tujuan->id, 'pengiriman_ids' => [$resi->id]])
        ->assertSuccessful();

    return TransferMushaf::latest('id')->firstOrFail();
}

it('SPV yang juga memegang role manager-repacking tidak bisa menerima dokumen ke gudang lain', function () {
    $spvSmg = cgSpvGanda($this->smg);
    $dokumen = cgDokumenTransfer($spvSmg, $this->btl, 'EQ-2026-77001');

    $this->actingAs($spvSmg)
        ->postJson("/admin/transfer-mushaf/{$dokumen->id}/terima")
        ->assertForbidden();

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DIPROSES);
});

it('SPV yang juga memegang role manager-repacking tidak bisa membatalkan dokumen gudang lain', function () {
    $spvBtl = cgSpvGanda($this->btl);
    $spvSmg = cgSpvGanda($this->smg);
    $dokumen = cgDokumenTransfer($spvBtl, $this->smg, 'EQ-2026-77002');

    $this->actingAs($spvSmg)
        ->postJson("/admin/transfer-mushaf/{$dokumen->id}/batalkan")
        ->assertForbidden();

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DIPROSES);
});

it('PJ tanpa role spv-gudang tetap bisa menerima dokumen gudang mana pun', function () {
    $dokumen = cgDokumenTransfer(cgSpvGanda($this->smg), $this->btl, 'EQ-2026-77003');
    $pj = cgAkun(RoleEnum::MANAGER_REPACKING->value);

    $this->actingAs($pj)
        ->postJson("/admin/transfer-mushaf/{$dokumen->id}/terima")
        ->assertSuccessful();

    expect($dokumen->fresh()->status)->toBe(TransferMushaf::STATUS_DITERIMA);
});

function cgTugas(User $pekerja, ?Gudang $gudang): DailyPackingTask
{
    return DailyPackingTask::factory()->create([
        'user_id' => $pekerja->id,
        'gudang_id' => $gudang?->id,
        'tanggal_tugas' => today(),
        'status' => DailyPackingTask::STATUS_ASSIGNED,
        'total_selesai' => 0,
    ]);
}

it('poling Monitor hanya mengirim tugas dan freelancer gudang SPV', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    $freelancerSmg = cgAkun(RoleEnum::WAREHOUSE->value, $this->smg);
    $freelancerBtl = cgAkun(RoleEnum::WAREHOUSE->value, $this->btl);
    $freelancerBaru = cgAkun(RoleEnum::WAREHOUSE->value);
    $tugasSmg = cgTugas($freelancerSmg, $this->smg);
    $tugasBtl = cgTugas($freelancerBtl, $this->btl);

    $data = $this->actingAs($spv)->getJson('/admin/supervisor/warehouse-monitor/data')->assertSuccessful();

    expect(collect($data->json('dailyTasks'))->pluck('id')->all())->toBe([$tugasSmg->id])
        ->and($data->json('dailyTasks.0.gudang'))->toBe('Gudang Semarang')
        ->and(collect($data->json('warehouseUsers'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$freelancerSmg->id, $freelancerBaru->id])->sort()->values()->all());

    $semua = $this->actingAs(cgAkun(RoleEnum::MANAGER_REPACKING->value))
        ->getJson('/admin/supervisor/warehouse-monitor/data')
        ->json('dailyTasks');

    expect(collect($semua)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$tugasSmg->id, $tugasBtl->id])->sort()->values()->all());
});

it('SPV tidak bisa menghapus tugas gudang lain, tapi bisa menghapus tugas gudangnya', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    $tugasBtl = cgTugas(cgAkun(RoleEnum::WAREHOUSE->value, $this->btl), $this->btl);
    $tugasSmg = cgTugas(cgAkun(RoleEnum::WAREHOUSE->value, $this->smg), $this->smg);

    $this->actingAs($spv)
        ->deleteJson("/admin/supervisor/warehouse-monitor/task/{$tugasBtl->id}/delete")
        ->assertForbidden();

    $this->actingAs($spv)
        ->deleteJson("/admin/supervisor/warehouse-monitor/task/{$tugasSmg->id}/delete")
        ->assertSuccessful();

    expect(DailyPackingTask::whereKey($tugasBtl->id)->exists())->toBeTrue()
        ->and(DailyPackingTask::whereKey($tugasSmg->id)->exists())->toBeFalse();
});

it('SPV tidak bisa menugaskan freelancer gudang lain', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    $freelancerBtl = cgAkun(RoleEnum::WAREHOUSE->value, $this->btl);

    $this->actingAs($spv)
        ->postJson('/admin/supervisor/assign-target', ['user_id' => $freelancerBtl->id, 'target' => 10, ...jenisTugasUji()])
        ->assertForbidden();

    expect(DailyPackingTask::where('user_id', $freelancerBtl->id)->exists())->toBeFalse();
});

it('SPV tidak bisa menimpa tugas hari ini yang sudah bertagih gudang lain', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    $freelancer = cgAkun(RoleEnum::WAREHOUSE->value);
    $tugas = cgTugas($freelancer, $this->btl);

    $this->actingAs($spv)
        ->postJson('/admin/supervisor/assign-target', ['user_id' => $freelancer->id, 'target' => 10, ...jenisTugasUji()])
        ->assertForbidden();

    expect($tugas->fresh()->gudang_id)->toBe($this->btl->id);
});

it('SPV tetap bisa menugaskan freelancer gudangnya sendiri', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
    $freelancer = cgAkun(RoleEnum::WAREHOUSE->value, $this->smg);

    $this->actingAs($spv)
        ->postJson('/admin/supervisor/assign-target', ['user_id' => $freelancer->id, 'target' => 10, ...jenisTugasUji()])
        ->assertSuccessful();

    expect(DailyPackingTask::where('user_id', $freelancer->id)->value('gudang_id'))->toBe($this->smg->id);
});

it('SPV yang belum ditempatkan hanya melihat resi tanpa gudang, bukan kedua gudang', function () {
    cgResi('pemesanan', $this->smg, 'EQ-2026-78001');
    cgResi('pemesanan', $this->btl, 'EQ-2026-78002');
    cgResi('pemesanan', null, 'EQ-2026-78003');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value);

    $props = $this->actingAs($spv)->get('/admin/pengiriman')->assertOk()->viewData('page')['props'];

    expect(collect($props['pengiriman']['data'])->pluck('no_resi')->all())->toBe(['EQ-2026-78003'])
        ->and($props['cakupanBelumDitempatkan'])->toBeTrue();
});

it('SPV yang belum ditempatkan tidak bisa mengubah status resi bergudang', function () {
    $resiSmg = cgResi('pemesanan', $this->smg, 'EQ-2026-78011');
    $resiLepas = cgResi('pemesanan', null, 'EQ-2026-78012');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value);

    $this->actingAs($spv)
        ->postJson('/admin/pengiriman/bulk-status', [
            'pengiriman_ids' => [$resiSmg->id, $resiLepas->id],
            'status_id' => StatusPengiriman::where('slug', 'produksi')->value('id'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.outside_gudang_count', 1);

    expect($resiSmg->fresh()->status->slug)->toBe('pemesanan')
        ->and($resiLepas->fresh()->status->slug)->toBe('produksi');
});

it('SPV yang belum ditempatkan tidak melihat dan tidak menugaskan apa pun di Monitor', function () {
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value);
    $freelancer = cgAkun(RoleEnum::WAREHOUSE->value);
    $tugasSmg = cgTugas(cgAkun(RoleEnum::WAREHOUSE->value, $this->smg), $this->smg);

    $data = $this->actingAs($spv)->getJson('/admin/supervisor/warehouse-monitor/data')->assertSuccessful();

    expect($data->json('dailyTasks'))->toBe([])
        ->and($data->json('warehouseUsers'))->toBe([])
        ->and($data->json('spvBelumDitempatkan'))->toBeTrue();

    $this->actingAs($spv)
        ->postJson('/admin/supervisor/assign-target', ['user_id' => $freelancer->id, 'target' => 10, ...jenisTugasUji()])
        ->assertForbidden();
    $this->actingAs($spv)
        ->deleteJson("/admin/supervisor/warehouse-monitor/task/{$tugasSmg->id}/delete")
        ->assertForbidden();

    expect(DailyPackingTask::where('user_id', $freelancer->id)->exists())->toBeFalse()
        ->and(DailyPackingTask::whereKey($tugasSmg->id)->exists())->toBeTrue();
});

it('SPV yang belum ditempatkan tidak bisa membuat dokumen perpindahan dari gudang mana pun', function () {
    $resi = cgResi('packing', $this->smg, 'EQ-2026-78021');
    $spv = cgAkun(RoleEnum::SPV_GUDANG->value);

    $this->actingAs($spv)
        ->postJson('/admin/transfer-mushaf', [
            'gudang_asal_id' => $this->smg->id,
            'gudang_tujuan_id' => $this->btl->id,
            'pengiriman_ids' => [$resi->id],
        ])
        ->assertForbidden();

    expect(TransferMushaf::count())->toBe(0);
});
