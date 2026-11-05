<?php

use App\Models\Gudang;
use App\Models\KodeSequence;
use App\Models\Pengiriman;
use App\Models\StatusPengiriman;
use App\Models\User;
use App\Services\RingkasanGudangService;
use App\Support\GudangAwal;
use Database\Seeders\JenisQuranSeeder;
use Database\Seeders\StatusPengirimanSeeder;
use Spatie\Permission\Models\Role;

/**
 * Gudang awal resi pemesanan (05/11/2026): bergantian per 1.000 resi dari yang terlama —
 * 1–1.000 Semarang, 1.001–2.000 Boyolali, dst. — dan langsung mengunci `gudang_id`.
 */
beforeEach(function () {
    $this->seed(JenisQuranSeeder::class);
    $this->seed(StatusPengirimanSeeder::class);

    $this->smg = Gudang::firstOrCreate(['kode' => 'SMG'], ['nama' => 'Gudang Semarang', 'is_active' => true]);
    $this->btl = Gudang::firstOrCreate(['kode' => 'BTL'], ['nama' => 'Gudang Boyolali', 'is_active' => true]);

    $this->statusPemesanan = StatusPengiriman::where('slug', 'pemesanan')->value('id');
});

function aturUrutanGudangAwal(int $nomorTerakhir): void
{
    KodeSequence::updateOrCreate(['kunci' => GudangAwal::KUNCI_URUTAN], ['last_number' => $nomorTerakhir]);
}

function resiPemesanan(array $atribut = []): Pengiriman
{
    return Pengiriman::factory()->create(array_merge([
        'status_id' => StatusPengiriman::where('slug', 'pemesanan')->value('id'),
        'jumlah_quran' => 1,
    ], $atribut));
}

it('memberi resi pemesanan pertama gudang Semarang', function () {
    expect(resiPemesanan()->gudang_id)->toBe($this->smg->id)
        ->and(KodeSequence::where('kunci', GudangAwal::KUNCI_URUTAN)->value('last_number'))->toBe(1);
});

it('berganti gudang setiap 1.000 resi', function (int $nomorTerakhir, string $kodeGudang) {
    aturUrutanGudangAwal($nomorTerakhir);

    expect(resiPemesanan()->gudang_id)->toBe(Gudang::where('kode', $kodeGudang)->value('id'));
})->with([
    'resi ke-1.000 masih Semarang' => [999, 'SMG'],
    'resi ke-1.001 pindah ke Boyolali' => [1000, 'BTL'],
    'resi ke-2.000 masih Boyolali' => [1999, 'BTL'],
    'resi ke-2.001 kembali ke Semarang' => [2000, 'SMG'],
    'resi ke-3.001 Boyolali lagi' => [3000, 'BTL'],
]);

it('tidak menimpa gudang yang sudah diisi pembuat resi', function () {
    $resi = resiPemesanan(['gudang_id' => $this->btl->id]);

    expect($resi->gudang_id)->toBe($this->btl->id)
        ->and((int) KodeSequence::where('kunci', GudangAwal::KUNCI_URUTAN)->value('last_number'))->toBe(0);
});

it('tidak memberi gudang awal pada resi yang dibuat di luar tahap pemesanan', function () {
    $resi = Pengiriman::factory()->create([
        'status_id' => StatusPengiriman::where('slug', 'packing')->value('id'),
    ]);

    expect($resi->gudang_id)->toBeNull();
});

it('melewati gudang nonaktif', function () {
    $this->smg->update(['is_active' => false]);

    expect(resiPemesanan()->gudang_id)->toBe($this->btl->id);
});

it('membiarkan resi tanpa gudang bila tidak ada gudang aktif', function () {
    Gudang::query()->update(['is_active' => false]);

    expect(resiPemesanan()->gudang_id)->toBeNull();
});

it('mengalokasikan resi pemesanan lama per 1.000 dan melanjutkan urutannya', function () {
    // Resi lama: dibuat tanpa gudang, sebelum aturan ini ada.
    $lama = collect(range(1, 3))->map(fn () => resiPemesanan());
    $sudahDikemas = resiPemesanan(['status_id' => StatusPengiriman::where('slug', 'packing')->value('id')]);
    $sudahBergudang = resiPemesanan();
    Pengiriman::whereIn('id', [...$lama->pluck('id'), $sudahDikemas->id])->update(['gudang_id' => null]);
    Pengiriman::whereKey($sudahBergudang->id)->update(['gudang_id' => $this->btl->id]);

    // Urutan sudah sampai resi ke-998: dua resi lama berikutnya menutup blok Semarang.
    aturUrutanGudangAwal(998);

    expect(GudangAwal::alokasikanResiLama())->toBe(3);

    expect(Pengiriman::whereIn('id', $lama->pluck('id'))->orderBy('id')->pluck('gudang_id')->all())
        ->toBe([$this->smg->id, $this->smg->id, $this->btl->id])
        ->and($sudahDikemas->fresh()->gudang_id)->toBeNull()
        ->and($sudahBergudang->fresh()->gudang_id)->toBe($this->btl->id)
        ->and(KodeSequence::where('kunci', GudangAwal::KUNCI_URUTAN)->value('last_number'))->toBe(1001);

    // Resi baru melanjutkan urutan: resi ke-1.002 masih di blok Boyolali.
    expect(resiPemesanan()->gudang_id)->toBe($this->btl->id);
});

it('mengisi kartu Sedang Dipesan per gudang', function () {
    resiPemesanan(['jumlah_quran' => 3]);
    aturUrutanGudangAwal(1000);
    resiPemesanan(['jumlah_quran' => 5]);

    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));

    $ringkasan = app(RingkasanGudangService::class)->untukManager($superAdmin);
    $perGudang = collect($ringkasan['per_gudang'])->keyBy('kode');

    expect($perGudang['SMG']['stok']['pemesanan'])->toBe(['resi' => 1, 'eks' => 3])
        ->and($perGudang['BTL']['stok']['pemesanan'])->toBe(['resi' => 1, 'eks' => 5])
        ->and($ringkasan['stok']['tanpa_gudang']['pemesanan'])->toBe(0);
});
