<?php

use App\Models\Donatur;
use App\Models\KodeSequence;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use App\Services\ConsolidatedCertificateService;
use App\Services\OnDemandCertificateService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Race condition sertifikat & kode berurutan.
 *
 * - Pembuat sertifikat dulu "cek dulu, lalu buat" tanpa kunci dan tanpa unique
 *   index, sehingga dua proses bersamaan membuat dua sertifikat untuk satu batch
 *   (atau dua sertifikat konsolidasi untuk satu donatur).
 * - Nomor sertifikat dan batch_code dihitung "terakhir + 1" / count() + 1,
 *   sehingga dua proses bersamaan mendapat nomor yang sama.
 *
 * Proses kedua ditiru dengan instance basi yang dimuat sebelum proses pertama
 * selesai (relasi `sertifikat` masih kosong).
 */
it('menolak dua sertifikat untuk satu batch di tingkat database', function () {
    $batch = WakafBatch::factory()->create();
    Sertifikat::factory()->create(['wakaf_batch_id' => $batch->id]);

    expect(fn () => Sertifikat::factory()->create(['wakaf_batch_id' => $batch->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('mengembalikan sertifikat yang sudah ada saat batch diproses bersamaan', function () {
    $batch = WakafBatch::factory()->create();
    $basi = WakafBatch::with('sertifikat')->find($batch->id);

    $pertama = Sertifikat::factory()->create(['wakaf_batch_id' => $batch->id]);

    $layanan = app(OnDemandCertificateService::class);
    $kedua = (fn () => $this->getOrCreateSertifikatRecord($basi))->call($layanan);

    expect($kedua->id)->toBe($pertama->id)
        ->and(Sertifikat::where('wakaf_batch_id', $batch->id)->count())->toBe(1);
});

it('hanya membuat satu sertifikat konsolidasi per donatur', function () {
    $donatur = Donatur::factory()->create();

    $pertama = Sertifikat::factory()->create([
        'wakaf_batch_id' => null,
        'donatur_id' => $donatur->id,
        'is_consolidated' => true,
    ]);

    expect(fn () => Sertifikat::factory()->create([
        'wakaf_batch_id' => null,
        'donatur_id' => $donatur->id,
        'is_consolidated' => true,
    ]))->toThrow(UniqueConstraintViolationException::class);

    // Lewat layanan, tabrakan itu berubah menjadi "pakai yang sudah ada".
    $lewatLayanan = app(ConsolidatedCertificateService::class)->createConsolidatedCertificateRecord($donatur);

    expect($lewatLayanan->id)->toBe($pertama->id);
});

it('tetap mengizinkan banyak sertifikat non-konsolidasi untuk donatur yang sama', function () {
    $donatur = Donatur::factory()->create();

    Sertifikat::factory()->count(2)->create([
        'donatur_id' => $donatur->id,
        'is_consolidated' => false,
    ]);

    expect(Sertifikat::where('donatur_id', $donatur->id)->count())->toBe(2);
});

it('melanjutkan nomor sertifikat dari data lama lalu tidak pernah memakai ulang nomor', function () {
    $prefix = 'CERT-EQ-'.date('Y').'-';
    Sertifikat::factory()->create(['nomor_sertifikat' => $prefix.'00041']);

    $a = Sertifikat::factory()->create();
    $b = Sertifikat::factory()->create();

    expect($a->nomor_sertifikat)->toBe($prefix.'00042')
        ->and($b->nomor_sertifikat)->toBe($prefix.'00043');

    // Pola lama (nomor terakhir + 1) memberi 00043 lagi setelah 00043 dihapus.
    $b->delete();

    expect(Sertifikat::factory()->create()->nomor_sertifikat)->toBe($prefix.'00044');
});

it('membuat batch_code tahunan yang tidak bertabrakan walau ada batch terhapus', function () {
    $prefix = 'WB-'.date('Y').'-';
    WakafBatch::factory()->create(['batch_code' => $prefix.'00007']);
    $lain = WakafBatch::factory()->create(['batch_code' => $prefix.'00003']);

    // Pola lama count() + 1 = 3 di sini, padahal WB-…-00003 sudah ada.
    $lain->delete();

    expect(WakafBatch::buatBatchCodeTahunan())->toBe($prefix.'00008')
        ->and(WakafBatch::buatBatchCodeTahunan())->toBe($prefix.'00009');
});

it('memberi nomor berbeda untuk setiap pemanggilan kunci yang sama', function () {
    $nilaiAwalDipanggil = 0;
    $awal = function () use (&$nilaiAwalDipanggil) {
        $nilaiAwalDipanggil++;

        return 10;
    };

    $nomor = collect(range(1, 5))->map(fn () => KodeSequence::berikutnya('UJI-', $awal));

    expect($nomor->all())->toBe([11, 12, 13, 14, 15])
        // Nilai awal dari data lama hanya dihitung sekali, saat kunci pertama kali dibuat.
        ->and($nilaiAwalDipanggil)->toBe(1)
        ->and(KodeSequence::berikutnya('LAIN-', fn () => 0))->toBe(1);
});
