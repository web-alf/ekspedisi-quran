<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\PackingItem;
use App\Models\PackingScanLog;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\Cache\StatusPengirimanCache;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tugas packing berjenis (keputusan pemilik 28/10/2026).
 *
 * - Modal Assign Target Harian mewajibkan satu jenis: A5/A6 dengan atau tanpa terjemahan,
 *   atau Iqro. Varian hanya keterangan: resi belum menyimpan varian terjemahan.
 * - Saat scan (dan catat kerdus), resi berjenis lain DITOLAK.
 * - Gudang tugas mengikuti penempatan staf yang ditugasi; kolom gudang di modal dihapus.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    StatusPengirimanCache::clear();
});

function berjenisAkun(string $role, ?Gudang $gudang = null): User
{
    $user = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

/** PJ = manager-repacking TANPA spv-gudang (tidak terikat gudang). */
function berjenisPj(): User
{
    return berjenisAkun(RoleEnum::MANAGER_REPACKING->value);
}

function berjenisJenis(string $kode): JenisQuran
{
    $jenis = JenisQuran::where('kode_jenis', $kode)->first() ?? match ($kode) {
        'A5' => JenisQuran::factory()->a5()->create(),
        'A6' => JenisQuran::factory()->a6()->create(),
        'IQRA' => JenisQuran::factory()->iqra()->create(),
    };
    $jenis->update(['is_active' => true, 'default_capacity' => match ($kode) {
        'A5' => 20,
        'A6' => 40,
        'IQRA' => 160,
    }]);

    return $jenis->refresh();
}

function berjenisResi(JenisQuran $jenis, ?Gudang $gudang = null): Pengiriman
{
    $statusPacking = StatusPengiriman::firstOrCreate(['slug' => 'packing'], ['nama' => 'Proses Packing', 'urutan' => 4]);

    return Pengiriman::factory()->create([
        'jenis_quran_id' => $jenis->id,
        'status_id' => $statusPacking->id,
        'gudang_id' => $gudang?->id,
        'jumlah_quran' => 1,
    ]);
}

function tugasHariIni(User $user): ?DailyPackingTask
{
    return DailyPackingTask::where('user_id', $user->id)->whereDate('tanggal_tugas', today())->first();
}

it('menyimpan jenis dan varian tugas dari modal Assign Target', function () {
    $semarang = Gudang::byKode('SMG');
    $a5 = berjenisJenis('A5');
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value, $semarang);

    $this->actingAs(berjenisAkun(RoleEnum::SPV_GUDANG->value, $semarang))
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 80,
            'jenis_quran_id' => $a5->id,
            'terjemahan' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.jenis', 'A5 Terjemahan')
        ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'A5 Terjemahan') && str_contains($pesan, 'Gudang Semarang'));

    $tugas = tugasHariIni($freelancer);
    expect($tugas->jenis_quran_id)->toBe($a5->id)
        ->and($tugas->terjemahan)->toBeTrue()
        ->and($tugas->labelJenis())->toBe('A5 Terjemahan');
});

it('mewajibkan jenis, dan varian hanya untuk A5/A6', function () {
    $semarang = Gudang::byKode('SMG');
    $spv = berjenisAkun(RoleEnum::SPV_GUDANG->value, $semarang);
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value, $semarang);

    $this->actingAs($spv)
        ->postJson(route('admin.supervisor.assign-target'), ['user_id' => $freelancer->id, 'target' => 80])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['jenis_quran_id' => 'Pilih jenis mushaf untuk tugas ini.']);

    $this->actingAs($spv)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 80,
            'jenis_quran_id' => berjenisJenis('A6')->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('terjemahan');

    expect(tugasHariIni($freelancer))->toBeNull();

    // Iqro tidak punya varian: nilai terjemahan yang tetap terkirim diabaikan.
    $iqra = berjenisJenis('IQRA');
    $this->actingAs($spv)
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 80,
            'jenis_quran_id' => $iqra->id,
            'terjemahan' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.jenis', 'Iqro');

    expect(tugasHariIni($freelancer)->terjemahan)->toBeNull();
});

