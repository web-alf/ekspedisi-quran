<?php

use App\Models\CertificateTemplate;
use App\Models\Donatur;
use App\Models\Sertifikat;
use App\Models\WakafBatch;
use App\Services\ConsolidatedCertificateService;
use App\Services\OnDemandCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sertifikat hanya boleh tercatat kalau PDF-nya benar-benar bisa dibuat.
 *
 * `OnDemandCertificateService::generateOnDemand()` menulis catatan sertifikat
 * lebih dulu, baru memeriksa template. Kalau templatenya tidak ada — dan di
 * produksi memang tidak ada satu pun — catatannya sudah terlanjur tersimpan:
 * sebuah "sertifikat" yang tampak sah, ikut terhitung di rekap, tetapi PDF-nya
 * mustahil dibuat. Itulah asal `CERT-EQ-2026-00001` yang menggantung tanpa batch.
 *
 * Seluruh berkas ini menjaga satu hal: kegagalan apa pun sebelum PDF jadi harus
 * tidak meninggalkan jejak apa pun.
 */
it('menolak membuat sertifikat dan tidak meninggalkan catatan apa pun bila belum ada template', function () {
    $batch = WakafBatch::factory()->create(['total_quran' => 10]);

    expect(CertificateTemplate::count())->toBe(0)
        ->and(Sertifikat::count())->toBe(0);

    expect(fn () => app(OnDemandCertificateService::class)->generateOnDemand($batch))
        ->toThrow(Exception::class, 'Belum ada template sertifikat');

    expect(Sertifikat::count())->toBe(0);
});

it('tidak meninggalkan catatan sertifikat bila berkas templatenya ternyata hilang', function () {
    // Factory menunjuk berkas yang tidak pernah ada di disk.
    CertificateTemplate::factory()->default()->create(['is_default' => true]);
    $batch = WakafBatch::factory()->create(['total_quran' => 10]);

    expect(fn () => app(OnDemandCertificateService::class)->generateOnDemand($batch))
        ->toThrow(Exception::class);

    expect(Sertifikat::count())->toBe(0);
});

it('mencatat sertifikat lengkap dengan template yang benar ketika PDF-nya benar-benar jadi', function () {
    $path = 'templates/uji-sertifikat.png';
    $full = storage_path('app/public/'.$path);
    @mkdir(dirname($full), 0755, true);
    $gambar = imagecreatetruecolor(2000, 1414);
    imagefill($gambar, 0, 0, imagecolorallocate($gambar, 255, 255, 255));
    imagepng($gambar, $full);
    imagedestroy($gambar);

    try {
        $template = CertificateTemplate::factory()->default()->create([
            'is_default' => true,
            'template_file_path' => $path,
        ]);
        $batch = WakafBatch::factory()->create(['total_quran' => 10]);

        $pdf = app(OnDemandCertificateService::class)->generateOnDemand($batch);

        expect($pdf)->not->toBeNull()
            ->and(Sertifikat::count())->toBe(1);

        $sertifikat = Sertifikat::first();

        // template_used tidak boleh lagi berbunyi 'default' — catatan harus menyebut
        // template yang sungguh dipakai.
        expect($sertifikat->wakaf_batch_id)->toBe($batch->id)
            ->and($sertifikat->template_id)->toBe($template->id)
            ->and($sertifikat->template_used)->toBe($template->slug);
    } finally {
        @unlink($full);
    }
});

it('tidak meninggalkan catatan sertifikat konsolidasi bila belum ada template', function () {
    // Jalur kembar: sertifikat konsolidasi per donatur dulu menulis catatannya lebih
    // dulu juga, dengan cacat yang sama persis.
    $donatur = Donatur::factory()->create();

    expect(fn () => app(ConsolidatedCertificateService::class)->generateConsolidatedCertificate($donatur))
        ->toThrow(Exception::class, 'Belum ada template sertifikat');

    expect(Sertifikat::count())->toBe(0);
});

it('tidak meninggalkan catatan sertifikat bila batchnya sendiri tidak layak dicetak', function () {
    CertificateTemplate::factory()->default()->create(['is_default' => true]);
    $batch = WakafBatch::factory()->create(['total_quran' => 0]);

    expect(fn () => app(OnDemandCertificateService::class)->generateOnDemand($batch))
        ->toThrow(Exception::class, 'Jumlah Al-Quran');

    expect(Sertifikat::count())->toBe(0);
});
