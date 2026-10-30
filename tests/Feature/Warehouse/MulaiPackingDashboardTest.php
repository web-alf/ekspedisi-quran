<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Tombol "Mulai / Lanjutkan Packing" di dashboard freelancer (30/10/2026).
 *
 * Tombolnya kini meminta JSON dan bereaksi pada kode status: 419/401 memuat ulang halaman,
 * 404/400 menampilkan pesan server lalu menyegarkan kartu tugas. Karena itu setiap jalur
 * gagal harus dijawab JSON berpesan, bukan halaman HTML atau redirect.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->freelancer = User::factory()->create(['is_active' => true]);
    $this->freelancer->syncRoles([RoleEnum::WAREHOUSE->value]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->tugas = fn (string $status): DailyPackingTask => DailyPackingTask::factory()->create([
        'user_id' => $this->freelancer->id,
        'tanggal_tugas' => today(),
        'total_target' => 80,
        'status' => $status,
    ]);
});

it('memulai tugas yang belum dimulai', function () {
    $tugas = ($this->tugas)(DailyPackingTask::STATUS_ASSIGNED);

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/start-scanning')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($tugas->fresh())
        ->status->toBe(DailyPackingTask::STATUS_IN_PROGRESS)
        ->started_at->not->toBeNull();
});

it('melanjutkan tugas yang sedang berjalan tanpa mengubah jam mulainya', function () {
    $tugas = ($this->tugas)(DailyPackingTask::STATUS_IN_PROGRESS);
    $tugas->update(['started_at' => now()->subHours(3)]);
    $jamMulai = $tugas->fresh()->started_at;

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/start-scanning')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($tugas->fresh()->started_at->equalTo($jamMulai))->toBeTrue();
});

it('menjawab 401 JSON, bukan redirect login, saat sesi sudah berakhir', function () {
    $this->postJson('/admin/warehouse/start-scanning')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('menjawab 404 berpesan bila tugas hari ini sudah tidak ada', function () {
    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/start-scanning')
        ->assertNotFound()
        ->assertJson(['success' => false, 'message' => 'Tidak ada tugas untuk hari ini']);
});

it('menjawab 400 berpesan bila tugas sudah selesai dari perangkat lain', function () {
    ($this->tugas)(DailyPackingTask::STATUS_COMPLETED);

    $this->actingAs($this->freelancer)
        ->postJson('/admin/warehouse/start-scanning')
        ->assertBadRequest()
        ->assertJson(['success' => false, 'message' => 'Tugas hari ini sudah selesai']);
});