it('mengganti jenis saat target tugas yang sudah ada diperbarui', function () {
    $semarang = Gudang::byKode('SMG');
    $spv = berjenisAkun(RoleEnum::SPV_GUDANG->value, $semarang);
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value, $semarang);

    foreach ([['A5', false, 60], ['A6', true, 70]] as [$kode, $terjemahan, $target]) {
        $this->actingAs($spv)
            ->postJson(route('admin.supervisor.assign-target'), [
                'user_id' => $freelancer->id,
                'target' => $target,
                'jenis_quran_id' => berjenisJenis($kode)->id,
                'terjemahan' => $terjemahan,
            ])
            ->assertSuccessful();
    }

    $tugas = tugasHariIni($freelancer);
    expect(DailyPackingTask::where('user_id', $freelancer->id)->count())->toBe(1)
        ->and($tugas->total_target)->toBe(70)
        ->and($tugas->labelJenis())->toBe('A6 Terjemahan');
});

it('memakai penempatan staf sebagai gudang tugas, bukan pilihan penugasa', function () {
    $boyolali = Gudang::byKode('BTL');
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value, $boyolali);

    // gudang_id dari klien diabaikan: kolomnya sudah dihapus dari modal.
    $this->actingAs(berjenisPj())
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 50,
            'jenis_quran_id' => berjenisJenis('A5')->id,
            'terjemahan' => false,
            'gudang_id' => Gudang::byKode('SMG')->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.gudang', 'Gudang Boyolali');

    expect(tugasHariIni($freelancer)->gudang_id)->toBe($boyolali->id);
});

it('menolak penugasan oleh PJ untuk staf yang belum ditempatkan di gudang', function () {
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value);

    $this->actingAs(berjenisPj())
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 50,
            'jenis_quran_id' => berjenisJenis('IQRA')->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'Penempatan Gudang'));

    expect(tugasHariIni($freelancer))->toBeNull();
});

it('memberi gudang SPV kepada staf belum ditempatkan yang ditugasi SPV itu', function () {
    $semarang = Gudang::byKode('SMG');
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value);

    $this->actingAs(berjenisAkun(RoleEnum::SPV_GUDANG->value, $semarang))
        ->postJson(route('admin.supervisor.assign-target'), [
            'user_id' => $freelancer->id,
            'target' => 50,
            'jenis_quran_id' => berjenisJenis('IQRA')->id,
        ])
        ->assertSuccessful();

    expect(tugasHariIni($freelancer)->gudang_id)->toBe($semarang->id);
});

it('mengirim pilihan jenis berurutan ke Monitor, tanpa pilihan gudang', function () {
    $a5 = berjenisJenis('A5');
    $a6 = berjenisJenis('A6');
    $iqra = berjenisJenis('IQRA');
    JenisQuran::whereNotIn('id', [$a5->id, $a6->id, $iqra->id])->update(['is_active' => false]);

    $props = $this->actingAs(berjenisPj())
        ->get(route('admin.supervisor.warehouse-monitor'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect(collect($props['pilihanJenis'])->pluck('label')->all())->toBe([
        'A5 Tanpa Terjemahan',
        'A5 Terjemahan',
        'A6 Tanpa Terjemahan',
        'A6 Terjemahan',
        'Iqro',
    ])
        ->and($props['pilihanJenis'][4])->toMatchArray(['nilai' => $iqra->id.':-', 'jenis_quran_id' => $iqra->id, 'terjemahan' => null])
        ->and($props)->not->toHaveKey('pilihGudang')
        ->and($props)->not->toHaveKey('gudangList');
});

it('menampilkan jenis tugas dan gudang tugas kandidat di Monitor', function () {
    $semarang = Gudang::byKode('SMG');
    $a5 = berjenisJenis('A5');
    $freelancer = berjenisAkun(RoleEnum::WAREHOUSE->value, $semarang);
    $belumDitempatkan = berjenisAkun(RoleEnum::WAREHOUSE->value);
    DailyPackingTask::factory()->create([
        'user_id' => $freelancer->id,
        'tanggal_tugas' => today(),
        'gudang_id' => $semarang->id,
        'jenis_quran_id' => $a5->id,
        'terjemahan' => false,
    ]);

    $props = $this->actingAs(berjenisPj())
        ->get(route('admin.supervisor.warehouse-monitor'))
        ->viewData('page')['props'];

    $tugas = collect($props['dailyTasks'])->firstWhere('user_id', $freelancer->id);
    $kandidat = collect($props['warehouseUsers'])->keyBy('id');

    expect($tugas['jenis'])->toBe('A5 Tanpa Terjemahan')
        ->and($tugas['jenis_nilai'])->toBe($a5->id.':0')
        ->and($kandidat[$freelancer->id]['gudang_tugas'])->toBe('Gudang Semarang')
        ->and($kandidat[$belumDitempatkan->id]['gudang_tugas'])->toBeNull();
});

it('menolak scan resi berjenis lain dan tetap menerima jenis tugasnya', function () {
    $petugas = berjenisAkun(RoleEnum::WAREHOUSE->value);
    $a5 = berjenisJenis('A5');
    $a6 = berjenisJenis('A6');
    DailyPackingTask::factory()->create([
        'user_id' => $petugas->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
        'jenis_quran_id' => $a5->id,
        'terjemahan' => true,
    ]);

    $resiA6 = berjenisResi($a6);
    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $resiA6->no_resi])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('type', 'jenis_tugas_berbeda')
        ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'khusus A5 Terjemahan') && str_contains($pesan, 'A6'));

    expect(PackingItem::where('pengiriman_id', $resiA6->id)->exists())->toBeFalse()
        ->and(PackingScanLog::where('type', 'jenis_tugas_berbeda')->count())->toBe(1);

    // Varian tidak diperiksa (resi belum menyimpannya): resi A5 mana pun diterima.
    $resiA5 = berjenisResi($a5);
    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $resiA5->no_resi])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PackingItem::where('pengiriman_id', $resiA5->id)->exists())->toBeTrue();
});

