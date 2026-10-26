<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
use App\Models\StokMushaf;
use App\Models\TransferMushaf;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/*
 * Ringkasan DUA GUDANG di dashboard Manager Gudang (21/10/2026).
 *
 * Akun PJ tidak terikat satu gudang (gudang_id NULL) dan mengurus Semarang + Boyolali,
 * jadi dia butuh angka kedua gudang berdampingan. SPV justru TERKECUALI — di produksi
 * SPV ikut memegang role manager-repacking, sehingga cakupannya tetap gudang sendiri.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);
    $this->a5 = JenisQuran::firstOrCreate(['kode_jenis' => 'A5'], ['nama_jenis' => "Al-Qur'an A5", 'default_capacity' => 20, 'is_active' => true]);
});

function drAkun(array $peran, $gudang = null): User
{
    $u = User::factory()->create(['is_active' => true, 'gudang_id' => $gudang?->id]);
    $u->syncRoles($peran);

    return $u->refresh();
}

it('manager gudang menerima ringkasan kedua gudang: gabungan, per gudang, kartu stok', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);
    $stafSmg = drAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $stafBtl = drAkun([RoleEnum::WAREHOUSE->value], $this->btl);

    DailyPackingTask::create(['user_id' => $stafSmg->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today(), 'total_target' => 40, 'total_selesai' => 20, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()->subHours(2)]);
    DailyPackingTask::create(['user_id' => $stafBtl->id, 'gudang_id' => $this->btl->id, 'tanggal_tugas' => today(), 'total_target' => 30, 'total_selesai' => 20, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);

    StokMushaf::create(['gudang_id' => $this->smg->id, 'jenis_quran_id' => $this->a5->id, 'terjemahan' => 1, 'jumlah_eks' => 18]);
    StokMushaf::create(['gudang_id' => $this->smg->id, 'jenis_quran_id' => $this->a5->id, 'terjemahan' => 0, 'jumlah_eks' => 120]);
    StokMushaf::create(['gudang_id' => $this->btl->id, 'jenis_quran_id' => $this->a5->id, 'terjemahan' => 0, 'jumlah_eks' => 90]);
    StokMushaf::create(['gudang_id' => $this->btl->id, 'jenis_quran_id' => $this->a5->id, 'terjemahan' => 1, 'jumlah_eks' => 0]);

    $props = $this->actingAs($pj)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];
    $m = $props['ringkasanManager'];

    expect($m)->not->toBeNull()
        ->and($m['gudangList'])->toHaveCount(2)
        // Gabungan 40 + 30 = 70 (garis berhenti —nya string).
        ->and($m['gabungan']['target'])->toEqual(70)
        ->and((int) $m['gabungan']['selesai'])->toEqual(40)
        // Dua gudang, staf 1 + 1, satu belum mulai (mulai jam 2 lalu → keduanya mulai).
        ->and($m['per_gudang'])->toHaveCount(2)
        ->and($m['per_gudang'][0]['nama'])->toBe('Gudang Semarang')
        ->and($m['per_gudang'][1]['persen'])->toBe(67) // 20/30

        // Kartu stok per gudang: SMG 2 kartu, BTL 2 kartu.
        ->and($m['per_gudang'][0]['stok_kartu'])->toHaveCount(2)
        ->and(collect($m['per_gudang'][0]['stok_kartu'])->firstWhere('varian', 'Tanpa terjemahan')['eks'])->toBe(120)
        ->and(collect($m['per_gudang'][1]['stok_kartu'])->firstWhere('varian', 'Dengan terjemahan')['status'])->toBe('habis');
});

it('embansi transfer menunggu dengan arah asal dan tujuan yang jelas', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);
    $dok = TransferMushaf::create([
        'kode' => 'TM-20261021-001', 'gudang_asal_id' => $this->btl->id, 'gudang_tujuan_id' => $this->smg->id,
        'status' => 'diproses', 'jumlah_resi' => 6, 'jumlah_eks' => 6, 'created_by' => $pj->id,
    ]);

    $m = $this->actingAs($pj)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanManager'];

    expect($m['transfer_menunggu'])->toHaveCount(1)
        ->and($m['transfer_menunggu'][0]['asal'])->toBe('Gudang Boyolali')
        ->and($m['transfer_menunggu'][0]['tujuan'])->toBe('Gudang Semarang');
});

it('SPV tidak menerima ringkasan manager — walau memegang role manager-repacking', function () {
    $spv = drAkun(['warehouse', 'manager-repacking', 'spv-gudang'], $this->smg);

    $props = $this->actingAs($spv)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($props['ringkasanManager'])->toBeNull()
        ->and($props['ringkasanGudang'])->not->toBeNull()
        ->and($props['ringkasanGudang']['gudang']['nama'])->toBe('Gudang Semarang');
});

it('staf freelancer tidak menerima ringkasan manager', function () {
    $staf = drAkun([RoleEnum::WAREHOUSE->value]);

    $props = $this->actingAs($staf)->get('/admin/warehouse')->assertOk()->viewData('page')['props'];

    expect($props['ringkasanManager'])->toBeNull();
});

it('ringkasan manager ikut disegarkan lewat dashboard-status saat ?ringkasan=1', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);
    $stafSmg = drAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $stafBtl = drAkun([RoleEnum::WAREHOUSE->value], $this->btl);

    DailyPackingTask::create(['user_id' => $stafSmg->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today(), 'total_target' => 40, 'total_selesai' => 20, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()->subHours(2)]);
    DailyPackingTask::create(['user_id' => $stafBtl->id, 'gudang_id' => $this->btl->id, 'tanggal_tugas' => today(), 'total_target' => 30, 'total_selesai' => 10, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);

    $data = $this->actingAs($pj)
        ->getJson('/admin/warehouse/dashboard-status?ringkasan=1')
        ->assertOk()->json('data');

    expect($data['ringkasanManager'])->not->toBeNull()
        ->and($data['ringkasanManager']['gabungan']['target'])->toEqual(70)
        ->and(collect($data['ringkasanManager']['per_gudang'])->pluck('nama'))->toHaveCount(2)
        // PJ bukan SPV — ringkasan satu gudang tetap kosong.
        ->and($data['ringkasanGudang'])->toBeNull();
});

it('poling dashboard-status tanpa ?ringkasan tidak membawa ringkasan', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);

    // Tanpa querstring: ringkasan penuh (belasan query per gudang) tidak boleh
    // ikut di tiap tick 15 detik.
    $data = $this->actingAs($pj)->getJson('/admin/warehouse/dashboard-status')->assertOk()->json('data');

    expect(array_key_exists('ringkasanManager', $data))->toBeFalse()
        ->and(array_key_exists('ringkasanGudang', $data))->toBeFalse();
});

it('manager hanya menerima nama freelancer yang sudah mulai kerja hari ini, sama seperti SPV', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);
    $andi = drAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $budi = drAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $andi->update(['name' => 'Andi']);
    $budi->update(['name' => 'Budi']);

    DailyPackingTask::create(['user_id' => $andi->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today(), 'total_target' => 20, 'total_selesai' => 12, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()->subHour()]);
    DailyPackingTask::create(['user_id' => $budi->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today(), 'total_target' => 20, 'total_selesai' => 0, 'status' => 'assigned', 'assigned_at' => now()]);

    $smg = collect($this->actingAs($pj)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanManager']['per_gudang'])
        ->firstWhere('kode', 'SMG');

    // Bentuk barisnya kini sama dengan dashboard SPV (status, jam, kerdus, persen dipakai
    // komponen FreelancerKerjaHariIni), jadi yang dikunci: hanya Andi yang disebut, dan
    // nilai intinya tetap 12/20.
    expect(collect($smg['staf_kerja'])->pluck('nama')->all())->toBe(['Andi'])
        ->and($smg['staf_kerja'][0])->toMatchArray(['nama' => 'Andi', 'selesai' => 12, 'target' => 20, 'status' => 'mulai', 'persen' => 60])
        ->and($smg['staf_kerja'][0])->toHaveKeys(['jam_mulai', 'jam_selesai', 'kerdus'])
        ->and($smg['staf_belum_mulai'])->toBe(1)
        ->and($smg)->not->toHaveKey('belum_nama');
});

it('angka gabungan sama dengan jumlah per gudang; tugas tanpa gudang disebut terpisah', function () {
    $pj = drAkun(['warehouse', 'manager-repacking']);
    $stafSmg = drAkun([RoleEnum::WAREHOUSE->value], $this->smg);
    $stafBtl = drAkun([RoleEnum::WAREHOUSE->value], $this->btl);
    $stafLepas = drAkun([RoleEnum::WAREHOUSE->value]);

    DailyPackingTask::create(['user_id' => $stafSmg->id, 'gudang_id' => $this->smg->id, 'tanggal_tugas' => today(), 'total_target' => 40, 'total_selesai' => 20, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);
    DailyPackingTask::create(['user_id' => $stafBtl->id, 'gudang_id' => $this->btl->id, 'tanggal_tugas' => today(), 'total_target' => 30, 'total_selesai' => 10, 'status' => 'assigned', 'assigned_at' => now()]);
    DailyPackingTask::create(['user_id' => $stafLepas->id, 'gudang_id' => null, 'tanggal_tugas' => today(), 'total_target' => 50, 'total_selesai' => 5, 'status' => 'assigned', 'assigned_at' => now()]);

    $m = $this->actingAs($pj)->get('/admin/warehouse')->assertOk()->viewData('page')['props']['ringkasanManager'];
    $perGudang = collect($m['per_gudang']);

    expect($m['gabungan']['target'])->toBe($perGudang->sum('target'))->toBe(70)
        ->and($m['gabungan']['selesai'])->toBe($perGudang->sum('selesai'))->toBe(30)
        ->and($m['gabungan']['staf_bertugas'])->toBe($perGudang->sum('staf_total'))->toBe(2)
        ->and($m['gabungan']['staf_belum_mulai'])->toBe($perGudang->sum('staf_belum_mulai'))->toBe(1)
        ->and($m['gabungan']['tugas_tanpa_gudang'])->toBe(1);
});
