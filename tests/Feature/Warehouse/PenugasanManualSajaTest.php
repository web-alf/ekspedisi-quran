<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Cakupan: penugasan packing kini MANUAL (keputusan pemilik, 27/10/2026).
 *
 * Cron `packing:daily-assignment` pukul 06:00 dihapus dari jadwal: pekerjaan dibagi
 * manusia — PJ memberi beban kerja ke SPV gudangnya, SPV menugasi freelancer-nya.
 * Perintah artisan-nya sengaja dipertahankan untuk dijalankan manual, jadi yang diuji di
 * sini adalah JADWALNYA (routes/console.php), bukan kelas perintahnya.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function jadwalKonsol(): string
{
    return file_get_contents(base_path('routes/console.php'));
}

it('tidak lagi menjadwalkan penugasan packing harian otomatis', function () {
    $jadwal = jadwalKonsol();

    expect($jadwal)->not->toContain("Schedule::command('packing:daily-assignment')")
        // Jejak keputusan harus terbaca, bukan jadwal yang hilang tanpa alasan.
        ->and($jadwal)->toContain('Penugasan packing harian OTOMATIS DIHAPUS');
});

it('tetap menjadwalkan tugas terjadwal lain yang dibutuhkan gudang', function () {
    $jadwal = jadwalKonsol();

    foreach ([
        'packing:expire-tasks',
        'packing:check-progress',
        'packing:prune-notifications',
        'wakaf:tutup-pengiriman',
    ] as $perintah) {
        expect($jadwal)->toContain("Schedule::command('{$perintah}')");
    }
});

it('perintah penugasan manual tetap bisa dipanggil dari konsol', function () {
    // Perintahnya tidak dihapus: hanya tidak dijadwalkan. Buktikan ia masih terdaftar.
    expect(array_keys(Artisan::all()))->toContain('packing:daily-assignment');
});

it('tugas hari ini hanya lahir bila ada yang menugasi', function () {
    $freelancer = User::factory()->create(['is_active' => true, 'gudang_id' => Gudang::byKode('SMG')->id]);
    $freelancer->assignRole(RoleEnum::WAREHOUSE->value);

    // Tanpa cron: tidak ada tugas yang lahir sendiri.
    expect(DailyPackingTask::whereDate('tanggal_tugas', today())->count())->toBe(0);
});