it('menolak resi berjenis lain lewat jalur konfirmasi ganti jenis', function () {
    $petugas = berjenisAkun(RoleEnum::WAREHOUSE->value);
    DailyPackingTask::factory()->create([
        'user_id' => $petugas->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
        'jenis_quran_id' => berjenisJenis('IQRA')->id,
    ]);
    $resiA5 = berjenisResi(berjenisJenis('A5'));

    $this->actingAs($petugas)
        ->postJson(route('admin.warehouse.packing.scan-confirm'), ['pengiriman_id' => $resiA5->id, 'confirm_seal_others' => true])
        ->assertJsonPath('success', false);

    expect(PackingItem::where('pengiriman_id', $resiA5->id)->exists())->toBeFalse();
});

it('menolak catat kerdus jenis lain', function () {
    $petugas = berjenisAkun(RoleEnum::WAREHOUSE->value);
    $a6 = berjenisJenis('A6');
    DailyPackingTask::factory()->create([
        'user_id' => $petugas->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
        'jenis_quran_id' => berjenisJenis('A5')->id,
        'terjemahan' => false,
    ]);
    berjenisResi($a6);

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/catat-kerdus', ['jenis_quran_id' => $a6->id, 'jumlah_kerdus' => 1])
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'khusus A5 Tanpa Terjemahan'));

    expect(PackingItem::count())->toBe(0);
});

it('tidak membatasi tugas lama yang belum punya jenis', function () {
    $petugas = berjenisAkun(RoleEnum::WAREHOUSE->value);
    DailyPackingTask::factory()->create([
        'user_id' => $petugas->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
    ]);
    $resiA6 = berjenisResi(berjenisJenis('A6'));

    $this->actingAs($petugas)
        ->postJson('/admin/warehouse/packing/scan', ['qr_data' => $resiA6->no_resi])
        ->assertJsonPath('success', true);
});

it('menyebut jenis tugas di halaman Packing dan hanya menawarkan jenis itu untuk catat kerdus', function () {
    $petugas = berjenisAkun(RoleEnum::WAREHOUSE->value);
    $a6 = berjenisJenis('A6');
    berjenisJenis('A5');
    DailyPackingTask::factory()->create([
        'user_id' => $petugas->id,
        'tanggal_tugas' => today(),
        'total_target' => 50,
        'jenis_quran_id' => $a6->id,
        'terjemahan' => true,
    ]);

    $props = $this->actingAs($petugas)
        ->get(route('admin.warehouse.packing.index'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($props['task']['jenis'])->toBe('A6 Terjemahan')
        ->and(collect($props['jenisList'])->pluck('id')->all())->toBe([$a6->id]);

    $dashboard = $this->actingAs($petugas)
        ->get(route('admin.warehouse.dashboard'))
        ->assertSuccessful()
        ->viewData('page')['props'];

    expect($dashboard['todayTask']['jenis'])->toBe('A6 Terjemahan');
});
