<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingBox;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use App\Services\StokMushafService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Fase 3b: kartu dashboard per gudang di Monitor Gudang.
 *
 * Empat kartu sesuai permintaan pemilik sistem: Proses Pemesanan, Quran Ready
 * di Gudang (belum ada muwakif), Proses Packing, dan Selesai Packing (siap
 * distribusi). Yang dipatok di sini:
 * - ready DIBACA dari stok_mushaf per gudang (dus + eks);
 * - pemecahan per gudang untuk packing/selesai DIBACA dari kerdus → tugas →
 *   gudang (resi tidak membawa gudang sendiri), dengan angka global tetap ada
 *   supaya kartu tidak berbohong;
 * - kerdus aktif dihitung untuk tugas HARI INI, kerdus tersegel untuk semua waktu.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    StatusPengirimanCache::clear();
});

function kartuStatus(string $slug, string $nama, int $urutan): StatusPengiriman
{
    return StatusPengiriman::firstOrCreate(
        ['slug' => $slug],
        ['nama' => $nama, 'urutan' => $urutan, 'is_active' => true, 'is_final' => false]
    );
}

function kartuLihat($test, ?User $pemantau = null): array
{
    $pemantau ??= User::factory()->create(['is_active' => true, 'gudang_id' => null]);
    $pemantau = User::find($pemantau->id);
    if (! $pemantau->hasRole(RoleEnum::MANAGER_REPACKING->value) && ! $pemantau->hasRole(RoleEnum::SPV_GUDANG->value)) {
        $pemantau->assignRole(RoleEnum::MANAGER_REPACKING->value);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $test->actingAs($pemantau)
        ->get(route('admin.supervisor.warehouse-monitor'))
        ->assertSuccessful()
        ->viewData('page')['props']['kartuGudang'];
}

it('menyusun kartu per gudang dari stok, kerdus aktif, dan kerdus tersegel', function () {
    $semarang = Gudang::byKode('SMG');
    $boyolali = Gudang::byKode('BTL');
    $jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();
    $jenis->update(['default_capacity' => 20]);

    kartuStatus('pemesanan', 'Proses Pemesanan', 1);
    kartuStatus('packing', 'Proses Packing', 4);
    $statusSelesai = kartuStatus('selesai-packing', 'Selesai Packing', 5);

    // Stok belum ada muwakif: 40 eks A5 di Semarang (= 2 dus).
    app(StokMushafService::class)->catatMasuk($semarang, $jenis, 40, 'uji', User::factory()->create());

    // Kerdus aktif hari ini milik tugas Semarang; kerdus tersegel milik Boyolali.
    $petugas = User::factory()->create(['is_active' => true]);
    $tugasSemarang = DailyPackingTask::factory()->create([
        'user_id' => $petugas->id, 'gudang_id' => $semarang->id,
        'tanggal_tugas' => today(), 'total_target' => 50,
    ]);
    $tugasBoyolali = DailyPackingTask::factory()->create([
        'user_id' => $petugas->id, 'gudang_id' => $boyolali->id,
        'tanggal_tugas' => today()->subDays(2), 'total_target' => 50,
    ]);
    PackingBox::factory()->create([
        'daily_packing_task_id' => $tugasSemarang->id, 'jenis_quran_id' => $jenis->id,
        'kapasitas' => 20, 'status' => 'filling', 'jumlah_terisi' => 5,
    ]);
    PackingBox::factory()->create([
        'daily_packing_task_id' => $tugasBoyolali->id, 'jenis_quran_id' => $jenis->id,
        'kapasitas' => 20, 'status' => 'sealed', 'jumlah_terisi' => 20,
    ]);

    Pengiriman::factory()->count(3)->create(['status_id' => kartuStatus('pemesanan', 'Proses Pemesanan', 1)->id]);
    Pengiriman::factory()->count(2)->create(['status_id' => StatusPengiriman::where('slug', 'packing')->value('id')]);
    Pengiriman::factory()->create(['status_id' => $statusSelesai->id]);

    $kartu = kartuLihat($this);

    expect($kartu['pemesanan'])->toBe(3)
        ->and($kartu['packing']['total'])->toBe(2)
        ->and($kartu['selesai']['total'])->toBe(1);

    // Ready: hanya Semarang yang pegang stok; Boyolali 0.
    $readySemarang = collect($kartu['ready'])->firstWhere('gudang', 'Gudang Semarang');
    $readyBoyolali = collect($kartu['ready'])->firstWhere('gudang', 'Gudang Boyolali');
    expect($readySemarang['dus'])->toBe(2)
        ->and($readySemarang['eks'])->toBe(40)
        ->and($readyBoyolali['eks'])->toBe(0);

    // Proses Packing: eks di kerdus AKTIF hari ini bertagih Semarang (5 eks).
    $aktif = collect($kartu['packing']['per_gudang'])->firstWhere('gudang', 'Gudang Semarang');
    expect($aktif['eks'])->toBe(5)
        ->and($aktif['kerdus'])->toBe(1);

    // Selesai: kerdus tersegel Boyolali (semua waktu) = 20 eks.
    $selesai = collect($kartu['selesai']['per_gudang'])->firstWhere('gudang', 'Gudang Boyolali');
    expect($selesai['kerdus'])->toBe(1)
        ->and($selesai['eks'])->toBe(20);
});

it('membatasi monitor untuk SPV: tugas & kartu hanya gudangnya sendiri', function () {
    $semarang = Gudang::byKode('SMG');
    $boyolali = Gudang::byKode('BTL');
    $jenis = JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create();

    app(StokMushafService::class)->catatMasuk($semarang, $jenis, 40, 'uji', User::factory()->create());

    $petugas = User::factory()->create(['is_active' => true]);
    foreach ([
        [$semarang, 'filling'],
        [$boyolali, 'sealed'],
    ] as [$gudang, $status]) {
        $tugas = DailyPackingTask::factory()->create([
            'user_id' => $petugas->id, 'gudang_id' => $gudang->id,
            'tanggal_tugas' => $status === 'filling' ? today() : today()->subDay(),
            'total_target' => 50,
        ]);
        PackingBox::factory()->create([
            'daily_packing_task_id' => $tugas->id, 'jenis_quran_id' => $jenis->id,
            'kapasitas' => 20, 'status' => $status, 'jumlah_terisi' => 20,
        ]);
    }

    kartuStatus('pemesanan', 'Proses Pemesanan', 1);
    kartuStatus('packing', 'Proses Packing', 4);
    kartuStatus('selesai-packing', 'Selesai Packing', 5);

    $spv = User::factory()->create(['is_active' => true, 'gudang_id' => $semarang->id]);
    $spv->assignRole(RoleEnum::SPV_GUDANG->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $props = $this->actingAs($spv->fresh())
        ->get(route('admin.supervisor.warehouse-monitor'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    // Daftar tugas: hanya yang bertagih gudangnya.
    expect(count($props['dailyTasks']))->toBe(1)
        ->and($props['dailyTasks'][0]['gudang'])->toBe('Gudang Semarang')
        // Kartu: baris per gudang hanya gudangnya (baris nol tetap tampil,
        // supaya SPV tahu angkanya memang 0 — bukan data hilang).
        ->and(count($props['kartuGudang']['ready']))->toBe(1)
        ->and($props['kartuGudang']['ready'][0]['gudang'])->toBe('Gudang Semarang')
        ->and($props['kartuGudang']['ready'][0]['eks'])->toBe(40)
        ->and($props['kartuGudang']['packing']['per_gudang'][0]['gudang'])->toBe('Gudang Semarang')
        ->and(count($props['kartuGudang']['selesai']['per_gudang']))->toBe(1)
        ->and($props['kartuGudang']['selesai']['per_gudang'][0]['gudang'])->toBe('Gudang Semarang')
        ->and($props['kartuGudang']['selesai']['per_gudang'][0]['eks'])->toBe(0);
});

it('kartu tetap ada walau semua angka nol', function () {
    kartuStatus('pemesanan', 'Proses Pemesanan', 1);
    kartuStatus('packing', 'Proses Packing', 4);
    kartuStatus('selesai-packing', 'Selesai Packing', 5);

    $kartu = kartuLihat($this);

    expect($kartu['pemesanan'])->toBe(0)
        ->and(count($kartu['ready']))->toBe(2)
        ->and(collect($kartu['packing']['per_gudang'])->sum('eks'))->toBe(0)
        ->and(collect($kartu['selesai']['per_gudang'])->sum('eks'))->toBe(0);
});
