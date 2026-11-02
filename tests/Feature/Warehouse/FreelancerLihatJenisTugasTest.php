<?php

use App\Enums\RoleEnum;
use App\Models\DailyPackingTask;
use App\Models\Gudang;
use App\Models\JenisQuran;
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
 * Freelancer bertugas satu jenis (mis. A6) hanya melihat jenis itu (02/11/2026).
 *
 * Proses Packing sudah menyaring "Siap Dikemas" dan pilihan kerdus, dan scan jenis lain
 * ditolak. Yang masih memperlihatkan A5/Iqro: halaman Stok Mushaf (freelancer punya
 * stok.read) dan data resi tersedia (availablePengiriman) yang ikut terkirim ke halaman
 * Packing. Keduanya kini dibatasi ke jenis tugas hari ini.
 */
beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    StatusPengirimanCache::clear();

    $this->statusPacking = StatusPengiriman::firstOrCreate(['slug' => 'packing'], ['nama' => 'Proses Packing', 'urutan' => 4]);
    $this->btl = Gudang::byKode('BTL');
    $this->jenis = [
        'A5' => JenisQuran::where('kode_jenis', 'A5')->first() ?? JenisQuran::factory()->a5()->create(),
        'A6' => JenisQuran::where('kode_jenis', 'A6')->first() ?? JenisQuran::factory()->a6()->create(),
        'IQRA' => JenisQuran::where('kode_jenis', 'IQRA')->first() ?? JenisQuran::factory()->iqra()->create(),
    ];

    $this->buatAkun = function (array $peran): User {
        $user = User::factory()->create(['is_active' => true, 'gudang_id' => $this->btl->id]);
        $user->syncRoles($peran);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    };

    $this->admin = ($this->buatAkun)([RoleEnum::SUPER_ADMIN->value]);
    $this->freelancer = ($this->buatAkun)([RoleEnum::WAREHOUSE->value]);

    $stok = app(StokMushafService::class);
    foreach ($this->jenis as $jenis) {
        $stok->catatMasuk($this->btl, $jenis, 50, false, 'Isi Boyolali', $this->admin);
    }

    $this->tugas = fn (User $akun, ?string $kodeJenis): DailyPackingTask => DailyPackingTask::factory()->create([
        'user_id' => $akun->id,
        'gudang_id' => $this->btl->id,
        'tanggal_tugas' => today(),
        'total_target' => 40,
        'total_selesai' => 0,
        'jenis_quran_id' => $kodeJenis ? $this->jenis[$kodeJenis]->id : null,
    ]);
});

/**
 * Kode jenis yang muncul di setiap bagian halaman Stok Mushaf.
 *
 * @param  array<string, mixed>  $props
 * @return array<string, array<int, string>>
 */
function jenisDiHalamanStok(array $props): array
{
    $idKeKode = JenisQuran::pluck('kode_jenis', 'id');
    $namaKeKode = JenisQuran::pluck('kode_jenis', 'nama_jenis');
    $urut = fn ($daftar) => collect($daftar)->unique()->sort()->values()->all();

    return [
        'stokKartu' => $urut(collect($props['stokKartu'])->flatMap(fn ($g) => collect($g['baris'])->pluck('kode_jenis'))),
        'stokList' => $urut(collect($props['stokList'])->map(fn ($b) => $idKeKode[$b['jenis_id']])),
        'pergerakanList' => $urut(collect($props['pergerakanList'])->map(fn ($b) => $namaKeKode[$b['jenis']])),
        'jenisList' => $urut(collect($props['jenisList'])->pluck('kode_jenis')),
    ];
}

function propsStok(User $akun): array
{
    return test()->actingAs($akun)->get('/admin/warehouse/stok')->assertSuccessful()->viewData('page')['props'];
}

