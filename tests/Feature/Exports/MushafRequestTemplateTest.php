<?php

use App\Exports\MushafRequestTemplateExport;
use App\Imports\MushafRequestImport;
use App\Models\MushafRequest;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Template yang DIUNDUH pengguna lewat tombol "Unduh Template".
 *
 * Berkas lepas di root repo bukan yang dipakai aplikasi — halaman admin
 * menghasilkan template dari MushafRequestTemplateExport. Karena itu isi kelas
 * ini yang menentukan apa yang benar-benar diterima pengisi berkas.
 *
 * Dua hal yang dikunci di sini:
 *  1. Baris contohnya memakai nama wilayah yang SUNGGUHAN ADA. Panduannya
 *     memperingatkan bahwa nama kelurahan yang salah tidak akan terdeteksi
 *     (dipakai apa adanya), jadi contoh yang dikarang mengajarkan kebiasaan
 *     yang merusak data peta.
 *  2. Contohnya benar-benar bisa diimpor — termasuk koordinat yang jatuh di
 *     provinsi yang tertulis, karena satu titik yang melenceng menggeser pin
 *     dan mengotori peta sebaran.
 */
beforeEach(function () {
    $this->namaBerkas = 'uji-template-mushaf.xlsx';
    $this->isiUnduhan = Storage::disk('local')->path($this->namaBerkas);

    Excel::store(new MushafRequestTemplateExport, $this->namaBerkas, 'local');
});

afterEach(function () {
    if (isset($this->isiUnduhan) && file_exists($this->isiUnduhan)) {
        unlink($this->isiUnduhan);
    }
});

it('memakai nama wilayah sungguhan pada baris contohnya', function () {
    $contoh = (new MushafRequestTemplateExport)->array();

    // Kolom wilayah: provinsi, kota_kabupaten, kecamatan, kelurahan_desa
    [$provinsi, $kabupaten, $kecamatan, $kelurahan] = [
        $contoh[0][5], $contoh[0][6], $contoh[0][7], $contoh[0][8],
    ];

    expect($kecamatan)->toBe('Garum');
    expect($kelurahan)->toBe('GARUM');

    // "Contoh Kelurahan" adalah nilai karangan: tersimpan apa adanya dan
    // mengotori data wilayah.
    foreach ($contoh as $baris) {
        expect(strtolower((string) $baris[8]))->not->toContain('contoh kelurahan');
    }

    expect($provinsi)->not->toBeEmpty();
    expect($kabupaten)->not->toBeEmpty();
});

it('membuat berkas yang bisa dibaca kembali', function () {
    expect(file_exists($this->isiUnduhan))->toBeTrue();
    expect(filesize($this->isiUnduhan))->toBeGreaterThan(0);
});

it('baris contohnya bisa diimpor dan koordinatnya jatuh di provinsi yang tertulis', function () {
    $sebelum = MushafRequest::count();

    $import = new MushafRequestImport;
    Excel::import($import, $this->isiUnduhan);
    $hasil = $import->getResults();

    // Kedua baris contoh harus masuk tanpa gagal
    expect($hasil['success_count'])->toBe(2);
    expect($hasil['error_count'])->toBe(0);

    $baru = MushafRequest::where('id', '>', $sebelum)->orderBy('id')->get();
    expect($baru)->toHaveCount(2);

    // Setiap baris punya wilayah lengkap sampai kelurahan
    foreach ($baru as $r) {
        expect($r->provinsi)->not->toBeEmpty();
        expect($r->kota_kabupaten)->not->toBeEmpty();
        expect($r->kecamatan)->not->toBeEmpty();
        expect($r->kelurahan_desa)->not->toBeEmpty();
        expect($r->provinsi_id)->not->toBeEmpty();
        expect($r->latitude)->not->toBeNull();
        expect($r->longitude)->not->toBeNull();
    }

    // Koordinat contoh 1 harus berada di Jawa Timur (batas kasar):
    // 6,5 LS–5,5 LS dan 110,5 BT–114,5 BT
    $satu = $baru->firstWhere('nama_lembaga', 'TPQ Contoh Al Falah');
    expect($satu)->not->toBeNull();
    expect((float) $satu->latitude)->toBeBetween(-9.0, -6.5);
    expect((float) $satu->longitude)->toBeBetween(110.5, 114.5);

    // Bersihkan supaya tidak mengotori basis data
    MushafRequest::where('id', '>', $sebelum)->delete();
});

it('memberi tahu bahwa baris contoh harus dihapus sebelum diimpor', function () {
    // Lembar panduan harus menyebutkannya, karena baris contoh ikut terimpor
    // bila dibiarkan.
    $export = new MushafRequestTemplateExport;
    expect($export->headings())->toContain('nama_lembaga');
    expect(MushafRequestTemplateExport::KOLOM)->toHaveCount(21);
});
