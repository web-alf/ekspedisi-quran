<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\User;
use App\Services\PackingAssignmentService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cakupan: siapa yang berhak MEMULAI packing, dan siapa yang boleh diberi tugas.
 *
 * Konteks: halaman /admin/warehouse dijaga izin warehouse.dashboard, yang juga
 * dimiliki manager untuk memantau gudang. Dulu penugasan harian memakai izin itu
 * sebagai patokan, sehingga manager ikut menerima tugas packing setiap hari —
 * padahal endpoint /admin/warehouse/start-scanning dijaga warehouse.packing.scan
 * dan menolak mereka dengan 403. Hasilnya tombol "Mulai Packing" yang selalu gagal.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('memberi izin memindai kepada staff gudang, tetapi tidak kepada manager', function () {
    // Inilah perbedaan yang menentukan: manager hanya memantau.
    $warehouse = User::factory()->create(['is_active' => true]);
    $warehouse->assignRole(RoleEnum::WAREHOUSE->value);

    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($warehouse->can(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))->toBeTrue()
        ->and($manager->can(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))->toBeFalse()
        // Keduanya tetap boleh MEMBUKA dasbor gudang: manager memantau lewat situ.
        ->and($warehouse->can(PermissionEnum::WAREHOUSE_DASHBOARD->value))->toBeTrue()
        ->and($manager->can(PermissionEnum::WAREHOUSE_DASHBOARD->value))->toBeTrue();
});

it('menolak manager memulai pemindaian dengan 403, bukan membiarkannya menekan tombol yang gagal', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    DailyPackingTask::create([
        'user_id' => $manager->id,
        'tanggal_tugas' => today(),
        'total_target' => 80,
        'total_selesai' => 0,
        'status' => 'assigned',
        'assignment_method' => 'target_only',
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager)
        ->postJson('/admin/warehouse/start-scanning')
        ->assertForbidden();
});

it('hanya menugaskan petugas yang benar-benar boleh memindai', function () {
    // Penugasan otomatis melewati akhir pekan, jadi tes HARUS memakai hari kerja.
    // Kalau tidak, tidak ada tugas yang tercipta untuk siapa pun dan tesnya lulus
    // secara palsu.
    $hariKerja = today()->isWeekend() ? today()->next(Carbon::MONDAY) : today();

    // Staff gudang: boleh memindai, jadi berhak menerima tugas.
    $petugas = User::factory()->create(['is_active' => true]);
    $petugas->assignRole(RoleEnum::WAREHOUSE->value);

    // Manager: hanya memantau, tidak boleh menerima tugas packing.
    $manager = User::factory()->create(['is_active' => true]);
    $manager->assignRole(RoleEnum::MANAGER->value);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    app(PackingAssignmentService::class)->runDailyAssignment($hariKerja);

    $diberiTugas = DB::table('daily_packing_tasks')
        ->whereDate('tanggal_tugas', $hariKerja)
        ->pluck('user_id');

    expect($diberiTugas)->toContain($petugas->id)
        ->and($diberiTugas)->not->toContain($manager->id);
});

it('memastikan setiap penerima tugas benar-benar boleh memulai pemindaian', function () {
    $hariKerja = today()->isWeekend() ? today()->next(Carbon::MONDAY) : today();

    // Populasi campuran: hanya sebagian yang berhak memindai.
    foreach ([RoleEnum::WAREHOUSE, RoleEnum::MANAGER, RoleEnum::SUPERVISOR, RoleEnum::SUPER_ADMIN, RoleEnum::CUSTOMER_SERVICE] as $role) {
        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole($role->value);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    app(PackingAssignmentService::class)->runDailyAssignment($hariKerja);

    $penerima = DB::table('daily_packing_tasks')
        ->whereDate('tanggal_tugas', $hariKerja)->pluck('user_id');

    // Harus ada penerima, supaya invariannya tidak kosong-dan-lolos.
    expect($penerima)->not->toBeEmpty();

    // Invarian: menerima tugas berarti boleh memindai. Manager dulu melanggar ini —
    // ia menerima tugas setiap hari padahal start-scanning menolaknya dengan 403.
    foreach ($penerima as $id) {
        $u = User::find($id);
        expect($u->can(PermissionEnum::WAREHOUSE_PACKING_SCAN->value))
            ->toBeTrue("user #{$id} ({$u->name}) menerima tugas packing tetapi tidak boleh memindai");
    }
});
