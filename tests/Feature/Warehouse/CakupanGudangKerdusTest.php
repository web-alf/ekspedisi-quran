<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

/*
 * Cakupan gudang untuk kerdus (25/10/2026).
 *
 * SPV memegang warehouse.box.update_any dan supervisor.warehouse.monitor, dan dulu kedua
 * izin itu meloloskan semua kerdus — SPV Semarang bisa menyegel, mengubah status, dan
 * memproses kerdus bersama milik Boyolali. Aturannya sama dengan resi: gudang sendiri +
 * yang belum berlabel. PJ dan super-admin tidak dibatasi.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    StatusPengiriman::updateOrCreate(['slug' => 'pengiriman'], ['nama' => 'Pengiriman', 'urutan' => 6, 'is_active' => true]);

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);
    JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran A5', 'default_capacity' => 30, 'is_active' => true]);

    $this->spv = kerdusAkun(RoleEnum::SPV_GUDANG->value, $this->smg);
});

function kerdusAkun(string $peran, ?Gudang $gudang = null): User
{
    $u = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $u->syncRoles([$peran]);

    return $u->refresh();
}

/** Kerdus wajib menempel ke tugas harian; tugasnya milik freelancer gudang yang sama. */
function kerdusTugas(?Gudang $gudang): array
{
    $tugas = DailyPackingTask::factory()->create([
        'user_id' => kerdusAkun(RoleEnum::WAREHOUSE->value, $gudang)->id,
        'gudang_id' => $gudang?->id,
        'tanggal_tugas' => today(),
    ]);

    return [
        'daily_packing_task_id' => $tugas->id,
        'gudang_id' => $gudang?->id,
        'jenis_quran_id' => JenisQuran::where('kode_jenis', 'A5')->value('id'),
    ];
}

function kerdusDi(?Gudang $gudang): PackingBox
{
    return PackingBox::factory()->filling()->create(kerdusTugas($gudang));
}

function kerdusBersama(?Gudang $gudang): PackingBox
{
    return PackingBox::factory()->shared()->filling()->create(kerdusTugas($gudang));
}

it('SPV tidak bisa menyegel kerdus gudang lain', function () {
    $kerdus = kerdusDi($this->btl);

    $this->actingAs($this->spv)
        ->postJson("/admin/warehouse/packing/box/{$kerdus->id}/seal")
        ->assertForbidden();

    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_FILLING);
});

it('SPV bisa menyegel kerdus gudangnya dan kerdus yang belum berlabel', function (?string $kodeGudang) {
    $kerdus = kerdusDi($kodeGudang ? Gudang::byKode($kodeGudang) : null);

    $this->actingAs($this->spv)
        ->postJson("/admin/warehouse/packing/box/{$kerdus->id}/seal")
        ->assertSuccessful();

    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_SEALED);
})->with(['gudang sendiri' => 'SMG', 'belum berlabel' => null]);

it('PJ tetap bisa menyegel kerdus gudang mana pun', function () {
    $kerdus = kerdusDi($this->btl);

    $this->actingAs(kerdusAkun(RoleEnum::MANAGER_REPACKING->value))
        ->postJson("/admin/warehouse/packing/box/{$kerdus->id}/seal")
        ->assertSuccessful();

    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_SEALED);
});

it('memindai kerdus gudang lain di Box Scanner ditolak', function () {
    $kerdus = kerdusDi($this->btl);

    $this->actingAs($this->spv)
        ->postJson('/admin/warehouse/box-scanner/scan', ['qr_code' => $kerdus->kode_kerdus])
        ->assertForbidden()
        ->assertJsonPath('message', 'Kerdus ini tercatat di gudang lain. Hanya SPV gudang tersebut yang boleh mengubahnya.');
});

it('memindai kerdus gudang sendiri di Box Scanner tetap bisa', function () {
    $kerdus = kerdusDi($this->smg);

    $this->actingAs($this->spv)
        ->postJson('/admin/warehouse/box-scanner/scan', ['qr_code' => $kerdus->kode_kerdus])
        ->assertSuccessful()
        ->assertJsonPath('data.box.id', $kerdus->id);
});

it('mengubah status kerdus gudang lain di Box Scanner ditolak', function () {
    $kerdus = kerdusDi($this->btl);

    $this->actingAs($this->spv)
        ->postJson('/admin/warehouse/box-scanner/update-status', [
            'box_id' => $kerdus->id,
            'new_status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
        ])
        ->assertForbidden();

    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_FILLING);
});

it('operasi massal Box Scanner ditolak utuh bila ada kerdus gudang lain', function () {
    Queue::fake();
    $milikSaya = kerdusDi($this->smg);
    $milikLain = kerdusDi($this->btl);

    $this->actingAs($this->spv)
        ->postJson('/admin/warehouse/box-scanner/bulk-update-status', [
            'box_ids' => [$milikSaya->id, $milikLain->id],
            'new_status_id' => StatusPengiriman::where('slug', 'pengiriman')->value('id'),
        ])
        ->assertForbidden()
        ->assertJsonPath('data.kerdus_gudang_lain', [$milikLain->kode_kerdus]);

    $this->actingAs($this->spv)
        ->postJson('/admin/warehouse/box-scanner/bulk-box-operation', [
            'box_codes' => [$milikSaya->kode_kerdus, $milikLain->kode_kerdus],
            'operation' => 'seal',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('daftar kerdus bersama hanya gudang SPV + yang belum berlabel', function () {
    $milikSaya = kerdusBersama($this->smg);
    $belumBerlabel = kerdusBersama(null);
    kerdusBersama($this->btl);

    $bersama = $this->actingAs($this->spv)->getJson('/admin/warehouse/shared-boxes-status')->assertSuccessful();

    expect(collect($bersama->json('shared_boxes'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$milikSaya->id, $belumBerlabel->id])->sort()->values()->all());
});

it('progres, penyelesaian, dan permintaan segel kerdus bersama gudang lain ditolak', function () {
    $milikLain = kerdusBersama($this->btl);

    $this->actingAs($this->spv)->getJson("/admin/warehouse/box-progress/{$milikLain->id}")->assertForbidden();
    $this->actingAs($this->spv)->postJson("/admin/warehouse/box/{$milikLain->id}/trigger-completion")->assertForbidden();
    $this->actingAs($this->spv)->postJson("/admin/warehouse/box/{$milikLain->id}/seal-request")->assertForbidden();

    expect($milikLain->fresh()->status)->toBe(PackingBox::STATUS_FILLING);
});

it('progres kerdus bersama gudang sendiri tetap bisa dibuka', function () {
    $milikSaya = kerdusBersama($this->smg);

    $this->actingAs($this->spv)
        ->getJson("/admin/warehouse/box-progress/{$milikSaya->id}")
        ->assertSuccessful()
        ->assertJsonPath('box.id', $milikSaya->id);
});

it('SPV yang belum ditempatkan hanya bisa menyegel kerdus tanpa gudang', function () {
    $spvLepas = kerdusAkun(RoleEnum::SPV_GUDANG->value);
    $kerdusSmg = kerdusDi($this->smg);
    $kerdusLepas = kerdusDi(null);

    $this->actingAs($spvLepas)->postJson("/admin/warehouse/packing/box/{$kerdusSmg->id}/seal")->assertForbidden();
    $this->actingAs($spvLepas)->postJson("/admin/warehouse/packing/box/{$kerdusLepas->id}/seal")->assertSuccessful();

    expect($kerdusSmg->fresh()->status)->toBe(PackingBox::STATUS_FILLING)
        ->and($kerdusLepas->fresh()->status)->toBe(PackingBox::STATUS_SEALED);
});