it('freelancer bertugas A6 hanya melihat stok A6 di kartu, daftar, pergerakan, dan pilihan jenis', function () {
    ($this->tugas)($this->freelancer, 'A6');

    $props = propsStok($this->freelancer);

    expect(jenisDiHalamanStok($props))->toBe([
        'stokKartu' => ['A6'],
        'stokList' => ['A6'],
        'pergerakanList' => ['A6'],
        'jenisList' => ['A6'],
    ])->and($props['jenisTugas'])->toBe('A6');
});

it('freelancer bertugas Iqro hanya melihat stok Iqro', function () {
    ($this->tugas)($this->freelancer, 'IQRA');

    $props = propsStok($this->freelancer);

    expect(jenisDiHalamanStok($props)['stokKartu'])->toBe(['IQRA'])
        ->and($props['jenisTugas'])->toBe('Iqro');
});

it('freelancer tanpa tugas hari ini atau dengan tugas lama tanpa jenis tetap melihat semua jenis gudangnya', function (?string $tugasKemarin) {
    if ($tugasKemarin === 'tanpa-jenis') {
        ($this->tugas)($this->freelancer, null);
    } elseif ($tugasKemarin === 'kemarin-a6') {
        ($this->tugas)($this->freelancer, 'A6')->update(['tanggal_tugas' => today()->subDay()]);
    }

    $props = propsStok($this->freelancer);

    expect(jenisDiHalamanStok($props)['stokKartu'])->toBe(['A5', 'A6', 'IQRA'])
        ->and($props['jenisTugas'])->toBeNull();
})->with([
    'tanpa tugas' => [null],
    'tugas tanpa jenis' => ['tanpa-jenis'],
    'tugas A6 kemarin' => ['kemarin-a6'],
]);

it('SPV gudang yang ikut packing A6 tetap melihat semua jenis stok gudangnya', function () {
    $spv = ($this->buatAkun)([RoleEnum::WAREHOUSE->value, RoleEnum::MANAGER_REPACKING->value, RoleEnum::SPV_GUDANG->value]);
    ($this->tugas)($spv, 'A6');

    $props = propsStok($spv);

    expect(jenisDiHalamanStok($props)['stokKartu'])->toBe(['A5', 'A6', 'IQRA'])
        ->and($props['jenisTugas'])->toBeNull();
});

it('halaman Packing hanya mengirim resi tersedia berjenis tugas', function () {
    ($this->tugas)($this->freelancer, 'A6');

    $resi = collect(['A5', 'A6', 'IQRA', 'A6'])->map(fn (string $kode) => Pengiriman::factory()->create([
        'jenis_quran_id' => $this->jenis[$kode]->id,
        'status_id' => $this->statusPacking->id,
        'gudang_id' => $this->btl->id,
        'qr_code_path' => 'qr-codes/contoh.png',
    ]));

    $props = $this->actingAs($this->freelancer)->get('/admin/warehouse/packing')->assertOk()->viewData('page')['props'];
    $resiA6 = $resi->filter(fn (Pengiriman $p) => $p->jenis_quran_id === $this->jenis['A6']->id)->pluck('no_resi')->sort()->values()->all();

    expect(collect($props['availablePengiriman'])->pluck('no_resi')->sort()->values()->all())->toBe($resiA6)
        ->and(collect($props['availablePengiriman'])->pluck('jenis_quran')->unique()->all())->toBe([$this->jenis['A6']->nama_jenis])
        ->and($props['availableCount'])->toBe(2);
});

it('halaman Packing tugas lama tanpa jenis tetap mengirim resi semua jenis', function () {
    ($this->tugas)($this->freelancer, null);

    foreach (['A5', 'A6', 'IQRA'] as $kode) {
        Pengiriman::factory()->create([
            'jenis_quran_id' => $this->jenis[$kode]->id,
            'status_id' => $this->statusPacking->id,
            'gudang_id' => $this->btl->id,
            'qr_code_path' => 'qr-codes/contoh.png',
        ]);
    }

    $props = $this->actingAs($this->freelancer)->get('/admin/warehouse/packing')->assertOk()->viewData('page')['props'];

    expect($props['availableCount'])->toBe(3)
        ->and(collect($props['availablePengiriman']))->toHaveCount(3);
});
