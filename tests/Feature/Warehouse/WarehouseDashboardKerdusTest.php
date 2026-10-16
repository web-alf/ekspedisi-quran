<?php

use App\Http\Controllers\Warehouse\DashboardController;
use App\Models\DailyPackingTask;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dashboard Gudang (/admin/warehouse) sudah lama mengirim prop `recentBoxes`,
 * tetapi halaman Svelte-nya tidak pernah mendeklarasikannya sehingga hasilnya
 * dibuang tanpa pesan: kerdus yang isinya sudah jalan tidak terlihat di mana pun
 * di dashboard. Tes ini menjaga agar datanya benar-benar sampai ke halaman
 * (dan sampai pula lewat endpoint penyegar /dashboard-status).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['warehouse.dashboard', 'warehouse.packing.scan', 'warehouse.boxes.view'] as $nama) {
        Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->staf = User::factory()->create(['is_active' => true, 'name' => 'Staff Gudang']);
    $this->staf->givePermissionTo(['warehouse.dashboard', 'warehouse.packing.scan', 'warehouse.boxes.view']);

    $this->jenis = JenisQuran::where('kode_jenis', 'A5')->firstOrFail();
});

it('menampilkan kerdus milik staf di dashboard gudang', function () {
    $tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->staf->id,
        'tanggal_tugas' => today(),
    ]);

    PackingBox::factory()->create([
        'daily_packing_task_id' => $tugas->id,
        'jenis_quran_id' => $this->jenis->id,
        'kode_kerdus' => 'KB-20261006-003-A5-01',
        'kapasitas' => 20,
        'jumlah_terisi' => 20,
        'status' => PackingBox::STATUS_FULL,
    ]);

    $this->actingAs($this->staf)
        ->get('/admin/warehouse')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Warehouse/Dashboard')
            ->has('recentBoxes', 1)
            ->where('recentBoxes.0.kode_kerdus', 'KB-20261006-003-A5-01')
            ->where('recentBoxes.0.jumlah_terisi', 20)
            ->where('recentBoxes.0.kapasitas', 20)
            ->where('recentBoxes.0.progress_percentage', 100));
});

it('tidak menampilkan kerdus milik staf lain', function () {
    $stafLain = User::factory()->create(['is_active' => true]);
    $tugasLain = DailyPackingTask::factory()->create([
        'user_id' => $stafLain->id,
        'tanggal_tugas' => today(),
    ]);

    PackingBox::factory()->create([
        'daily_packing_task_id' => $tugasLain->id,
        'jenis_quran_id' => $this->jenis->id,
        'kode_kerdus' => 'KB-20261006-999-A5-01',
        'status' => PackingBox::STATUS_FILLING,
    ]);

    $this->actingAs($this->staf)
        ->get('/admin/warehouse')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentBoxes', 0));
});

it('mengirim daftar kerdus kosong bila belum ada kerdus', function () {
    DailyPackingTask::factory()->create([
        'user_id' => $this->staf->id,
        'tanggal_tugas' => today(),
    ]);

    $this->actingAs($this->staf)
        ->get('/admin/warehouse')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentBoxes', 0));
});

it('menyegarkan daftar kerdus lewat endpoint dashboard-status', function () {
    $tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->staf->id,
        'tanggal_tugas' => today(),
    ]);

    PackingBox::factory()->create([
        'daily_packing_task_id' => $tugas->id,
        'jenis_quran_id' => $this->jenis->id,
        'kode_kerdus' => 'KB-20261006-003-A5-02',
        'kapasitas' => 20,
        'jumlah_terisi' => 3,
        'status' => PackingBox::STATUS_FILLING,
    ]);

    $this->actingAs($this->staf)
        ->getJson('/admin/warehouse/dashboard-status')
        ->assertOk()
        ->assertJsonPath('data.recentBoxes.0.kode_kerdus', 'KB-20261006-003-A5-02')
        ->assertJsonPath('data.recentBoxes.0.progress_percentage', 15);
});

it('membatasi daftar kerdus pada jumlah yang diminta', function () {
    $tugas = DailyPackingTask::factory()->create([
        'user_id' => $this->staf->id,
        'tanggal_tugas' => today(),
    ]);

    PackingBox::factory()->count(3)->create([
        'daily_packing_task_id' => $tugas->id,
        'jenis_quran_id' => $this->jenis->id,
    ]);

    $controller = new DashboardController(app(PackingAssignmentService::class));

    expect($controller->getRecentBoxesForUser($this->staf, 2))->toHaveCount(2);
    expect($controller->getRecentBoxesForUser($this->staf, 10))->toHaveCount(3);
});
