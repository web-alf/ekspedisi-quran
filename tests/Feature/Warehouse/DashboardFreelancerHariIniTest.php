<?php

use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: data per freelancer yang dikirim ke dashboard gudang.
 *
 * Baris "Freelancer yang kerja hari ini" memakai susunan yang sama dengan daftar di
 * halaman Performa Kerja (26/10/2026): nama + status, jam mulai/selesai + jumlah kerdus,
 * lalu selesai/target dengan bar progress. Tanpa status, jam, kerdus, dan persen di
 * props, barisnya akan tampil kosong walau nama dan angkanya benar.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);

    $spv = User::factory()->create(['name' => 'SPV Semarang', 'is_active' => true, 'gudang_id' => $this->smg->id]);
    $spv->syncRoles(['spv-gudang']);
    $this->spv = $spv;

    $this->staf = User::factory()->create(['name' => 'Qanita Nadya', 'is_active' => true, 'gudang_id' => $this->smg->id]);
    $this->staf->syncRoles(['warehouse']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function fkTask(User $staf, int $target, int $selesai, bool $mulai = true): DailyPackingTask
{
    return DailyPackingTask::create([
        'user_id' => $staf->id,
        'tanggal_tugas' => today(),
        'total_target' => $target,
        'total_selesai' => $selesai,
        'status' => $selesai >= $target ? 'completed' : ($mulai ? 'in_progress' : 'assigned'),
        'started_at' => $mulai ? now()->setTime(8, 12) : null,
        'completed_at' => $selesai >= $target ? now()->setTime(11, 34) : null,
        'assigned_at' => now(),
        'gudang_id' => $staf->gudang_id,
    ]);
}

it('mengirim status, jam, kerdus, dan persen tiap freelancer', function () {
    fkTask($this->staf, 80, 52);

    $props = $this->actingAs($this->spv)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];
    $staf = $props['ringkasanGudang']['packing']['per_staf'][0];

    expect($staf)->toHaveKeys(['id', 'nama', 'selesai', 'target', 'mulai', 'status', 'persen', 'jam_mulai', 'jam_selesai', 'kerdus'])
        ->and($staf['nama'])->toBe('Qanita Nadya')
        ->and($staf['status'])->toBe('mulai')
        ->and($staf['persen'])->toBe(65)
        ->and($staf['jam_mulai'])->toBe('08:12')
        ->and($staf['jam_selesai'])->toBeNull()
        ->and($staf['kerdus'])->toBe(0);
});

it('menandai freelancer yang sudah mencapai target sebagai selesai', function () {
    fkTask($this->staf, 96, 96);

    $staf = $this->actingAs($this->spv)->get('/admin/warehouse')->assertOk()
        ->viewData('page')['props']['ringkasanGudang']['packing']['per_staf'][0];

    expect($staf['status'])->toBe('selesai')
        ->and($staf['persen'])->toBe(100)
        ->and($staf['jam_selesai'])->toBe('11:34');
});

it('tetap mengirim freelancer yang belum mulai dengan status tidak_mulai', function () {
    fkTask($this->staf, 80, 0, mulai: false);

    $r = $this->actingAs($this->spv)->get('/admin/warehouse')->assertOk()
        ->viewData('page')['props']['ringkasanGudang']['packing'];

    expect($r['staf_belum_mulai'])->toBe(1)
        ->and($r['per_staf'][0]['mulai'])->toBeFalse()
        ->and($r['per_staf'][0]['status'])->toBe('tidak_mulai')
        ->and($r['per_staf'][0]['jam_mulai'])->toBeNull();
});

it('tidak membagi nol saat target freelancer kosong', function () {
    fkTask($this->staf, 0, 0);

    $staf = $this->actingAs($this->spv)->get('/admin/warehouse')->assertOk()
        ->viewData('page')['props']['ringkasanGudang']['packing']['per_staf'][0];

    expect($staf['persen'])->toBe(0);
});

it('memberi label peran pada baris, supaya akun PJ/SPV tidak terbaca sebagai freelancer', function () {
    // Akun PJ yang juga memegang role warehouse (seperti gudang@ di produksi) tetap punya
    // tugas harian; label perannya yang membedakan dia dari freelancer.
    $pj = User::factory()->create(['is_active' => true, 'gudang_id' => $this->smg->id, 'name' => 'PJ Gudang']);
    $pj->syncRoles(['warehouse', 'manager-repacking']);
    fkTask($pj, 80, 10);
    fkTask($this->staf, 80, 12);

    $perStaf = $this->actingAs($this->spv)->get('/admin/warehouse')->assertOk()
        ->viewData('page')['props']['ringkasanGudang']['packing']['per_staf'];

    $barisPj = collect($perStaf)->firstWhere('nama', 'PJ Gudang');
    $barisFreelancer = collect($perStaf)->firstWhere('nama', 'Qanita Nadya');

    expect($barisPj['peran'])->toBe('Manager Repacking (PJ Gudang)')
        ->and($barisFreelancer['peran'])->toBe('Freelancer Gudang');
});
