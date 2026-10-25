<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\SharedBoxAssignment;
use App\Models\StatusPengiriman;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/*
 * Alur penyelesaian kerdus bersama (25/10/2026): selesai semua kontributor → ready_to_seal
 * → seal_requested → sealed. Status ready_to_seal/seal_requested dulu tidak ada di enum
 * kolom, dan syarat segel hanya menerima `filling`, sehingga alur ini tidak pernah jalan.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->a5 = JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => 'Al-Quran A5', 'default_capacity' => 20, 'is_active' => true]);

    $this->spv = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->spv->syncRoles([RoleEnum::SPV_GUDANG->value]);
});

/** Kerdus bersama berkapasitas 20 yang diisi penuh oleh dua kontributor. */
function kerdusBersamaSelesai(Gudang $gudang, JenisQuran $jenis): PackingBox
{
    $kontributor = collect([1, 2])->map(function () use ($gudang) {
        $u = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang->id]);
        $u->syncRoles([RoleEnum::WAREHOUSE->value]);

        return DailyPackingTask::factory()->create(['user_id' => $u->id, 'gudang_id' => $gudang->id, 'tanggal_tugas' => today()]);
    });

    $kerdus = PackingBox::factory()->shared()->create([
        'daily_packing_task_id' => $kontributor->first()->id,
        'gudang_id' => $gudang->id,
        'jenis_quran_id' => $jenis->id,
        'kapasitas' => 20,
        'jumlah_terisi' => 20,
        'status' => PackingBox::STATUS_FILLING,
    ]);

    foreach ($kontributor as $tugas) {
        SharedBoxAssignment::create([
            'packing_box_id' => $kerdus->id,
            'daily_packing_task_id' => $tugas->id,
            'user_id' => $tugas->user_id,
            'allocated_items' => 10,
            'completed_items' => 10,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    return $kerdus->refresh();
}

it('menjalankan alur penuh: siap segel, minta segel, lalu disegel', function () {
    $kerdus = kerdusBersamaSelesai($this->smg, $this->a5);

    $this->actingAs($this->spv)->postJson("/admin/warehouse/box/{$kerdus->id}/trigger-completion")
        ->assertSuccessful()->assertJsonPath('success', true);
    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_READY_TO_SEAL);

    $siap = $this->actingAs($this->spv)->getJson('/admin/warehouse/boxes-ready-for-seal')->assertSuccessful();
    expect(collect($siap->json('boxes'))->pluck('id')->all())->toBe([$kerdus->id]);

    $this->actingAs($this->spv)->postJson("/admin/warehouse/box/{$kerdus->id}/seal-request")
        ->assertSuccessful()->assertJsonPath('box_status', PackingBox::STATUS_SEAL_REQUESTED);
    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_SEAL_REQUESTED);

    $this->actingAs($this->spv)->postJson("/admin/warehouse/packing/box/{$kerdus->id}/seal")
        ->assertSuccessful()->assertJsonPath('success', true);
    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_SEALED);
});

it('tidak menandai siap segel bila kontributor belum selesai semua', function () {
    $kerdus = kerdusBersamaSelesai($this->smg, $this->a5);
    $kerdus->sharedBoxAssignments()->first()->update(['completed_items' => 4, 'completed_at' => null]);

    $this->actingAs($this->spv)->postJson("/admin/warehouse/box/{$kerdus->id}/trigger-completion");

    expect($kerdus->fresh()->status)->toBe(PackingBox::STATUS_FILLING);
});

it('kerdus yang sudah siap segel tidak menerima isi baru', function () {
    StatusPengiriman::updateOrCreate(['slug' => 'packing'], ['nama' => 'Packing', 'urutan' => 4, 'is_active' => true]);
    $kerdus = PackingBox::factory()->create([
        'daily_packing_task_id' => DailyPackingTask::factory()->create(['gudang_id' => $this->smg->id])->id,
        'gudang_id' => $this->smg->id,
        'jenis_quran_id' => $this->a5->id,
        'kapasitas' => 20,
        'jumlah_terisi' => 5,
        'status' => PackingBox::STATUS_READY_TO_SEAL,
    ]);
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
        'jenis_quran_id' => $this->a5->id,
        'jumlah_quran' => 1,
    ]);

    expect(fn () => $kerdus->addItemSafe($resi, $this->spv->id))
        ->toThrow(Exception::class, 'Kerdus sudah ditandai siap disegel');

    expect($kerdus->fresh()->jumlah_terisi)->toBe(5);
});
